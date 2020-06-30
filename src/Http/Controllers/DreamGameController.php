<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SuperPlatform\StationWallet\Events\SingleWalletRecordEvent;
use SuperPlatform\StationWallet\Models\StationWallet;

class DreamGameController
{
    /**
     * 回戳API憑證
     *
     * @var string
     */
    protected $apiKey = '';

    public function __construct()
    {
        $this->apiKey = config('api_caller.dream_game.config.api_key');
    }

    /**
     * 獲取玩家餘額
     *
     * @param Request $request
     * @param $agentName
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function getBalance(Request $request, $agentName)
    {
        $account = $request->input('member.username');

        // 檢查回戳API TOKEN憑證
        $token = $request->input('token');
        $this->checkApiToken($token, $agentName);

        // 找玩家主錢包
        $masterWallet = $this->findMasterWallet($account);

        $responseData = [
            'codeId' => 0,
            'token' => $request->input('token'),
            'member' => [
                'username' => $account,
                'balance' => round(data_get($masterWallet, 'balance'), 2)
            ]
        ];

        return response()->json($responseData, 200);
    }

    /**
     * 存取款接口
     *
     * @param Request $request
     * @param $agentName
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function transfer(Request $request, $agentName)
    {
        $account = $request->input('member.username');
        $amount = $request->input('member.amount');
        $serialNo = $request->input('data');

        // 檢查回戳API TOKEN憑證
        $token = $request->input('token');
        $this->checkApiToken($token, $agentName);

        // 找玩家主錢包
        $masterWallet = $this->findMasterWallet($account);

        try {
            DB::beginTransaction();

            $wallet = StationWallet::lockForUpdate()
                ->find($masterWallet->id);

            // 轉帳前餘額
            $beforeBalance = $wallet->balance;

            if ($this->checkBalanceNotEnough($beforeBalance, $amount)) {
                throw new \Exception($account . '主錢包餘額不足');
            }

            $wallet->balance += $amount;
            $wallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $wallet->id,
                        'amount' => $amount,
                        'remark' => 'DG真人 上下分',
                        'serial_no' => $serialNo
                    ]
                )
            );

            DB::commit();

            $responseData = [
                'codeId' => 0,
                'token' => $request->input('token'),
                'data' => $serialNo,
                'member' => [
                    'username' => $account,
                    'amount' => $amount,
                    'balance' => round($beforeBalance, 2)
                ]
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 確認存取款結果接口
     *
     * @param Request $request
     * @param $agentName
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function checkTransfer(Request $request, $agentName)
    {
        $serialNo = $request->input('data');

        // 檢查回戳API TOKEN憑證
        $token = $request->input('token');
        $this->checkApiToken($token, $agentName);

        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN')
            ->where('SN', $serialNo)
            ->first();

        if (empty($record)) {
            throw new \Exception($serialNo . '交易紀錄不存在');
        }

        $responseData = [
            'codeId' => 0,
            'token' => $request->input('token'),
        ];

        return response()->json($responseData, 200);
    }

    /**
     * 請求回滾轉帳事務
     *
     * @param Request $request
     * @param $agentName
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function inform(Request $request, $agentName)
    {
        $amount = $request->input('member.amount');
        $account = $request->input('member.username');
        $serialNo = $request->input( 'data');

        // 檢查回戳API TOKEN憑證
        $token = $request->input('token');
        $this->checkApiToken($token, $agentName);

        // 找玩家主錢包
        $masterWallet = $this->findMasterWallet($account);

        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN')
            ->where('SN', $serialNo)
            ->latest()
            ->first();

        // 下注扣款回滾通知
        if ($amount < 0) {
            /**
             * 如果沒有紀錄 不需做任何動作 直接回傳成功訊息
             */
            if (empty($record)) {
                $responseData = [
                    'codeId' => 0,
                    'token' => $request->input('token'),
                    'data' => $serialNo,
                    'member' => [
                        'username' => $account,
                        'balance' => round(data_get($masterWallet, 'balance'), 2)
                    ],
                ];

                return response()->json($responseData, 200);
            }

