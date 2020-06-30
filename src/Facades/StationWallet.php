<?php

namespace SuperPlatform\StationWallet\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class StationWallet
 * @package SuperPlatform\StationWallet\Facades
 * @see \SuperPlatform\StationWallet\StationWallet
 * @method static generatePlayUrl($getWallet)
 * @method static getWallet(string $walletId, string $station)
 */
class StationWallet extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        // 回傳 alias 的名稱
        return 'station_wallet';
    }
}