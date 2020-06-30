<?php

namespace SuperPlatform\StationWallet\Events;

/**
 * Class FailTransactionEvent
 *
 * 單一錢包 交易失敗，觸發此事件寫入交易失敗紀錄
 *
 * @package SuperPlatform\StationWallet\Events
 */
class FailTransactionEvent
{
    /**
     * 寫入交易失敗的變數內容
     *
     * @var array
     */
    public $params = [];

    public function __construct(array $params)
    {
        $this->params = $params;
    }
}