            /**
             * 如果有交易紀錄成功 玩家主錢包補錢 新增交易回退紀錄
             */
            try {
                DB::beginTransaction();

                $wallet = StationWallet::lockForUpdate()
                    ->find($masterWallet->id);

                // 轉帳前餘額
                $beforeBalance = $wallet->balance;

                $wallet->balance += -$amount; // 取負值代表補錢
                $wallet->save();

                // 寫入錢包紀錄
                event(
                    new SingleWalletRecordEvent(
                        [
                            'wallet_id' => $wallet->id,
                            'amount' => -$amount, // 取負值代表補錢
                            'remark' => 'DG真人 下注扣款回滾',
                            'serial_no' => $serialNo
                        ]
                    )
                );

                DB::commit();

                $responseData = [
                    'codeId' => 0,
                    'token' => $request->input('token'),
                    'data' => $serialNo,
                    'member' => [
                        'username' => $account,
                        'balance' => round(data_get($masterWallet, 'balance'), 2)
                    ],
                ];

                return response()->json($responseData, 200);
            } catch (\Exception $exception) {
                DB::rollBack();
                throw $exception;
            }

            // 派彩入款回滾通知
        } else {

            /**
             * 如果有交易成功紀錄 不需做任何動作
             */
            if (!empty($record)) {
                $responseData = [
                    'codeId' => 0,
                    'token' => $request->input('token'),
                    'data' => $serialNo,
                    'member' => [
                        'username' => $account,
                        'balance' => round(data_get($masterWallet, 'balance'), 2)
                    ],
                ];

                return response()->json($responseData, 200);
            }

            /**
             * 如果沒有紀錄 補交易紀錄 玩家主錢包補錢
             */
            try {
                DB::beginTransaction();

                $wallet = StationWallet::lockForUpdate()
                    ->find($masterWallet->id);

                // 轉帳前餘額
                $beforeBalance = $wallet->balance;

                $wallet->balance += $amount;
                $wallet->save();

                // 寫入錢包紀錄
                event(
                    new SingleWalletRecordEvent(
                        [
                            'wallet_id' => $wallet->id,
                            'amount' => $amount,
                            'remark' => 'DG真人 派彩入款回滾',
                            'serial_no' => $serialNo
                        ]
                    )
                );

                DB::commit();

                $responseData = [
                    'codeId' => 0,
                    'token' => $request->input('token'),
                    'data' => $serialNo,
                    'member' => [
                        'username' => $account,
                        'amount' => $amount,
                        'balance' => round($beforeBalance, 2)
                    ]
                ];

                return response()->json($responseData, 200);
            } catch (\Exception $exception) {
                DB::rollBack();
                throw $exception;
            }
        }
    }

    /**
     * 檢查回戳API TOKEN憑證
     *
     * @param $token
     * @param $agentName
     * @throws \Exception
     */
    private function checkApiToken($token, $agentName)
    {
        if ($token !== md5($agentName . $this->apiKey)) {
            throw new \Exception("{$agentName} token: $token 不正確");
        }
    }

    /**
     * 找玩家主錢包
     *
     * @param string $account
     * @return mixed
     * @throws \Exception
     */
    private function findMasterWallet(string $account)
    {
        $masterWallet = StationWallet::select('id', 'account', 'station', 'status', 'activated_status', 'balance')
            ->where('account', $account)
            ->where('station', 'master')
            ->first();

        if (empty($masterWallet)) {
            throw new \Exception($account . ' 玩家主錢包不存在');
        }

        if (data_get($masterWallet, 'activated_status') !== 'yes') {
            throw new \Exception($account . ' 主錢包未開通');
        }

        if (data_get($masterWallet, 'status') === 'freezing') {
            throw new \Exception($account . '主錢包被凍結');
        }

        return $masterWallet;
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
        return ($balance + $amount) < 0;
    }
}