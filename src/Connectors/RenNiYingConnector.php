<?php

namespace SuperPlatform\StationWallet\Connectors;

use Exception;
use Illuminate\Support\Facades\Log;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class RenNiYingConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 任你贏遊戲站名稱
     */
    protected $station = 'ren_ni_ying';

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->console = new ConsoleOutput();



        parent::__construct();
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return mixed
     * @throws Exception
     */
    public function build(Wallet $wallet, array $params = [])
    {
        $requestId = str_random();
        $agentId = config('api_caller.ren_ni_ying.config.agent_id');
        $balk = config('api_caller.ren_ni_ying.config.balk');
        $proportion = config('api_caller.ren_ni_ying.config.proportion');
        $maxProfit = config('api_caller.ren_ni_ying.config.max_profit');
        $keepRebateRate = config('api_caller.ren_ni_ying.config.keep_rebate_rate');

        // 訪問 action
        $action = 'createAccount';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* require */
                'parentAgentUserId' => $agentId,
                'userId' => $wallet->account,
                'nick' => $wallet->account,
                'balk' => $balk,
                'initialCredit' => 0,
                'parentProportion' => $proportion,
                'maxProfit' => $maxProfit,
                'keepRebateRate' =>$keepRebateRate
            ],
        ];
        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
            $httpCode = array_get($response, 'http_code');
            $arrayData = json_encode(array_get($response, 'response'));
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action);
            // 再次檢查確認開通錢包成功
            $balance = array_get($response, 'response.data.user.balance');
            if ($balance != 0) {
                throw new \Exception('遊戲方錢包未開通成功');
            }

            return $this->responseMerge($response, [
                'account' => $wallet->account
            ]);
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));
            $logException = $this->formatException($exception);
            // 判断是否有帳號重複訊息
            if ($logException->getCode() !== 405) {
                return $this->responseMerge([], [
                    'account' => $wallet->account
                ]);
            }
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action);
            throw $this->formatException($exception);
        }

    }

    /**
     * 取得本地錢包對應遊戲站帳號「餘額」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return float
     * @throws Exception
     */
    public function balance(Wallet $wallet, array $params = [])
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'getBalance';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'userId' => $wallet->account,
            ],
        ];

        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            $aResponseFormatData = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
            $httpCode = array_get($aResponseFormatData, 'http_code');
            $arrayData = json_encode(array_get($aResponseFormatData, 'response'));
            $balance = (string)array_get($aResponseFormatData, 'response.data.balance');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            return $this->responseMerge(
                $aResponseFormatData,
                [
                    'balance' => array_get($aResponseFormatData, 'response.data.balance'),
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action);
            throw $this->formatException($exception);
        }

    }

    /**
     * 「增加」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 充值金額
     * @return float
     * @throws Exception
     */
    public function deposit(Wallet $wallet, float $amount)
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'deposit';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'userId' => $wallet->account,
                'amount' => $amount
            ],
        ];

        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            $aResponseFormatData = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
            // 再次檢查確認轉點成功
//        $stationBalance = array_get($aResponseFormatData, 'response.data.balance');
//        $walletBalance = data_get($wallet, 'balance');
//
//        if ($stationBalance != $walletBalance) {
//            throw new \Exception('轉點後，遊戲方與我方點數不符');
//        }
            $httpCode = array_get($aResponseFormatData, 'http_code');
            $arrayData = json_encode(array_get($aResponseFormatData, 'response'));
            $balance = (string)array_get($aResponseFormatData, 'response.data.balance');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
            return $this->responseMerge(
                $aResponseFormatData,
                [
                    'balance' => array_get($aResponseFormatData, 'response.data.balance'),
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);
            throw $this->formatException($exception);
        }
    }

    /**
     * 「回收」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 扣款金額
     * @return float
     * @throws Exception
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        // 任你贏 需要把金額設定為負值 代表出金
        $requestId = str_random();
        // 訪問 action
        $action = 'withdraw';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'userId' => $wallet->account,
                'amount' => -$amount
            ],
        ];

        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            $aResponseFormatData = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
            // 再次檢查確認轉點成功
//        $stationBalance = array_get($aResponseFormatData, 'response.data.balance');
//        $walletBalance = data_get($wallet, 'balance');
//
//        if ($stationBalance != $walletBalance) {
//            throw new \Exception('轉點後，遊戲方與我方點數不符');
//        }
            $httpCode = array_get($aResponseFormatData, 'http_code');
            $arrayData = json_encode(array_get($aResponseFormatData, 'response'));
            $balance = (string)array_get($aResponseFormatData, 'response.data.balance');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
            return $this->responseMerge(
                $aResponseFormatData,
                [
                    'balance' => array_get($aResponseFormatData, 'response.data.balance'),
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);
            throw $this->formatException($exception);
        }

    }

    /**
     * 調整點數
     *
     * @param Wallet $wallet 錢包
     * @param float $finalBalance 經過異動點數後，最後的 balance 餘額應為多少
     * @param array $params 參照表參數
     * @return mixed
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
     * @return mixed
     */
    public function passport(Wallet $wallet, array $options = [])
    {
        $aResponseFormatData = $this->getResponseFormatData(
            'passport',
            [
                'userId' => $wallet->account,
            ]
        );

        $response = $this->responseMerge(
            $aResponseFormatData,
            [
                'gameUrl' => array_get($aResponseFormatData, 'response.data.gameUrl'),
            ]
        );

        $playUrl = 'https://' . array_get($response, 'response.data.gameUrl');

        return $this->responseMerge($response, [
            'method' => 'redirect',
            'web_url' => $playUrl,
            'mobile_url' => $playUrl,
        ]);
    }

    /**
     * @param string $sAction
     * @param array $aFormParams
     * @return array
     * @throws \Exception
     */
    private function getResponseFormatData(string $sAction, array $aFormParams): array
    {
        $aFormParams = [
            'form_params' => $aFormParams,
        ];
        try {
            $aResponseFormatData = ApiPoke::poke(
                $this->station,
                $sAction,
                $aFormParams
            );
        } catch (\Exception $exception) {
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $sAction,
                $aFormParams
            ));
            throw $this->formatException($exception);
        }

        return $aResponseFormatData;
    }

    /**
     * 登出
     *
     * @param Wallet $wallet
     * @param array  $params
     *
     * @return bool
     */
    public function logout(Wallet $wallet, array $params = [])
    {
        try {
            $response = ApiCaller::make($this->station)->methodAction('post', 'logoutPlayer', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'userId' => $wallet->account
            ])->submit();

            $response = array_get($response, 'response');

            if (array_get($response, 'code') == 0) {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                'logoutPlayer',
                []
            ));
            throw $this->formatException($exception);
        }
    }

}
