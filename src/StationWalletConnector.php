<?php

namespace SuperPlatform\StationWallet;

use SuperPlatform\StationWallet\Exceptions\ConnectorClassNotExistsException;

/**
 * 遊戲站錢包連結器類別，提供 make 創建實體
 */
class StationWalletConnector
{
    /**
     * @param $connector
     * @return mixed
     * @throws ConnectorClassNotExistsException
     */
    public function make($connector)
    {
        $connectorName = ucfirst(camel_case($connector)) . 'Connector';
        $connectorClassName = 'SuperPlatform\\StationWallet\\Connectors\\' . $connectorName;

        if(!class_exists($connectorClassName)) {
            throw new ConnectorClassNotExistsException($connectorClassName);
        }

        return app()->make($connectorClassName);
    }
}