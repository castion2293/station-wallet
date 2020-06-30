<?php

namespace SuperPlatform\StationWallet\Commands;

use Illuminate\Console\Command;
use SuperPlatform\StationWallet\Jobs\SyncOneWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Queue;
use SuperPlatform\StationWallet\Facades\StationWalletConnector;

class WalletSynchronizePoint extends Command
{
    // 命令名稱
    protected $signature = 'wallet:synchronize-point {station}';

    // 說明文字
    protected $description = '同步指定遊戲站的錢包金額 { station : 欲同步遊戲站名稱 }';

    public function __construct()
    {
        parent::__construct();
    }

    // Console 執行的程式
    public function handle()
    {
        $arguments = $this->arguments();
        $station = $arguments['station'];

        /* 尋找該站所有已開通錢包 */
        $wallets = Wallet::where('station', '=', $station)
            ->where('activated_status', '=', 'yes')
            ->get();
        if(!in_array($station, array_keys( config("api_caller") ))){
            $this->error("沒有找到對應的遊戲站！請確認輸入遊戲站是否正確。");
            return;
        }

        /* 建立連結器 */
        $connector =  StationWalletConnector::make($station);

        if(is_null($connector)){
            $this->error("沒有找到對應的連接器！請確認輸入遊戲站是否正確。");
            return;
        }

        /* 同步餘額 */
        /* 將所有該站已開通錢包狀態改為"同步中" */
        Wallet::where('station', '=', $station)
            ->where('activated_status', '=', 'yes')
            ->update(['sync_status' => 'lock']);

        /* 加入佇列執行 */
        $wallets->each(function($wallet) use ($connector) {
            $params = [];

            if ($wallet->station === 'nihtan') {
                $params['user_id'] = $wallet->user()->get()->first()->id;
                $params['user_name'] = $wallet->user()->get()->first()->username;
            }

            dispatch(new SyncOneWallet($wallet, $params));
        });

    }
}