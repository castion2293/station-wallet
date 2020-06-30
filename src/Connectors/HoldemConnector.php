<?php

namespace SuperPlatform\StationWallet\Connectors;

use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationLoginRecord as LoginRecord;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * 「Holdem」帳號錢包連接器
 *
 * @package SuperPlatform\StationWallet\Connectors
 */
class HoldemConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 德州遊戲站名稱
     */
    protected $station;

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->station = 'holdem';
        $this->console = new ConsoleOutput();

        parent::__construct();
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     * Holdem 無此API
     *
     * @param Wallet $wallet
     * @param array $params
     * @return mixed|void
     */
    public function build(Wallet $wallet, array $params = [])
    {
        // Holdem 無此API
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
                        'host' => $this->config['host'],
                        'account' => $wallet->account,
                    ],
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }

        return $this->responseMerge($response, [
            'balance' => array_get($response['response'], 'points')
        ]);
    }

    /**
     * 「增加」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 充值金額
     * @param array $params 參照表參數
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
                        /* required */
                        'host' => $this->config['host'],
                        'account' => $wallet->account,
                        'point' => $amount,
                    ],
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }

        return $this->responseMerge($response, [
            'balance' => array_get($response['response'], 'points')
        ]);
    }

    /**
     * 「回收」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 扣款金額
     * @param array $params
     * @return array|float
     * @throws \Exception
     */
    public function withdraw(Wallet $wallet, float $amount, array $params = [])
    {
        try {
            if($amount > 0)
                $amount *= (-1);
            $response = ApiPoke::poke(
                $this->station,
                'withdraw',
                [
                    'form_params' => [
                        /* required */
                        'host' => $this->config['host'],
                        'account' => $wallet->account,
                        'point' => $amount,
                    ],
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }

        return $this->responseMerge($response, [
            'balance' => array_get($response['response'], 'points')
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
        $getBalance = $this->balance($wallet, ['host' => $this->config['host']]);
        $balance = array_get($getBalance, 'balance');

        if (number_format($balance, 2, '.', '') === number_format($finalBalance, 2, ',', '')) {
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
        // 寫入 passport 資料，產生對應的遊戲連結記錄 StationLoginRecord (Model)，返回夾心連結實體
        return StationWallet::generatePlayUrl(StationWallet::getWallet($walletId, $this->station));
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
                    'form_params'    => [
                        /* required */
                        'host'     => $this->config['host'],
                        'account'    => $wallet->account,
                        'password'   => $wallet->password,
                    ],
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }

        $data = $response['response'];
        return $this->responseMerge($response, [
            'method' => 'post',
            'web_url' => array_get($data, 'play_url'),
            'mobile_url' => array_get($data, 'mobile_url'),
        ]);
    }
}