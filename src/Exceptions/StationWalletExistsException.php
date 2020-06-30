<?php

namespace SuperPlatform\StationWallet\Exceptions;

use Exception;
use Illuminate\Support\Collection;
use SuperPlatform\StationWallet\Models\StationWallet;

class StationWalletExistsException extends Exception
{
    /**
     * @var StationWallet
     */
    private $wallets;

    /**
     * WalletExistsException constructor.
     * @param array $wallet
     */
    public function __construct(array $wallets)
    {
        $this->wallets = $wallets;
        parent::__construct("The station wallet already exists");
    }

    /**
     * 當拋出 StationWalletExistsException 例外時，提供取得遊戲站錢包實體方法
     * @return collection
     */
    public function getStationWallet()
    {
        return collect($this->wallets);
    }
}