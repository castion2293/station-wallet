<?php

namespace SuperPlatform\StationWallet\Exceptions;

use Exception;

class BalanceNotEnoughException extends Exception
{
    /**
     * BalanceNotEnoughException constructor.
     * @param $wallet
     */
    public function __construct()
    {
        parent::__construct("錢包餘額不足");
    }
}