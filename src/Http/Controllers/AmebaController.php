<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SuperPlatform\StationWallet\Events\FailTransactionEvent;
use SuperPlatform\StationWallet\Events\SingleWalletRecordEvent;
use SuperPlatform\StationWallet\Models\StationWallet;

class AmebaController
{
    /**
     * 回戳API憑證
     *
     * @var string
     */
    protected $apiKey = '';

    /**
     * 營運商識別碼
     *
     * @var string
     */
    protected $siteId = '';

    public function __construct()
    {
        $this->apiKey = config('api_caller.ameba.config.secret_key');
        $this->siteId = config('api_caller.ameba.config.site_id');
    }

    public function actionDispatcher(Request $request)
    {
        // JWT Web Token 加密驗證並返回原始資料數據
        $jwtToken = explode(' ', $request->header('Authorization'))[1];
        $payLoadData = $this->getPayloadData($jwtToken);
        $errorCode = array_get($payLoadData, 'error_code');
        if ($errorCode !== 'OK') {
            $responseData = [
                'error_code' => $errorCode,
            ];

            return response()->json($responseData, 200);
        }

        // 根據 action 選擇執行動作
        $callAction = camel_case(array_get($payLoadData, 'data.action'));
        $data = array_get($payLoadData, 'data');

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
    protected function getBalance(array $data): array
    {
        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'account_name'));

        // 如果message 狀態不是 'OK' 就回傳錯誤訊息
        $message = array_get($wallet, 'message');
        if ($message !== 'OK') {
            return [
                'error_code' => $message,
            ];
        }

