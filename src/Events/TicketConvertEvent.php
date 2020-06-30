<?php

namespace SuperPlatform\StationWallet\Events;

/**
 * Class SlotFactoryWalletSyncEvent
 *
 * 當單一錢包遊戲注單回戳，處方此事件轉換注單
 *
 * @package SuperPlatform\StationWallet\Events
 */
class TicketConvertEvent
{
    /**
     * 注單參數
     * @var array
     */
    public $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }
}