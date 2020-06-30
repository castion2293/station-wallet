<?php

namespace SuperPlatform\StationWallet\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class StationWalletConnector
 * @package SuperPlatform\StationWallet\Facades
 *
 * @see \SuperPlatform\StationWallet\StationWalletConnector
 */
class StationWalletConnector extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        // 回傳 alias 的名稱
        return 'station_wallet_connector';
    }
}