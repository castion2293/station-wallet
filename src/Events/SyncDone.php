<?php

namespace SuperPlatform\StationWallet\Events;

use SuperPlatform\StationWallet\Models\StationWallet;

/**
 * 錢包同步完成事件
 *
 * @package SuperPlatform\StationWallet\Events
 */
class SyncDone
{
    /**
     * @var StationWallet 欲同步的錢包
     */
    public $wallet;

    /**
     * @var mixed 同步之前的餘額
     */
    public $beforeBalance;

    /**
     * @var mixed 同步之後的餘額
     */
    public $afterBalance;

    /**
     * @var number 變動值
     */
    public $amount;

    /**
     * SyncDone constructor.
     *
     * @param StationWallet $wallet
     * @param mixed $beforeBalance
     * @param mixed $afterBalance
     */
    public function __construct(StationWallet $wallet, $beforeBalance, $afterBalance)
    {
        $this->wallet = $wallet;
        $this->beforeBalance = $beforeBalance;
        $this->afterBalance = $afterBalance;
        $this->amount = $this->afterBalance - $this->beforeBalance;
    }
}
