<?php

namespace SuperPlatform\StationWallet\Connectors;

use SuperPlatform\StationWallet\Models\StationWallet as Wallet;

/**
 * 第三方遊戲站帳號錢包連結器
 *
 * Interface
 * @package SuperPlatform\StationWallet\Connectors
 */
interface ConnectorInterface
{
    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     */
    public function build(Wallet $wallet, array $params = []);

    /**
     * 取得本地錢包對應遊戲站帳號「餘額」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     */
    public function balance(Wallet $wallet, array $params = []);

    /**
     * 「增加」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 充值金額
     */
    public function deposit(Wallet $wallet, float $amount);

    /**
     * 「回收」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 扣款金額
     */
    public function withdraw(Wallet $wallet, float $amount);

    /**
     * 調整點數
     *
     * @param Wallet $wallet 錢包
     * @param float $finalBalance 經過異動點數後，最後的 balance 餘額應為多少
     * @param array $params 參照表參數
     */
    public function adjust(Wallet $wallet, float $finalBalance, array $params = []);

    /**
     * 透過錢包 ID 取得夾心連結
     *
     * @param string $walletId
     * @return \SuperPlatform\StationWallet\Models\StationLoginRecord
     */
    public function play(string $walletId);

    /**
     * 向遊戲站端請求遊玩連結
     *
     * @param Wallet $wallet 錢包
     * @param array $options 參照表參數
     */
    public function passport(Wallet $wallet, array $options = []);
}