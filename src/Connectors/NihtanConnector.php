<?php

namespace SuperPlatform\StationWallet\Connectors;

use Illuminate\Support\Facades\Log;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class NihtanConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 歐博遊戲站名稱
     */
    protected $station;

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->station = 'nihtan';
        $this->console = new ConsoleOutput();

        parent::__construct();
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return array|mixed
     * @throws \Exception
     */
    public function build(Wallet $wallet, array $params = [])
    {
        return true;
    }

    /**
     * 取得本地錢包對應遊戲站帳號「餘額」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return array|float
     * @throws \Exception
     */
    public function balance(Wallet $wallet, array $params = [])
    {
        try {
            $response = ApiPoke::poke(
                $this->station,
                'getBalance',
                [
                    'form_params' => [
                        /* required */
                        'user_id' => $params['user_id'],
                        'user_name' => $params['user_name'],
                    ],
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }
        $balance = array_get($response, 'response')[0];
        Log::channel('member-wallet-api')->info("會員錢包帳號： {$wallet->account} 遊戲館：{$this->station} API回傳金額：{$balance}" );

        return $this->responseMerge($response, [
            'balance' => $balance
        ]);
    }

    /**
     * 「增加」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 充值金額
     * @param array $params 參數
     * @return array|float
     * @throws \Exception
     */
    public function deposit(Wallet $wallet, float $amount, array $params = [])
    {
        try {
            $response = ApiPoke::poke(
                $this->station,
                'deposit',
                [
                    'form_params' => [
                        'user_id' => $params['user_id'],
                        'user_name' => $params['user_name'],
                        'user_ip' => $params['user_ip'],
                        'amount' => $amount
                    ],
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }
        $balance = $this->balance($wallet, ['user_id' => $params['user_id'], 'user_name' => $params['user_name']])['balance'];
        Log::channel('member-wallet-api')->info("會員錢包帳號： {$wallet->account} 遊戲館：{$this->station} 轉入金額：{$amount}  API回傳金額：{$balance}" );

        return $this->responseMerge($response, [
            'balance' => $balance
        ]);
    }

    /**
     * 「回收」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 扣款金額
     * @param array $params 參數
     * @return array|float
     * @throws \Exception
     */
    public function withdraw(Wallet $wallet, float $amount, array $params = [])
    {
        try {
            $response = ApiPoke::poke(
                $this->station,
                'withdraw',
                [
                    'form_params' => [
                        'user_id' => $params['user_id'],
                        'user_name' => $params['user_name'],
                        'user_ip' => $params['user_ip'],
                        'amount' => $amount
                    ],
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }
        $balance = $this->balance($wallet, ['user_id' => $params['user_id'], 'user_name' => $params['user_name']])['balance'];
        Log::channel('member-wallet-api')->info("會員錢包帳號： {$wallet->account} 遊戲館：{$this->station} 回收金額：{$amount}  API回傳金額：{$balance}" );

        return $this->responseMerge($response, [
            'balance' => $balance
        ]);
    }

    /**
     * 調整點數，決定動作，與要「增加」或「回收」的點數量
     *
     * @param Wallet $wallet 錢包
     * @param float $finalBalance 經過異動點數後，最後的 balance 餘額應為多少
     * @param array $params 參照表參數
     * @return array|float|mixed
     * @throws \Exception
     */
    public function adjust(Wallet $wallet, float $finalBalance, array $params = [])
    {
        $getBalance = $this->balance($wallet, ['user_id' => $params['user_id'], 'user_name' => $params['user_name']]);
        $balance = array_get($getBalance, 'balance');

        if (number_format($balance, 2, '.', '') === number_format($finalBalance, 2, '.', '')) {
            return $balance;
        }

        /**
         * 應該要異動的點數量
         *
         * balance 餘額大於 $finalBalance 例如：剩餘 1000，$finalBalance 為 600，需「回收 400」
         * balance 餘額小於 $finalBalance 例如：剩餘 1000，$finalBalance 為 2100，需「增加 1100」
         */
        $adjustValue = abs($balance - $finalBalance);
        if ($balance > $finalBalance) {
            return $this->withdraw($wallet, $adjustValue, $params);
        } else {
            return $this->deposit($wallet, $adjustValue, $params);
        }
    }

    /**
     * 透過錢包 ID 取得夾心連結
     *
     * @param string $walletId
     * @return LoginRecord
     */
    public function play(string $walletId)
    {

    }

    /**
     * 取得進入遊戲站的通行證資訊
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return array|mixed
     * @throws \Exception
     */
    public function passport(Wallet $wallet, array $params = [])
    {
        try {
            $response = ApiPoke::poke(
                $this->station,
                'passport',
                [
                    'form_params' => [
                        'user_id' => $params['user_id'],
                        'user_name' => $params['user_name'],
                        'user_ip' => $params['user_ip'],
                        'currency' => $params['currency']
                    ],
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }

        return $this->responseMerge($response, [
            'params' => [
                'token' => array_get($response, 'response.token'),
                'mobile' => 1,
                'html5' => 1
            ],
            'method' => 'POST',
            'web_url' => 'https://session.nihtanv2.com',
            'mobile_url' => 'https://session.nihtanv2.com'
        ]);
    }
}