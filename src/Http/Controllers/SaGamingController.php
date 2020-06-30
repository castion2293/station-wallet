<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;
use SuperPlatform\StationWallet\Events\SingleWalletRecordEvent;
use SuperPlatform\StationWallet\Models\StationWallet;

class SaGamingController
{
    /**
     * Encrypt 解密鍵
     *
     * @var string
     */
    protected $decryptKey = '';

    public function __construct()
    {
        $this->decryptKey = config("api_caller.sa_gaming.config.encrypt_key");
    }

    /**
     * 獲取玩家餘額
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function getUserBalance(Request $request)
    {
        // 解密流程
        $data = $this->decrypt($request->getContent());

        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'username'));

        // 如果status code 狀態不是 0 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== 0) {
            // 組XML回傳格式
            $content = $this->toXml($data, $statusCode, array_get($wallet, 'master_wallet.balance', 0));

            return response($content)->header('Content-Type', 'text/xml');
        }

        // 成功回傳訊息
        // 組XML回傳格式
        $content = $this->toXml($data, $statusCode, array_get($wallet, 'master_wallet.balance'));

        return response($content)->header('Content-Type', 'text/xml');
    }

    /**
     * 下注
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     * @throws \Exception
     */
    public function PlaceBet(Request $request)
    {
        // 解密流程
        $data = $this->decrypt($request->getContent());

        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'username'));

        // 如果status code 狀態不是 0 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== 0) {
            // 組XML回傳格式
            $content = $this->toXml($data, $statusCode, array_get($wallet, 'master_wallet.balance', 0));

            return response($content)->header('Content-Type', 'text/xml');
        }

        $amount = -array_get($data, 'amount'); // 負值表示扣點
        $serialNo = strval(array_get($data, 'txnid'));

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            // 檢查餘額不足
            if ($this->checkBalanceNotEnough($masterWallet->balance, $amount)) {
                // 組XML回傳格式
                $content = $this->toXml($data, 1004, array_get($masterWallet, 'balance', 0));

                return response($content)->header('Content-Type', 'text/xml');
            }

            $masterWallet->balance += $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => $amount,
                        'remark' => '沙龍 下注',
                        'serial_no' => $serialNo
                    ]
                )
            );

            DB::commit();

            // 組XML回傳格式
            $content = $this->toXml($data, 0, array_get($masterWallet, 'balance', 0));

            return response($content)->header('Content-Type', 'text/xml');
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 派彩贏 玩家帳戶補點
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     * @throws \Exception
     */
    public function PlayerWin(Request $request)
    {
        // 解密流程
        $data = $this->decrypt($request->getContent());

        $amount = array_get($data, 'amount');
        $serialNo = array_get($data, 'txnid');

        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'username'));

        // 如果status code 狀態不是 0 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== 0) {
            // 組XML回傳格式
            $content = $this->toXml($data, $statusCode, array_get($wallet, 'master_wallet.balance', 0));

            return response($content)->header('Content-Type', 'text/xml');
        }

        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN')
            ->where('SN', $serialNo)
            ->first();

        // 如果已經有交易紀錄，直接回傳目前餘額，避免重複上分
        if (!empty($record)) {
            // 組XML回傳格式
            $content = $this->toXml($data, 0, array_get($wallet, 'master_wallet.balance', 0));

            return response($content)->header('Content-Type', 'text/xml');
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
                        'remark' => '沙龍 派彩',
                        'serial_no' => $serialNo
                    ]
                )
            );

            DB::commit();

            // 組XML回傳格式
            $content = $this->toXml($data, 0, array_get($masterWallet, 'balance', 0));

            return response($content)->header('Content-Type', 'text/xml');
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 派彩輸 不需要補點
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function PlayerLost(Request $request)
    {
        // 解密流程
        $data = $this->decrypt($request->getContent());

        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'username'));

        // 如果status code 狀態不是 0 就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== 0) {
            // 組XML回傳格式
            $content = $this->toXml($data, $statusCode, array_get($wallet, 'master_wallet.balance', 0));

            return response($content)->header('Content-Type', 'text/xml');
        }

        // 成功回傳訊息
        // 組XML回傳格式
        $content = $this->toXml($data, $statusCode, array_get($wallet, 'master_wallet.balance'));

        return response($content)->header('Content-Type', 'text/xml');
    }

    /**
     * 取消下注
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     * @throws \Exception
     */
    public function PlaceBetCancel(Request $request)
    {
        // 解密流程
        $data = $this->decrypt($request->getContent());

        $amount = array_get($data, 'amount');
        $originalSerialNo = array_get($data, 'txn_reverse_id');
        $serialNo = array_get($data, 'txnid');

        // 找玩家主錢包
        $wallet = $this->findMasterWallet(array_get($data, 'username'));

        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN')
            ->where('SN', $originalSerialNo)
            ->first();

        // 如果沒有紀錄，表示未扣點，直接回傳目前餘額
        if (empty($record)) {
            // 組XML回傳格式
            $content = $this->toXml($data, 0, array_get($wallet, 'master_wallet.balance', 0));

            return response($content)->header('Content-Type', 'text/xml');
        }

        // 如果已經有紀錄，表示已扣點，玩家帳戶需補錢
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
                        'remark' => '沙龍 取消下注',
                        'serial_no' => $serialNo
                    ]
                )
            );

            DB::commit();

            // 組XML回傳格式
            $content = $this->toXml($data, 0, array_get($masterWallet, 'balance', 0));

            return response($content)->header('Content-Type', 'text/xml');
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 解密流程
     *
     * @param string $content
     * @return array
     */
    private function decrypt(string $content): array
    {
        $str = urldecode($content);
        $rawData = openssl_decrypt(base64_decode($str), 'DES-CBC', $this->decryptKey, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $this->decryptKey);
        parse_str(rtrim($rawData, "\x01..\x1F"), $data);

        return $data;
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
                'status_code' => 1000,
                'message' => '会员帐号不存在',
            ];
        }

        if (data_get($masterWallet, 'activated_status') !== 'yes') {
            return [
                'master_wallet' => null,
                'status_code' => 1003,
                'message' => '会员帐号已被锁',
            ];
        }

        if (data_get($masterWallet, 'status') === 'freezing') {
            return [
                'master_wallet' => null,
                'status_code' => 1003,
                'message' => '会员帐号已被锁',
            ];
        }

        return [
            'master_wallet' => optional($masterWallet)->toArray(),
            'status_code' => 0,
            'message' => '成功',
        ];
    }

    /**
     * 組XML回傳格式
     *
     * @param array $data
     * @param int $errorCode
     * @param string $balance
     * @return mixed
     */
    private function toXml(array $data, int $errorCode, $balance = 0)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><RequestResponse></RequestResponse>');
        $xml->addChild('username', array_get($data, 'username'));
        $xml->addChild('currency', array_get($data, 'currency'));
        $xml->addChild('amount', $balance);
        $xml->addChild('error', $errorCode);

        return $xml->asXML();
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