        // 成功回傳訊息
        return [
            'error_code' => 'OK',
            'balance' => array_get($wallet, 'master_wallet.balance'),
        ];
    }

    /**
     * 下注
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function bet(array $data): array
    {
        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'account_name'));

        // 如果message 狀態不是 'OK' 就回傳錯誤訊息
        $message = array_get($wallet, 'message');
        if ($message !== 'OK') {
            return [
                'error_code' => $message,
            ];
        }

        $amount = -abs(floatval(array_get($data, 'bet_amt'))); // 負值表示扣點
        $serialNo = array_get($data, 'tx_id');
        $ticketId = array_get($data, 'round_id');
        $free = array_get($data, 'free');

        // 如果是 free game 玩家帳戶不需扣點，直接回傳目前餘額
        if ($free) {
            return [
                'error_code' => 'OK',
                'balance' => array_get($wallet, 'master_wallet.balance'),
                'time' => array_get($data, 'time')
            ];
        }

        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN', 'wallet_account', 'trade_amount')
            ->where('SN', $serialNo)
            ->first();

        // 如果已經有交易紀錄，直接回傳失敗
        if (!empty($record)) {
            $hasSameAccount = data_get($record, 'wallet_account') === array_get($data, 'account_name');
            $hasSameAmount = floatval(data_get($record, 'trade_amount')) === abs($amount);

            // 同帳戶 同金額
            if ($hasSameAmount && $hasSameAccount) {
                return [
                    'error_code' => 'AlreadyProcessed',
                    'balance' => array_get($wallet, 'master_wallet.balance'),
                    'time' => array_get($data, 'time')
                ];
            }

            // 不同帳戶 或 不同金額
            return [
                'error_code' => 'TransactionNotMatch',
                'balance' => array_get($wallet, 'master_wallet.balance'),
                'time' => array_get($data, 'time')
            ];
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            // 檢查餘額不足
            if ($this->checkBalanceNotEnough($masterWallet->balance, $amount)) {
                return [
                    'error_code' => 'InsufficientBalance',
                    'balance' => $masterWallet->balance,
                    'time' => array_get($data, 'time')
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
                        'remark' => 'Ameba 下注',
                        'serial_no' => $serialNo,
                        'ticket_id' => $ticketId,
                    ]
                )
            );

            DB::commit();

            return [
                'error_code' => 'OK',
                'balance' => $masterWallet->balance,
                'time' => array_get($data, 'time')
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 下注回滾
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function cancelBet(array $data): array
    {
        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'account_name'));

        // 如果message 狀態不是 'OK' 就回傳錯誤訊息
        $message = array_get($wallet, 'message');
        if ($message !== 'OK') {
            return [
                'error_code' => $message,
            ];
        }

        $amount = floatval(array_get($data, 'bet_amt'));
        $serialNo = array_get($data, 'tx_id');
        $ticketId = array_get($data, 'round_id');
        $free = array_get($data, 'free');

        // 如果是 free game 玩家帳戶不需扣點，直接回傳目前餘額
        if ($free) {
            return [
                'error_code' => 'OK',
                'balance' => array_get($wallet, 'master_wallet.balance'),
                'time' => array_get($data, 'time')
            ];
        }

        // 尋找交易紀錄
        $records = DB::table('station_wallet_trade_records')
            ->select('SN', 'remark', 'wallet_account', 'trade_amount')
            ->where('SN', $serialNo)
            ->get();

        // 如果沒有下注扣款紀錄 就回傳失敗 拒絕回滾
        if ($records->isEmpty()) {
            return [
                'error_code' => 'BetNotFound',
                'balance' => array_get($wallet, 'master_wallet.balance'),
                'time' => array_get($data, 'time')
            ];
        }

        // 如果已經有下注回滾紀錄 就回傳失敗 拒絕回滾
        $refundRecord = $records->filter(function ($record) {
            return  strpos(data_get($record, 'remark'), '回滾');
        })->first();
        if (!empty($refundRecord)) {
            $hasSameAccount = data_get($refundRecord, 'wallet_account') === array_get($data, 'account_name');
            $hasSameAmount = floatval(data_get($refundRecord, 'trade_amount')) === abs($amount);

            // 同帳戶且同金額
            if ($hasSameAmount && $hasSameAccount) {
                return [
                    'error_code' => 'AlreadyProcessed',
                    'balance' => array_get($wallet, 'master_wallet.balance'),
                    'time' => array_get($data, 'time')
                ];
            }

            // 不同帳戶或不同金額
            return [
                'error_code' => 'TransactionNotMatch',
                'balance' => array_get($wallet, 'master_wallet.balance'),
                'time' => array_get($data, 'time')
            ];
        }

        // 如果有下注扣款紀錄
        $betRecord = $records->filter(
            function ($record) {
                return strpos(data_get($record, 'remark'), '下注');
            }
        )->first();
        if (!empty($betRecord)) {
            $hasSameAccount = data_get($betRecord, 'wallet_account') === array_get($data, 'account_name');
            $hasSameAmount = floatval(data_get($betRecord, 'trade_amount')) === abs($amount);

            // 帳戶及金額相同 回傳成功 玩家帳戶補點  新增下注回滾紀錄
            if ($hasSameAccount && $hasSameAmount) {
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
                                'remark' => 'Ameba 下注 回滾',
                                'serial_no' => $serialNo,
                                'ticket_id' => $ticketId,
                            ]
                        )
                    );

                    DB::commit();

                    return [
                        'error_code' => 'OK',
                        'balance' => $masterWallet->balance,
                        'time' => array_get($data, 'time')
                    ];
                } catch (\Exception $exception) {
                    DB::rollBack();
                    throw $exception;
                }
            }

            // 帳戶或金額不同 就回傳失敗 拒絕回滾
            return [
                'error_code' => 'BetNotMatch',
                'balance' => array_get($wallet, 'master_wallet.balance'),
                'time' => array_get($data, 'time')
            ];
        }

        // 未知的錯誤 可以寫入 wallet_trade_fails table 待人工查詢
        $errorMsg = [
            'request' => $data,
            'record' => $records->toArray()
        ];

        event(
            new FailTransactionEvent(
                [
                    'station' => 'ameba',
                    'serial_no' => $serialNo,
                    'account' => array_get($data, 'account_name'),
                    'ticket_no' => $ticketId,
                    'error_msg' => json_encode($errorMsg, JSON_UNESCAPED_UNICODE),
                ]
            )
        );
    }

    /**
     * 派彩
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function payout(array $data): array
    {
        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'account_name'));

        // 如果message 狀態不是 'OK' 就回傳錯誤訊息
        $message = array_get($wallet, 'message');
        if ($message !== 'OK') {
            return [
                'error_code' => $message,
            ];
        }

        $amount = floatval(array_get($data, 'sum_payout_amt'));
        $serialNo = array_get($data, 'tx_id');
        $ticketId = array_get($data, 'round_id');

        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN', 'wallet_account', 'trade_amount')
            ->where('SN', $serialNo)
            ->first();

        // 如果已經有交易紀錄，直接回傳失敗
        if (!empty($record)) {
            $hasSameAccount = data_get($record, 'wallet_account') === array_get($data, 'account_name');
            $hasSameAmount = floatval(data_get($record, 'trade_amount')) === abs($amount);

            // 同帳戶 同金額
            if ($hasSameAmount && $hasSameAccount) {
                return [
                    'error_code' => 'AlreadyProcessed',
                    'balance' => array_get($wallet, 'master_wallet.balance'),
                    'time' => array_get($data, 'time')
                ];
            }

            // 不同帳戶 或 不同金額
            return [
                'error_code' => 'TransactionNotMatch',
                'balance' => array_get($wallet, 'master_wallet.balance'),
                'time' => array_get($data, 'time')
            ];
        }

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
                        'remark' => 'Ameba 派彩',
                        'serial_no' => $serialNo,
                        'ticket_id' => $ticketId,
                    ]
                )
            );

            DB::commit();

            return [
                'error_code' => 'OK',
                'balance' => $masterWallet->balance,
                'time' => array_get($data, 'time')
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
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
                'status_code' => '',
                'message' => 'Player not found',
            ];
        }

        if (data_get($masterWallet, 'activated_status') !== 'yes') {
            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => '',
                'message' => 'Player is invalid',
            ];
        }

        if (data_get($masterWallet, 'status') === 'freezing') {
            return [
                'master_wallet' => optional($masterWallet)->toArray(),
                'status_code' => '',
                'message' => 'Player is for freezing',
            ];
        }

        return [
            'master_wallet' => optional($masterWallet)->toArray(),
            'status_code' => '',
            'message' => 'OK',
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

    /**
     * JWT Web Token 加密驗證並返回原始資料數據
     *
     * @param string $jwtToken
     * @return array
     */
    private function getPayloadData(string $jwtToken): array
    {
        list($header64, $payload64, $sign) = explode('.', $jwtToken);

        $rawSignature = hash_hmac('sha256', "{$header64}.{$payload64}", $this->apiKey, true);

        // 檢查 JWT Web Toke 加密
        if ($this->base64UrlEncode($rawSignature) !== $sign) {
            return [
                'error_code' => 'Wrong JWT Web Token',
                'data' => []
            ];
        }

        $data = json_decode($this->urlsafeB64Decode($payload64), JSON_OBJECT_AS_ARRAY);

        // 檢查 Site Id
        if (strval(array_get($data, 'site_id')) !== $this->siteId) {
            return [
                'error_code' => 'Wrong Site Id',
                'data' => []
            ];
        }

        // 成功回傳
        return [
            'error_code' => 'OK',
            'data' => $data
        ];
    }

    /**
     * @param string $input
     * @return string
     */
    private function urlsafeB64Decode(string $input): string
    {
        $remainder = strlen($input) % 4;

        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * @param string $data
     * @return string
     */
    private function base64UrlEncode(string $data): string
    {
        $urlSafeData = strtr(base64_encode($data), '+/', '-_');

        return rtrim($urlSafeData, '=');
    }
}