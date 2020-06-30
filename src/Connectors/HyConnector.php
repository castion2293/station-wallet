<?php

namespace SuperPlatform\StationWallet\Connectors;

use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationLoginRecord as LoginRecord;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * 「Hy」帳號錢包連接器
 *
 * @package SuperPlatform\StationWallet\Connectors
 */
class HyConnector extends Connector
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
        $this->station = 'hy';
        $this->console = new ConsoleOutput();

        parent::__construct();
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet
     * @param array $params
     * @return mixed|void
     */
    public function build(Wallet $wallet, array $params = [])
    {
        
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

    }
}