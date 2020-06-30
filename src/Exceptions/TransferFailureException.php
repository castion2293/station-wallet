<?php

namespace SuperPlatform\StationWallet\Exceptions;

use Exception;

class TransferFailureException extends Exception
{
    protected $info;

    /**
     * TransferFailureException constructor.
     * @param $info
     */
    public function __construct($info)
    {
        $this->info = $info;

        parent::__construct("Transfer Error: " . print_r($this->info, true));
    }

    public function getInfo()
    {
        return $this->info;
    }
}