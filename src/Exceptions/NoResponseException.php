<?php

namespace SuperPlatform\StationWallet\Exceptions;

use Exception;

class NoResponseException extends Exception
{
    /**
     * NoResponseException constructor.
     * @param $str
     */
    public function __construct($str)
    {
        parent::__construct("The connector of station \"'{$str}'\" does not have any response.");
    }
}