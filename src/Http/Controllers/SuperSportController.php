<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SuperPlatform\StationWallet\Events\FailTransactionEvent;
use SuperPlatform\StationWallet\Events\SingleWalletRecordEvent;
use SuperPlatform\StationWallet\Models\StationWallet;

class SuperSportController
{
    protected $changeTypes = [
        'payout' => '派彩',
        'repay' => '重新派彩',
        'refund' => '取消注單',
        'refundCancel' => '恢復取消的注單'
    ];

    /**
     * 獲取玩家餘額
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function balance(Request $request)
    {
        // 找玩家主錢包
        $wallet = $this->findMasterWallet($request->input('account'));

        // 如果status code 狀態不是 '0' 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'code' => $statusCode,
                'data' => [
                    'errorMessage' => array_get($wallet, 'message')
                ]
            ];

            return response()->json($responseData, 200);
        }

        // 成功回傳訊息
        $responseData = [
            'code' => $statusCode,
            'data' => [
                "account" => array_get($wallet, 'master_wallet.account'),
                "balance" => array_get($wallet, 'master_wallet.balance')
            ]
        ];

        return response()->json($responseData, 200);
    }

    /**
     * 投注
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function bet(Request $request)
    {
        $account = $request->input('account');
        $amount = -abs(floatval($request->input('amount'))); // 負值表示扣點
        $serialNo = $request->input('transactionId');
        $ticketId = $request->input('ticketNo');

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0' 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'code' => $statusCode,
                'data' => [
                    'errorMessage' => array_get($wallet, 'message')
                ]
            ];

            return response()->json($responseData, 200);
        }

        // 尋找交易紀錄
        $records = DB::table('station_wallet_trade_records')
            ->select('SN')
            ->where('SN', $serialNo)
            ->get()
            ->toArray();

        // 如果已經有交易紀錄，直接回傳失敗
        if (!empty($records)) {
            $responseData = [
                'code' => '903',
                'data' => [
                    'errorMessage' => "transactionId 重複"
                ]
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            // 檢查餘額不足
            if ($this->checkBalanceNotEnough($masterWallet->balance, $amount)) {
                $responseData = [
                    'code' => '902',
                    'data' => [
                        'errorMessage' => "餘額不足"
                    ]
                ];

                return response()->json($responseData, 200);
            }

            $masterWallet->balance += $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => $amount,
                        'remark' => 'Super體育 下注',
                        'serial_no' => $serialNo,
                        'ticket_id' => $ticketId,
                    ]
                )
            );

            DB::commit();

            $responseData = [
                'code' => '0',
                'data' => [
                    "account" => data_get($masterWallet, 'account'),
                    "balance" => data_get($masterWallet, 'balance')
                ]
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 錢包金額異動
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addOrDeposit(Request $request)
    {
        $serialNo = $request->input('transactionId');
        $changeType = $request->input('changeType');
        $orders = $request->input('orders');
        $accounts = collect($orders)->pluck('account')->unique()->toArray();

        // 找需要異動的主錢包資料
        $masterWallets = StationWallet::select('id', 'account', 'station', 'status', 'activated_status', 'balance')
            ->whereIn('account', $accounts)
            ->where('station', 'master')
            ->get()
            ->keyBy('account')
            ->toArray();

        // 回傳的資料
        $responseData = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($orders as $order) {
            $account = array_get($order, 'account');
            $ticketId = array_get($order, 'ticketNo', '');
            $amount = floatval(array_get($order, 'amount'));

            /**
             * 尋找交易紀錄 包含 ticket_id
             */
            $record = DB::table('station_wallet_trade_records')
                ->select('SN', 'ticket_id')
                ->where('SN', $serialNo)
                ->where('ticket_id', $ticketId)
                ->first();
            // 如果已經有交易紀錄，歸類到失敗群
            if (!empty($record)) {
                array_push(
                    $responseData['failed'],
                    [
                        'account' => $account,
                        'ticketNo' => $ticketId,
                        'transactionId' => $serialNo,
                        'errorMessage' => '交易紀錄重複'
                    ]
                );
                continue;
            }

