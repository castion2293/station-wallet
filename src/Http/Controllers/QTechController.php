<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SuperPlatform\StationWallet\Events\FailTransactionEvent;
use SuperPlatform\StationWallet\Events\SingleWalletRecordEvent;
use SuperPlatform\StationWallet\Models\StationWallet;

class QTechController
{
    /**
     * 回戳API session
     *
     * @var string
     */
    protected $apiSession = '';

    /**
     * 回戳API 憑證
     *
     * @var string
     */
    protected $apiKey = '';

    /**
     * 站台使用的幣別
     *
     * @var string
     */
    protected $currency = '';

    public function __construct()
    {
        $this->apiSession = config('api_caller.q_tech.config.wallet_session_id');
        $this->apiKey = config('api_caller.q_tech.config.passkey');
        $this->currency = config('api_caller.q_tech.config.currency');
    }

    /**
     * 驗證 session 並獲取餘額
     *
     * @param Request $request
     * @param string $playerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifySession(Request $request, string $playerId)
    {
        // 檢查回戳API Session
        if (!$this->checkApiToken($request->header('wallet-session', ''))) {
            $responseData = [
                'code' => 'INVALID_TOKEN',
                'message' => '缺失，无效或过期的 玩家(钱包)会话令牌'
            ];

            return response()->json($responseData, 400);
        }

        // 檢查回戳API TOKEN憑證
        if (!$this->checkAiKey($request->header('Pass-Key', ''))) {
            $responseData = [
                'code' => 'LOGIN_FAILED',
                'message' => '给定的密钥不正确'
            ];

            return response()->json($responseData, 401);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($playerId);

        // 如果status code 狀態不是 'OK' 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== 'OK') {
            $responseData = [
                'code' => $statusCode,
                'message' => array_get($wallet, 'message')
            ];

            return response()->json($responseData, 403);
        }

        // 成功回傳訊息
        $responseData = [
            'balance' => array_get($wallet, 'master_wallet.balance'),
            'currency' => $this->currency
        ];

        return response()->json($responseData, 200);
    }

    /**
     * 獲取餘額
     *
     * @param Request $request
     * @param $playerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function balance(Request $request, $playerId)
    {
        // 檢查回戳API TOKEN憑證
        if (!$this->checkAiKey($request->header('Pass-Key', ''))) {
            $responseData = [
                'code' => 'LOGIN_FAILED',
                'message' => '给定的密钥不正确'
            ];

            return response()->json($responseData, 401);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($playerId);

        // 如果status code 狀態不是 'OK' 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== 'OK') {
            $responseData = [
                'code' => $statusCode,
                'message' => array_get($wallet, 'message')
            ];

            return response()->json($responseData, 403);
        }

        // 成功回傳訊息
        $responseData = [
            'balance' => array_get($wallet, 'master_wallet.balance'),
            'currency' => $this->currency
        ];

        return response()->json($responseData, 200);
    }

    /**
     * 加扣點
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function transactions(Request $request)
    {
        // 檢查回戳API Session 型態是DEBIT 才需檢查
        $type = $request->input('txnType');
        if (($type === 'DEBIT') && !$this->checkApiToken($request->header('wallet-session', ''))) {
            $responseData = [
                'code' => 'INVALID_TOKEN',
                'message' => '缺失，无效或过期的 玩家(钱包)会话令牌'
            ];

            return response()->json($responseData, 400);
        }

        // 檢查回戳API TOKEN憑證
        if (!$this->checkAiKey($request->header('Pass-Key', ''))) {
            $responseData = [
                'code' => 'LOGIN_FAILED',
                'message' => '给定的密钥不正确'
            ];

            return response()->json($responseData, 401);
        }

        $account = $request->input('playerId');
        $action = $request->input('txnType');
        // DEBIT 代表扣款 amount 取負數
        $amount = ($action === 'DEBIT') ? -$request->input('amount') : $request->input('amount');
        $serialNo = $request->input('txnId');
        $ticketId = $request->input('roundId');

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 'OK' 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== 'OK') {
            $responseData = [
                'code' => $statusCode,
                'message' => array_get($wallet, 'message')
            ];

            return response()->json($responseData, 403);
        }

        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN', 'remark')
            ->where('SN', $serialNo)
            ->first();

        // 如果已經有交易紀錄 就直接回傳現在餘額
        if (!empty($record)) {
            $responseData = [
                'balance' => array_get($wallet, 'master_wallet.balance'),
                'referenceId' => $request->input('clientRoundId')
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            // 檢查餘額不足 當amount為負值時才需檢查
            if ($amount < 0 && $this->checkBalanceNotEnough($masterWallet->balance, $amount)) {
                $responseData = [
                    'code' => 'INSUFFICIENT_FUNDS',
                    'message' => '餘額不足'
                ];

                return response()->json($responseData, 400);
            }

            $masterWallet->balance += $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => $amount,
                        'remark' => ($action === 'DEBIT') ? 'QT電子 下注' : 'QT電子 派彩',
                        'serial_no' => $serialNo,
                        'ticket_id' => $ticketId
                    ]
                )
            );

            DB::commit();

            // 成功回傳訊息
            $responseData = [
                'balance' => $masterWallet->balance,
                'referenceId' => $request->input('clientRoundId')
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 回滾
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function rollback(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if (!$this->checkAiKey($request->header('Pass-Key', ''))) {
            $responseData = [
                'code' => 'LOGIN_FAILED',
                'message' => '给定的密钥不正确'
            ];

            return response()->json($responseData, 401);
        }

        $account = $request->input('playerId');
        $serialNo = $request->input('txnId');
        $beforeSerialNo = $request->input('betId');
        $amount = $request->input('amount');
        $ticketId = $request->input('roundId');

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 'OK' 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== 'OK') {
            $responseData = [
                'code' => $statusCode,
                'message' => array_get($wallet, 'message')
            ];

            return response()->json($responseData, 403);
        }

        // 尋找交易紀錄
        $records = DB::table('station_wallet_trade_records')
            ->select('SN', 'remark')
            ->where('SN', $beforeSerialNo)
            ->orWhere('SN', $serialNo)
            ->get();

        // 如果沒有下注扣款紀錄 就直接回傳餘額
        if ($records->isEmpty()) {
            $responseData = [
                'balance' => array_get($wallet, 'master_wallet.balance')
            ];

            return response()->json($responseData, 200);
        }

        // 如果已經有下注回滾紀錄 就回傳失敗 拒絕回滾
        $rollbackRecord = $records->where('SN', $serialNo);
        if ($rollbackRecord->isNotEmpty()) {
            $responseData = [
                'balance' => array_get($wallet, 'master_wallet.balance'),
                'referenceId' => $request->input('clientRoundId')
            ];

            return response()->json($responseData, 200);
        }

        // 如果有下注扣款紀錄
        $betRecord = $records->where('SN', $beforeSerialNo);
        if ($betRecord->isNotEmpty()) {
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
                            'remark' => 'QT 回滾',
                            'serial_no' => $serialNo,
                            'ticket_id' => $ticketId,
                        ]
                    )
                );

                DB::commit();

                // 成功回傳訊息
                $responseData = [
                    'balance' => $masterWallet->balance,
                    'referenceId' => $request->input('clientRoundId')
                ];

                return response()->json($responseData, 200);
            } catch (\Exception $exception) {
                DB::rollBack();
                throw $exception;
            }
        }

        // 未知的錯誤 可以寫入 wallet_trade_fails table 待人工查詢
        $errorMsg = [
            'request' => $request->all(),
            'record' => $records->toArray()
        ];

        event(
            new FailTransactionEvent(
                [
                    'station' => 'q_tech',
                    'serial_no' => $serialNo,
                    'account' => $account,
                    'ticket_no' => $ticketId,
                    'error_msg' => json_encode($errorMsg, JSON_UNESCAPED_UNICODE),
                ]
            )
        );

        $responseData = [
            'code' => 'UNKNOWN_ERROR',
            'message' => '意外的錯誤'
        ];

        return response()->json($responseData, 500);
    }

    /**
     * 檢查回戳API TOKEN Session
     *
     * @param string $walletSession
     * @return bool
     */
    private function checkApiToken(string $walletSession)
    {
        return $this->apiSession === $walletSession;
    }

    /**
     * 檢查回戳API TOKEN憑證
     *
     * @param string $apiKey
     * @return bool
     */
    private function checkAiKey(string $apiKey)
    {
        return $this->apiKey === $apiKey;
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
                'status_code' => 'ACCOUNT_BLOCKED',
                'message' => '玩家不存在',
            ];
        }

        if (data_get($masterWallet, 'activated_status') !== 'yes') {
            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => 'ACCOUNT_BLOCKED',
                'message' => '此帳號已被停用',
            ];
        }

        if (data_get($masterWallet, 'status') === 'freezing') {
            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => 'ACCOUNT_BLOCKED',
                'message' => '此帳號已被停用',
            ];
        }

        return [
            'master_wallet' => optional($masterWallet)->toArray(),
            'status_code' => 'OK',
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