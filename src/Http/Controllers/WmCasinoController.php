<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SuperPlatform\StationWallet\Events\SingleWalletRecordEvent;
use SuperPlatform\StationWallet\Models\StationWallet;

class WmCasinoController
{
    /**
     * 回戳API憑證
     *
     * @var string
     */
    protected $apiKey = '';

    /**
     * 上下分的類型
     *
     * @var array
     */
    protected $changeTypes = [
        '1' => '加點',
        '2' => '扣點',
        '3' => '重對加點',
        '4' => '重對扣點',
        '5' => '重新派彩',
    ];

    public function __construct()
    {
        $this->apiKey = config('api_caller.wm_casino.config.signature_key');
    }

    /**
     * 動作派發器
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function actionDispatcher(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if (!$this->checkApiToken($request->input('signature'))) {
            $responseData = [
                'errorCode' => 103,
                'errorMessage' => "代理商ID與識別碼格式錯誤",
                'result' => []
            ];

            return response()->json($responseData, 200);
        }

        // 根據 cmd 選擇動作
        $callAction = camel_case($request->input('cmd'));
        $data = $request->except(['cmd', 'signature']);

        if (!method_exists($this, $callAction)) {
            throw new \Exception('method not existed');
        }

        $responseData = $this->$callAction($data);

        return response()->json($responseData, 200);
    }

    /**
     * 取餘額
     *
     * @param array $data
     * @return array
     */
    protected function callBalance(array $data): array
    {
        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'user'));

        // 如果status code 狀態不是 0 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== 0) {
            return [
                'errorCode' => $statusCode,
                'errorMessage' => array_get($wallet, 'message'),
                'result' => []
            ];
        }

        // 成功回傳訊息
        return [
            'errorCode' => $statusCode,
            'errorMessage' => '',
            'result' => [
                'user' => array_get($wallet, 'master_wallet.account'),
                'money' => strval(array_get($wallet, 'master_wallet.balance')),
                'responseDate' => now()->toDateTimeString()
            ]
        ];
    }

    /**
     * 加扣點
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function pointInout(array $data): array
    {
        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'user'));

        // 如果status code 狀態不是 0 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== 0) {
            return [
                'errorCode' => $statusCode,
                'errorMessage' => array_get($wallet, 'message'),
                'result' => []
            ];
        }

        $amount = floatval(array_get($data, 'money'));
        $serialNo = array_get($data, 'dealid');
        $chargeType = array_get($data, 'code');

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            // 檢查餘額不足 當amount為負值時才需檢查
            if ($amount < 0 && $this->checkBalanceNotEnough($masterWallet->balance, $amount)) {
                return [
                    'errorCode' => 10805,
                    'errorMessage' => '轉帳失敗 該帳號餘額不足',
                    'result' => []
                ];
            }

            $masterWallet->balance += $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => $amount,
                        'remark' => 'WM真人 ' . array_get($this->changeTypes, $chargeType),
                        'serial_no' => $serialNo,
                        'ticket_id' => array_get($data, 'gameno')
                    ]
                )
            );

            DB::commit();

            // 成功回傳訊息
            return [
                'errorCode' => 0,
                'errorMessage' => '',
                'result' => [
                    'money' => $amount,
                    'responseDate' => now()->toDateTimeString(),
                    'dealid' => $serialNo,
                    'cash' => $masterWallet->balance
                ]
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 回滾
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function timeoutBetReturn(array $data)
    {
        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'user'));

        // 如果status code 狀態不是 0 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== 0) {
            return [
                'errorCode' => $statusCode,
                'errorMessage' => array_get($wallet, 'message'),
                'result' => []
            ];
        }

        $amount = floatval(array_get($data, 'money'));
        $serialNo = array_get($data, 'dealid');
        $chargeType = array_get($data, 'code');

        // 尋找交易紀錄
        $records = DB::table('station_wallet_trade_records')
            ->select('SN', 'remark')
            ->where('SN', $serialNo)
            ->get();

        /**
         * 如果已經有回滾紀錄 就回傳失敗 拒絕回滾
         */
        $hasReturnRecord = $records->filter(function ($record) {
                return strpos(data_get($record, 'remark'), '回滾');
            })->isNotEmpty();
        if ($hasReturnRecord) {
            return [
                'errorCode' => 1,
                'errorMessage' => '已經有回滾紀錄 拒絕回滾',
                'result' => []
            ];
        }

        // 下注扣款回滾通知
        if ($amount < 0) {

            /**
             * 如果沒有下注扣款紀錄 就回傳失敗 拒絕回滾
             */
            if ($records->isEmpty()) {
                return [
                    'errorCode' => 1,
                    'errorMessage' => '沒有下注紀錄 拒絕回滾',
                    'result' => []
                ];
            }

            /**
             * 如果有下注扣款紀錄 就回傳成功 玩家主錢包補點 新增交易回滾紀錄
             */
            try {
                DB::beginTransaction();

                $masterWallet = StationWallet::lockForUpdate()
                    ->find(array_get($wallet, 'master_wallet.id'));

                $masterWallet->balance += -$amount; // 取負值代表補點
                $masterWallet->save();

                // 寫入錢包紀錄
                event(
                    new SingleWalletRecordEvent(
                        [
                            'wallet_id' => $masterWallet->id,
                            'amount' => -$amount, // 取負值代表補點
                            'remark' => 'WM真人 ' . array_get($this->changeTypes, $chargeType) . ' 回滾',
                            'serial_no' => $serialNo,
                            'ticket_id' => array_get($data, 'gameno')
                        ]
                    )
                );

                DB::commit();

                // 成功回傳訊息
                return [
                    'errorCode' => 0,
                    'errorMessage' => '',
                    'result' => [
                        'money' => $amount,
                        'responseDate' => now()->toDateTimeString(),
                        'dealid' => $serialNo,
                        'cash' => $masterWallet->balance
                    ]
                ];
            } catch (\Exception $exception) {
                DB::rollBack();
                throw $exception;
            }

        // 派彩入款回滾通知
        } else {

            /**
             * 如果有派彩入款紀錄 就回傳失敗 拒絕回滾
             */
            if ($records->isNotEmpty()) {
                return [
                    'errorCode' => 1,
                    'errorMessage' => '已有派彩紀錄 拒絕回滾',
                    'result' => []
                ];
            }

            /**
             * 如果沒有派彩入款紀錄 就回傳成功 玩家主錢包補點 新增交易回滾紀錄
             */
            try {
                DB::beginTransaction();

                $masterWallet = StationWallet::lockForUpdate()
                    ->find(array_get($wallet, 'master_wallet.id'));

                $masterWallet->balance += $amount;
                $masterWallet->save();

                // 寫入錢包紀錄
                event(
                    new SingleWalletRecordEvent(
                        [
                            'wallet_id' => $masterWallet->id,
                            'amount' => $amount,
                            'remark' => 'WM真人 ' . array_get($this->changeTypes, $chargeType) . ' 回滾',
                            'serial_no' => $serialNo,
                            'ticket_id' => array_get($data, 'gameno')
                        ]
                    )
                );

                DB::commit();

                // 成功回傳訊息
                return [
                    'errorCode' => 0,
                    'errorMessage' => '',
                    'result' => [
                        'money' => $amount,
                        'responseDate' => now()->toDateTimeString(),
                        'dealid' => $serialNo,
                        'cash' => $masterWallet->balance
                    ]
                ];
            } catch (\Exception $exception) {
                DB::rollBack();
                throw $exception;
            }
        }
    }

    /**
     * 檢查回戳API TOKEN憑證
     *
     * @param string $signature
     * @return bool
     */
    private function checkApiToken(string $signature)
    {
        return $this->apiKey === $signature;
    }

    /**
     * 找玩家主錢包
     *
     * @param string $account
     * @return array
     */
    private function findMasterWallet(string $account): array
    {
        $masterWallet = StationWallet::select('id', 'account', 'station', 'status', 'activated_status', 'balance')
            ->where('account', $account)
            ->where('station', 'master')
            ->first();

        if (empty($masterWallet)) {
            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => 10501,
                'message' => '查無此帳號，請檢查',
            ];
        }

        if (data_get($masterWallet, 'activated_status') !== 'yes') {
            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => 10505,
                'message' => '此帳號已被停用',
            ];
        }

        if (data_get($masterWallet, 'status') === 'freezing') {
            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => 10505,
                'message' => '此帳號已被停用',
            ];
        }

        return [
            'master_wallet' => optional($masterWallet)->toArray(),
            'status_code' => 0,
            'message' => '',
        ];
    }

    /**
     * 檢查餘額不足
     *
     * @param $balance
     * @param $amount
     * @return bool
     */
    private function checkBalanceNotEnough($balance, $amount)
    {
        return $balance < abs($amount);
    }
}