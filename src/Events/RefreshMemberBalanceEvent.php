<?php

namespace SuperPlatform\StationWallet\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\DB;

/**
 *
 *
 * @package SuperPlatform\StationWallet\Events
 */
class RefreshMemberBalanceEvent implements ShouldBroadcastNow
{
    /**
     * @var array
     */
    public $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function broadcastOn()
    {
        // 準備要發送的私人頻道
        $privateChannels = [];

        $userId = array_get($this->params, 'user_id');
        $memberLastLoginDevice = DB::table('users')
            ->select('last_login_device')
            ->where('id', '=', $userId)
            ->first()
            ->last_login_device;

        if ($memberLastLoginDevice == 'mobile') {
            $channelName = "App.User-Mobile-{$userId}";
        } else {
            $channelName = "App.User-Desktop-{$userId}";
        }

        $privateChannels[] = new PrivateChannel($channelName);

        return $privateChannels;
    }

    public function broadcastWith()
    {
        // 先發送餘額，目前前端做法是收到推播直接觸發錢包同步事件，若日後有效能相關的考量，則直接由此餘額做加扣點
        return [
            'balance' => array_get($this->params, 'balance')
        ];
    }
}