            /**
             * 確認主錢包狀態
             */
            $masterWalletStatus = array_get($masterWallets, "{$account}.status");
            $masterWalletActivatedStatus = array_get($masterWallets, "{$account}.activated_status");
            // 如果主錢包非正常狀態，歸類到失敗群
            if ($masterWalletStatus !== 'active' || $masterWalletActivatedStatus !== 'yes') {
                array_push(
                    $responseData['failed'],
                    [
                        'account' => $account,
                        'ticketNo' => $ticketId,
                        'transactionId' => $serialNo,
                        'errorMessage' => '玩家帳戶可能凍結或不存在'
                    ]
                );
                continue;
            }

            try {
                DB::beginTransaction();

                $masterWallet = StationWallet::lockForUpdate()
                    ->find(array_get($masterWallets, "{$account}.id"));

                /**
                 * 檢查餘額不足
                 * 當amount為負值時才需檢查
                 * 餘額不足，歸類到失敗群
                 */
                if ($amount < 0 && $this->checkBalanceNotEnough($masterWallet->balance, $amount)) {
                    array_push(
                        $responseData['failed'],
                        [
                            'account' => $account,
                            'ticketNo' => $ticketId,
                            'transactionId' => $serialNo,
                            'errorMessage' => '玩家帳戶餘額不足'
                        ]
                    );
                    DB::rollBack();
                    continue;
                }

                $masterWallet->balance += $amount;
                $masterWallet->save();

                // 寫入錢包紀錄
                event(
                    new SingleWalletRecordEvent(
                        [
                            'wallet_id' => $masterWallet->id,
                            'amount' => $amount,
                            'remark' => 'Super體育 ' . array_get($this->changeTypes, $changeType),
                            'serial_no' => $serialNo,
                            'ticket_id' => $ticketId,
                        ]
                    )
                );

                DB::commit();

                // 歸入成功群
                array_push(
                    $responseData['success'],
                    [
                        'account' => $account,
                        'balance' => $masterWallet->balance,
                        'ticketNo' => $ticketId,
                        'transactionId' => $serialNo,
                    ]
                );
            } catch (\Exception $exception) {
                DB::rollBack();

                // SQL寫入失敗，歸類到失敗群
                array_push(
                    $responseData['failed'],
                    [
                        'account' => $account,
                        'ticketNo' => $ticketId,
                        'transactionId' => $serialNo,
                        'errorMessage' => 'SQL寫入失敗'
                    ]
                );
                continue;
            }
        }

        // 寫入失敗資料訊息至wallet_trade_fails table 因為體育需要自己保留失敗資料供遊戲方手動重新派彩
        if (!empty($responseData['failed'])) {
            foreach ($responseData['failed'] as $failedData) {
                event(
                    new FailTransactionEvent(
                        [
                            'station' => 'super_sport',
                            'serial_no' => array_get($failedData, 'transactionId'),
                            'account' => array_get($failedData, 'account'),
                            'ticket_no' => array_get($failedData, 'ticketNo'),
                            'error_msg' => array_get($failedData, 'errorMessage'),
                        ]
                    )
                );
            }
        }

        return response()->json(['data' => $responseData], 200);
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
                'master_wallet' => null,
                'status_code' => '901',
                'message' => '會員帳號不存在',
            ];
        }

        if (data_get($masterWallet, 'activated_status') !== 'yes') {
            return [
                'master_wallet' => null,
                'status_code' => '901',
                'message' => '會員帳號未開通',
            ];
        }

        if (data_get($masterWallet, 'status') === 'freezing') {
            return [
                'master_wallet' => null,
                'status_code' => '901',
                'message' => '會員帳號已被锁',
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