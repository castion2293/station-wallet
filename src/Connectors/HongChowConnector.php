<?php
//
//namespace SuperPlatform\StationWallet\Connectors;
//
//use SuperPlatform\ApiCaller\Facades\ApiPoke;
//use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
//use SuperPlatform\StationWallet\Facades\StationWallet;
//use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
//use Symfony\Component\Console\Output\ConsoleOutput;
//
//class HongChowConnector extends Connector
//{
//    const TRANSFER_TYPE_IN = 1;
//    const TRANSFER_TYPE_OUT = 2;
//
//    protected $console;
//    protected $station = 'hong_chow';
//
//    public function __construct()
//    {
//        $this->console = new ConsoleOutput();
//
//        parent::__construct();
//    }
//
//    /**
//     * 建立本地錢包對應遊戲站「帳號」
//     * 登入會自動註冊
//     * @param Wallet $wallet 錢包
//     * @param array $params 參照表參數
//     * @return array
//     * @throws \Exception
//     */
//    public function build(Wallet $wallet, array $params = []): array
//    {
//        $aResponseFormatData = $this->getResponseFormatData(
//            'createAccount',
//            [
//                'account' => $wallet->account,
//            ]
//        );
//
//        $data = array_get($aResponseFormatData, 'response');
//
//        return $this->responseMerge(
//            $aResponseFormatData,
//            [
//                'method' => 'post',
//                'web_url' => array_get($data, 'pc_url'),
//                'mobile_url' => array_get($data, 'h5_url'),
//                'params' => [
//                    'token' => array_get($data, 'token'),
//                ]
//            ]
//        );
//    }
//
//    /**
//     * 取得本地錢包對應遊戲站帳號「餘額」
//     * @param Wallet $wallet 錢包
//     * @param array $params 參照表參數
//     * @return array
//     * @throws \Exception
//     */
//    public function balance(Wallet $wallet, array $params = []): array
//    {
//        $aResponseFormatData = $this->getResponseFormatData(
//            'getBalance',
//            [
//                'account' => $wallet->account,
//            ]
//        );
//
//        return $this->responseMerge(
//            $aResponseFormatData,
//            [
//                'balance' => array_get($aResponseFormatData, 'response.data.balance'),
//            ]
//        );
//    }
//
//    /**
//     * 「增加」本地錢包對應遊戲站帳號「點數」
//     * @param Wallet $wallet 錢包
//     * @param float $amount 充值金額
//     * @return array
//     * @throws \Exception
//     */
//    public function deposit(Wallet $wallet, float $amount): array
//    {
//        $aResponseFormatData = $this->getResponseFormatData(
//            'deposit',
//            [
//                'account' => $wallet->account,
//                'point' => $amount,
//                'type' => self::TRANSFER_TYPE_IN,
//            ]
//        );
//
//        return $this->responseMerge(
//            $aResponseFormatData,
//            [
//                'balance' => array_get($aResponseFormatData, 'response.data.currentMoney'),
//            ]
//        );
//    }
//
//    /**
//     * 「回收」本地錢包對應遊戲站帳號「點數」
//     * @param Wallet $wallet 錢包
//     * @param float $amount 扣款金額
//     * @return array
//     * @throws \Exception
//     */
//    public function withdraw(Wallet $wallet, float $amount): array
//    {
//        $aResponseFormatData = $this->getResponseFormatData(
//            'withdraw',
//            [
//                'account' => $wallet->account,
//                'point' => $amount,
//                'type' => self::TRANSFER_TYPE_OUT,
//            ]
//        );
//
//        return $this->responseMerge(
//            $aResponseFormatData,
//            [
//                'balance' => array_get($aResponseFormatData, 'response.data.currentMoney'),
//            ]
//        );
//    }
//
//    /**
//     * 調整點數，決定動作，與要「增加」或「回收」的點數量
//     *
//     * @param Wallet $wallet 錢包
//     * @param float $finalBalance 經過異動點數後，最後的 balance 餘額應為多少
//     * @param array $params 參照表參數
//     * @return array|float|mixed
//     * @throws \Exception
//     */
//    public function adjust(Wallet $wallet, float $finalBalance, array $params = [])
//    {
//        $getBalance = $this->balance($wallet, ['password' => $wallet->password]);
//        $balance = array_get($getBalance, 'balance');
//
//        if (number_format($balance, 2, '.', '') === number_format($finalBalance, 2, '.', '')) {
//            return $balance;
//        }
//
//        /**
//         * 應該要異動的點數量
//         *
//         * balance 餘額大於 $finalBalance 例如：剩餘 1000，$finalBalance 為 600，需「回收 400」
//         * balance 餘額小於 $finalBalance 例如：剩餘 1000，$finalBalance 為 2100，需「增加 1100」
//         */
//        $adjustValue = abs($balance - $finalBalance);
//        if ($balance > $finalBalance) {
//            return $this->withdraw($wallet, $adjustValue);
//        } else {
//            return $this->deposit($wallet, $adjustValue);
//        }
//    }
//
//    /**
//     * 透過錢包 ID 取得夾心連結
//     *
//     * @param string $walletId
//     * @return \SuperPlatform\StationWallet\Models\StationLoginRecord
//     */
//    public function play(string $walletId)
//    {
//        // 寫入 passport 資料，產生對應的遊戲連結記錄 StationLoginRecord (Model)，返回夾心連結實體
//        return StationWallet::generatePlayUrl(StationWallet::getWallet($walletId, $this->station));
//    }
//
//    /**
//     * 向遊戲站端請求遊玩連結
//     * @param Wallet $wallet 錢包
//     * @param array $options 參照表參數
//     * @return array
//     * @throws \Exception
//     */
//    public function passport(Wallet $wallet, array $options = []): array
//    {
//        $aResponseFormatData = $this->getResponseFormatData(
//            'passport',
//            [
//                'account' => $wallet->account,
//            ]
//        );
//
//        $sUrlParams = '?' .
//            http_build_query([
//                'token' => array_get($aResponseFormatData, 'response.data.token'),
//                'lang' => 'zh-CN',
//            ]);
//
//        return $this->responseMerge(
//            $aResponseFormatData,
//            [
//                'method' => 'redirect',
//                'web_url' => array_get($aResponseFormatData, 'response.data.pc_url') . $sUrlParams,
//                'mobile_url' => array_get($aResponseFormatData, 'response.data.h5_url') . $sUrlParams,
//                'params' => [],
//            ]
//        );
//    }
//
//    /**
//     * @param string $sAction
//     * @param array $aFormParams
//     * @return array
//     * @throws \Exception
//     */
//    private function getResponseFormatData(string $sAction, array $aFormParams): array
//    {
//        $aFormParams = [
//            'form_params' => $aFormParams,
//        ];
//
//        try {
//            $aResponseFormatData = ApiPoke::poke(
//                $this->station,
//                $sAction,
//                $aFormParams
//            );
//        } catch (\Exception $exception) {
//            event(new ConnectorExceptionOccurred(
//                $exception,
//                $this->station,
//                $sAction,
//                $aFormParams
//            ));
//            throw $exception;
//        }
//
//        return $aResponseFormatData;
//    }
//}