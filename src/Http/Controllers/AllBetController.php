<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SuperPlatform\StationWallet\Events\SingleWalletRecordEvent;
use SuperPlatform\StationWallet\Models\StationWallet;

class AllBetController
{
    /**
     * 上下分的動作列表
     *
     * @var array
     */
    protected $action = [
        10 => 'bet',
        11 => 'cancel',
        20 => 'settle',
        21 => 'reSettle',
    ];

    /**
     * 獲取玩家餘額
     *
     * @param Request $request
     * @param $client
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function getBalance(Request $request, $client)
    {
        // 找玩家主錢包
        $wallet = $this->findMasterWallet($client);

        // 如果status code 狀態不是 '0' 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'error_code' => $statusCode,
                'message' => array_get($wallet, 'message'),
                'balance' => 0
            ];

            return response()->json($responseData, 200);
        }

        // 成功回傳訊息
        $responseData = [
            'error_code' => $statusCode,
            'balance' => array_get($wallet, 'master_wallet.balance')
        ];

        return response()->json($responseData, 200);
    }

    /**
     * 上下分
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function transfer(Request $request)
    {
        $data = $request->all();

        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'client'));

        // 如果status code 狀態不是 '0' 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'error_code' => $statusCode,
                'message' => array_get($wallet, 'message'),
                'balance' => 0
            ];

            return response()->json($responseData, 200);
        }

        // 根據 transferType 選擇動作 上下分 action
        $callAction = array_get($this->action, array_get($data, 'transferType'), '');

        if (!method_exists($this, $callAction)) {
            throw new \Exception('method not existed');
        }

        // 上下方後的錢包
        $afterWallet = $this->$callAction($data, array_get($wallet, 'master_wallet'));

        // 如果status code 狀態不是 '0' 就回傳錯誤訊息
        $afterStatusCode = array_get($afterWallet, 'status_code');
        if ($afterStatusCode !== '0') {
            $responseData = [
                'error_code' => $afterStatusCode,
                'message' => array_get($afterWallet, 'message'),
                'balance' => array_get($afterWallet, 'master_wallet.balance')
            ];

            return response()->json($responseData, 200);
        }

        // 成功回傳訊息
        $responseData = [
            'error_code' => $afterStatusCode,
            'balance' => array_get($afterWallet, 'master_wallet.balance')
        ];

        return response()->json($responseData, 200);
    }

    /**
     * 下注
     *
     * @param array $data
     * @param array $masterWallet
     * @return array
     * @throws \Exception
     */
    public function bet(array $data, array $masterWallet): array
    {
        $amount = -array_get($data, 'amount');
        $serialNo = strval(array_get($data, 'tranId'));

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($masterWallet, 'id'));

