<?php

namespace SuperPlatform\StationWallet\Connectors;

use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class SlotFactoryConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 人人棋牌遊戲站名稱
     */
    protected $station = 'slot_factory';

    /**
     * 上層代理帳號
     *
     * @var \Illuminate\Config\Repository|mixed
     */
    protected $agentId = '';

    /**
     * SlotFactoryConnector constructor.
     */
    public function __construct()
    {
        $this->console = new ConsoleOutput();

        parent::__construct();

        $this->agentId = config('api_caller.slot_factory.config.customer_name');
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」因為SF電子沒有開通錢包的API，所以直接回傳成功訊息
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     */
    public function build(Wallet $wallet, array $params = [])
    {
        return $this->responseMerge([], [
            'account' => $wallet->account
        ]);
    }

    /**
     * 取得本地錢包對應遊戲站帳號「餘額」因為SF電子沒有同步錢包的API，所以直接回傳成功訊息
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     */
    public function balance(Wallet $wallet, array $params = [])
    {
        return $this->responseMerge([],
            [
                'balance' => $wallet->balance,
            ]
        );
    }

    /**
     * 「增加」本地錢包對應遊戲站帳號「點數」因為SF電子沒有充值錢包的API，所以直接回傳成功訊息
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 充值金額
     */
    public function deposit(Wallet $wallet, float $amount)
    {
        return $this->responseMerge([],
            [
                'balance' => $wallet->balance,
            ]
        );
    }

    /**
     * 「回收」本地錢包對應遊戲站帳號「點數」因為SF電子沒有回收錢包的API，所以直接回傳成功訊息
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 扣款金額
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        return $this->responseMerge([],
            [
                'balance' => $wallet->balance,
            ]
        );
    }

    /**
     * 調整點數
     *
     * @param Wallet $wallet 錢包
     * @param float $finalBalance 經過異動點數後，最後的 balance 餘額應為多少
     * @param array $params 參照表參數
     */
    public function adjust(Wallet $wallet, float $finalBalance, array $params = [])
    {
        $getBalance = $this->balance($wallet);
        $balance = array_get($getBalance, 'balance');

        if (number_format($balance, 2, '.', '') === number_format($finalBalance, 2, '.', '')) {
            return $balance;
        }

        /**
         * 應該要異動的點數量
         *
         * balance 餘額大於 $finalBalance 例如：剩餘 1000，$finalBalance 為 600，需「回收 400」
         * balance 餘額小於 $finalBalance 例如：剩餘 1000，$finalBalance 為 2100，需「增加 1100」
         */
        $adjustValue = abs($balance - $finalBalance);
        if ($balance > $finalBalance) {
            return $this->withdraw($wallet, $adjustValue);
        } else {
            return $this->deposit($wallet, $adjustValue);
        }
    }

    /**
     * 透過錢包 ID 取得夾心連結
     *
     * @param string $walletId
     * @return \SuperPlatform\StationWallet\Models\StationLoginRecord
     */
    public function play(string $walletId)
    {
        // 寫入 passport 資料，產生對應的遊戲連結記錄 StationLoginRecord (Model)，返回夾心連結實體
        return StationWallet::generatePlayUrl(StationWallet::getWallet($walletId, $this->station));
    }

    /**
     * 向遊戲站端請求遊玩連結
     *
     * @param Wallet $wallet 錢包
     * @param array $options 參照表參數
     */
    public function passport(Wallet $wallet, array $options = [])
    {
        $apiUrl = config('api_caller.slot_factory.config.api_url');

        $gameId = array_get($options, 'game_id');

        $params = [
            'gn' => $gameId,
            'ln' => $this->agentId,
            'ad' => $wallet->account,
            'at' => md5($gameId . $wallet->account),
            'lc' => config('api_caller.slot_factory.config.language')
        ];

        $params = http_build_query($params);

        $url = "https://{$apiUrl}/launch/?{$params}";

        $webUrl = $url;
        $mobileUrl = $url;

        return $this->responseMerge([], [
            'method' => 'redirect',
            'web_url' => $webUrl,
            'mobile_url' => $mobileUrl,
        ]);
    }
}