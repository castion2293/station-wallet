<?php

namespace SuperPlatform\StationWallet\Exceptions;

use Exception;

class ConnectorClassNotExistsException extends Exception
{
    /**
     * ConnectorClassNotExistsException constructor.
     * @param string $str
     */

    public function __construct(string $str)
    {
        parent::__construct("The connector '{$str}' does not exist.");
    }

}