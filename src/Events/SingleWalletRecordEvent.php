<?php

namespace SuperPlatform\StationWallet\Events;

use Illuminate\Support\Facades\Log;

/**
 * Class SlotFactoryWalletSyncEvent
 *
 * 當單一錢包遊戲每次spin後，觸發此事件寫入錢包紀錄
 *
 * @package SuperPlatform\StationWallet\Events
 */
class SingleWalletRecordEvent
{
    /**
     * 同步錢包所需要的參數
     * @var array
     */
    public $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }
}