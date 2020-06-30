<?php

namespace SuperPlatform\StationWallet\Events;

use SuperPlatform\StationWallet\Models\StationWallet;

/**
 * Class SuperSportUserLogIn
 *
 * 當有會員登入體育時，觸發此事件
 *
 * @package SuperPlatform\StationWallet\Src\Events
 */
class SuperSportUserLogIn
{
    public $wallet;

    public function __construct(StationWallet $wallet)
    {
        $this->wallet = $wallet;
    }
}
