<?php

namespace SuperPlatform\StationWallet\Events;

class SingleWalletExceptionEvent
{
    /**
     * @var array
     */
    public $params;

    /**
     * Create a new event instance.
     *
     * @param array $params
     */
    public function __construct(Array $params)
    {
        $this->params = $params;
    }
}