            // 檢查餘額不足
            if ($this->checkBalanceNotEnough($masterWallet->balance, $amount)) {
                return [
                    'master_wallet' => optional($masterWallet)->toArray(),
                    'status_code' => '10101',
                    'message' => '投注失败, 额度不足',
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
                        'remark' => '歐博 下注',
                        'serial_no' => $serialNo
                    ]
                )
            );

            // 寫入 all_bet_single_wallet_bet_no
            $details = array_get($data, 'details');
            $insertData = [];
            foreach ($details as $detail) {
                array_push(
                    $insertData,
                    [
                        'bet_no' => array_get($detail, 'betNum'),
                        'bet_amount' => array_get($detail, 'amount'),
                        'win_lose_amount' => 0
                    ]
                );
            }

            DB::table('all_bet_single_wallet_bet_no')
                ->insert($insertData);

            DB::commit();

            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => '0',
                'message' => '',
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 取消下注
     *
     * @param array $data
     * @param array $masterWallet
     * @return array
     * @throws \Exception
     */
    public function cancel(array $data, array $masterWallet): array
    {
        $amount = array_get($data, 'amount');
        $originalSerialNo = strval(array_get($data, 'originalTranId'));
        $serialNo = strval(array_get($data, 'tranId'));

        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN')
            ->where('SN', $originalSerialNo)
            ->first();

        // 如果沒有紀錄，表示未扣點，直接回傳目前餘額
        if (empty($record)) {
            return [
                'master_wallet' => $masterWallet,
                'status_code' => '0',
                'message' => '',
            ];
        }

        // 如果已經有紀錄，表示已扣點，玩家帳戶需補錢
        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($masterWallet, 'id'));

            $masterWallet->balance += $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => $amount,
                        'remark' => '歐博 取消下注',
                        'serial_no' => $serialNo
                    ]
                )
            );

            DB::commit();

            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => '0',
                'message' => '',
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 派彩
     *
     * @param array $data
     * @param array $masterWallet
     * @return array
     * @throws \Exception
     */
    public function settle(array $data, array $masterWallet): array
    {
        $winLost = array_get($data, 'amount');
        $serialNo = strval(array_get($data, 'tranId'));

        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN')
            ->where('SN', $serialNo)
            ->first();

        // 如果已經有交易紀錄，直接回傳目前餘額，避免重複上分
        if (!empty($record)) {
            return [
                'master_wallet' => $masterWallet,
                'status_code' => '0',
                'message' => '',
            ];
        }

        try {
            DB::beginTransaction();

            $wallet = StationWallet::lockForUpdate()
                ->find(array_get($masterWallet, 'id'));

            // 讀取 all_bet_single_wallet_bet_no 所有下注資料
            $details = array_get($data, 'details');
            $betNums = collect($details)->pluck('betNum')->toArray();
            $betAmount = DB::table('all_bet_single_wallet_bet_no')
                ->select('bet_amount')
                ->whereIn('bet_no', $betNums)
                ->sum('bet_amount');

            // 下注本金 + 輸贏
            $wallet->balance += $betAmount + $winLost;
            $wallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $wallet->id,
                        'amount' => $betAmount + $winLost,
                        'remark' => '歐博 派彩',
                        'serial_no' => $serialNo
                    ]
                )
            );

            // 批次修改 all_bet_single_wallet_bet_no 輸贏金額
            foreach ($details as $detail) {
                DB::table('all_bet_single_wallet_bet_no')
                    ->where('bet_no', array_get($detail, 'betNum'))
                    ->update(
                        [
                            'win_lose_amount' => $winLost
                        ]
                    );
            }

            DB::commit();

            return [
                'master_wallet' => optional($wallet)->toArray(),
                'status_code' => '0',
                'message' => '',
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 重新派彩
     *
     * @param array $data
     * @param array $masterWallet
     * @return array
     * @throws \Exception
     */
    public function reSettle(array $data, array $masterWallet): array
    {
        $winLose = array_get($data, 'amount');
        $serialNo = strval(array_get($data, 'tranId'));

        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN')
            ->where('SN', $serialNo)
            ->first();

        // 如果已經有交易紀錄，直接回傳目前餘額，避免重複上分
        if (!empty($record)) {
            return [
                'master_wallet' => $masterWallet,
                'status_code' => '0',
                'message' => '',
            ];
        }

        try {
            DB::beginTransaction();

            $wallet = StationWallet::lockForUpdate()
                ->find(array_get($masterWallet, 'id'));

            // 讀取 all_bet_single_wallet_bet_no 所有下注資料
            $details = array_get($data, 'details');
            $betNums = collect($details)->pluck('betNum')->toArray();
            $beforeWinLost = DB::table('all_bet_single_wallet_bet_no')
                ->select('win_lose_amount')
                ->whereIn('bet_no', $betNums)
                ->sum('win_lose_amount');

            // - 之前輸贏 + 重新派彩輸贏
            $wallet->balance += -$beforeWinLost + $winLose;
            $wallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $wallet->id,
                        'amount' => -$beforeWinLost + $winLose,
                        'remark' => '歐博 重新派彩',
                        'serial_no' => $serialNo
                    ]
                )
            );

            // 批次修改 all_bet_single_wallet_bet_no 輸贏金額
            foreach ($details as $detail) {
                DB::table('all_bet_single_wallet_bet_no')
                    ->where('bet_no', array_get($detail, 'betNum'))
                    ->update(
                        [
                            'win_lose_amount' => $winLose
                        ]
                    );
            }

            DB::commit();

            return [
                'master_wallet' => optional($wallet)->toArray(),
                'status_code' => '0',
                'message' => '',
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 找玩家主錢包
     *
     * @param string $client
     * @return mixed
     */
    private function findMasterWallet(string $client): array
    {
        $account = strtoupper($client);

        $masterWallet = StationWallet::select('id', 'account', 'station', 'status', 'activated_status', 'balance')
            ->where('account', $account)
            ->where('station', 'master')
            ->first();

        if (empty($masterWallet)) {
            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => '10003',
                'message' => '玩家信息不存在',
            ];
        }

        if (data_get($masterWallet, 'activated_status') !== 'yes') {
            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => '10005',
                'message' => '玩家被禁止登录',
            ];
        }

        if (data_get($masterWallet, 'status') === 'freezing') {
            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => '10005',
                'message' => '玩家被禁止登录',
            ];
        }

        return [
            'master_wallet' => optional($masterWallet)->toArray(),
            'status_code' => '0',
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