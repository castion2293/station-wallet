<?php

namespace SuperPlatform\StationWallet\Exceptions;

use Exception;

class WalletNotFoundException extends Exception
{
    /**
     * WalletNotFoundException constructor.
     *
     */
    public function __construct()
    {
        parent::__construct("The wallet not found.");
    }
}