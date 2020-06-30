<?php

namespace SuperPlatform\StationWallet\Connectors;

use Carbon\Carbon;
use GuzzleHttp\Client;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class QTechConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station QT電子遊戲站名稱
     */
    protected $station = 'q_tech';

    /**
     * 幣別
     *
     * @var string
     */
    protected $currency = '';

    /**
     * 國家
     *
     * @var string
     */
    protected $country = '';

    /**
     * 語言
     *
     * @var string
     */
    protected $language = '';

    /**
     * 模式
     *
     * @var string
     */
    protected $mode = '';

    /**
     * 限紅
     *
     * @var string
     */
    protected $betLimitCode = '';

    /**
     * 單錢包使用的錢包驗證碼
     *
     * @var string
     */
    protected $walletSessionId = '';

    protected $guzzleClient;

    /**
     * QTechConnector constructor.
     */
    public function __construct()
    {
        $this->console = new ConsoleOutput();

        parent::__construct();

        $this->currency = config('api_caller.q_tech.config.currency');
        $this->country = config('api_caller.q_tech.config.country');
        $this->language = config('api_caller.q_tech.config.language');
        $this->mode = config('api_caller.q_tech.config.mode');
        $this->betLimitCode = config('api_caller.q_tech.config.bet_limit_code');
        $this->walletSessionId = config('api_caller.q_tech.config.wallet_session_id');
        $this->guzzleClient = new Client();
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」因為QT沒有開通錢包的API，所以直接回傳成功訊息
     *
     * @param Wallet $wallet
     * @param array $params
     * @return array
     */
    public function build(Wallet $wallet, array $params = [])
    {
        return $this->responseMerge([], [
            'account' => $wallet->account
        ]);
    }

    /**
     * 取得本地錢包對應遊戲站帳號「餘額」
     *
     * @param Wallet $wallet
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function balance(Wallet $wallet, array $params = [])
    {
        // 單錢包禁止使用多錢包取餘額API
        if ((env('APP_IS_SINGLE_BALANCE_SITE') === 'yes')) {
            throw new \Exception('單錢包禁止使用balance接口');
        }

        $requestId = str_random();
        // 訪問 action
        $action = 'getBalance';
        // 訪問 parameters
        $formParams = [
            'route_params' => [
                /* required */
                'playerId' => $wallet->account,
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
            $balance = (string)array_get($aResponseFormatData, 'response.amount');
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            return $this->responseMerge(
                $aResponseFormatData,
                [
                    'balance' => $balance,
                ]
            );
        } catch (\Exception $exception) {
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action);
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));
            throw $this->formatException($exception);
        }
    }

    /**
     * 「增加」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet
     * @param float $amount
     * @return array
     * @throws \Exception
     */
    public function deposit(Wallet $wallet, float $amount)
    {
        // 單錢包禁止使用多錢包取充值API
        if ((env('APP_IS_SINGLE_BALANCE_SITE') === 'yes')) {
            throw new \Exception('單錢包禁止使用depoist接口');
        }

        $requestId = str_random();
        // 訪問 action
        $action = 'deposit';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'type' => 'CREDIT',
                'referenceId' => str_random(50),
                'playerId' => $wallet->account,
                'amount' => $amount,
                'currency' => $this->currency
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
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, '', $amount);

            $transferId = array_get($aResponseFormatData, 'response.id');
            // 回戳完成交易API
            $transferResponse = $this->completeTransfer($transferId, $wallet, $amount);
            $status = array_get($transferResponse, 'status');

            if ($status === 'COMPLETED') {
                return $this->responseMerge(
                    $aResponseFormatData,
                    [
                        'balance' => $transferResponse,
                    ]
                );
            } else {
                $exception = new \Exception('transfer not completed');
                // show_exception_message($exception);
                event(new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $formParams
                ));
                throw $exception;
            }

        } catch (\Exception $exception) {
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));

            throw $this->formatException($exception);
        }
    }

    /**
     * 「回收」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet
     * @param float $amount
     * @return array
     * @throws \Exception
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        // 單錢包禁止使用多錢包扣款API
        if ((env('APP_IS_SINGLE_BALANCE_SITE') === 'yes')) {
            throw new \Exception('單錢包禁止使用withdraw接口');
        }

        $requestId = str_random();
        // 訪問 action
        $action = 'withdraw';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'type' => 'DEBIT',
                'referenceId' => str_random(50),
                'playerId' => $wallet->account,
                'amount' => $amount,
                'currency' => $this->currency
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
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, '', $amount);

            $transferId = array_get($aResponseFormatData, 'response.id');
            // 回戳完成交易API
            $transferResponse = $this->completeTransfer($transferId, $wallet, $amount);
            $status = array_get($transferResponse, 'status');

            if ($status === 'COMPLETED') {
                return $this->responseMerge(
                    $aResponseFormatData,
                    [
                        'balance' => $transferResponse,
                    ]
                );
            } else {
                $exception = new \Exception('transfer not completed');
                // show_exception_message($exception);
                event(new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $formParams
                ));
                throw $exception;
            }

        } catch (\Exception $exception) {
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));
            throw $this->formatException($exception);
        }
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
     * @param Wallet $wallet
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function passport(Wallet $wallet, array $options = [])
        {
            // 訪問 action
            $action = 'games/lobby-url';
            // 訪問 parameters
            $formParams = [
                'playerId' => $wallet->account,
                'currency' => $this->currency,
                'country' => $this->country,
                'lang' => $this->language,
                'mode' => $this->mode,
                'device' => 'desktop',
                'betLimitCode' => $this->betLimitCode
            ];
            // 訪問 路由parameters
            $routeParams = [];

            // 指定單一遊戲站轉方式
            $gameId = array_get($options, 'game_id');
            if (!empty($gameId)) {
                // 訪問 action
                $action = 'games/{gameId}/launch-url';
                $routeParams = ['gameId' => $gameId];
            }

            // 單錢包需要增加 walletSessionId 參數
            if (env('APP_IS_SINGLE_BALANCE_SITE') === 'yes') {
                $formParams['walletSessionId'] = $this->walletSessionId;
            }

            try {
                $ResponseFormatData = ApiCaller::make($this->station)->methodAction('post', $action,
                    // 路由參數這邊設定
                    $routeParams
                )->params(
                    // 一般參數這邊設定
                    $formParams
                )->submit();

                $response = $this->responseMerge(
                    $ResponseFormatData,
                    [
                        'gameUrl' => array_get($ResponseFormatData, 'response.url'),
                    ]
                );

                $webUrl = array_get($response, 'gameUrl');
                $mobileUrl = array_get($response, 'gameUrl');

                return $this->responseMerge($response, [
                    'method' => 'redirect',
                    'web_url' => $webUrl,
                    'mobile_url' => $mobileUrl,
                ]);

            } catch (\Exception $exception) {
                // show_exception_message($exception);
                event(new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $formParams
                ));

                throw $this->formatException($exception);
            }
        }

    /**
     * 完成交易API
     *
     * @param string $transferId
     * @param $wallet
     * @param $amount
     * @return mixed
     * @throws \Exception
     */
    private function completeTransfer(string $transferId, $wallet, $amount)
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'complete_transfer';
        // 訪問 parameters
        $routeParams = [
            'transferId' => $transferId
        ];
        $formParams = [
            'status' => 'COMPLETED'
        ];

        $params = array_merge([
            'route_params' => $routeParams,
            'form_params'=> $formParams
        ]);

        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($params), $action);
            $response = ApiCaller::make('q_tech')->methodAction('put', 'fund-transfers/{transferId}/status', [
                // 路由參數這邊設定
                'transferId' => $transferId
            ])->params([
                // 一般參數這邊設定
                'status' => 'COMPLETED'
            ])->submit();

            $httpCode = array_get($response, 'http_code');
            $arrayData = json_encode(array_get($response, 'response'));
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, '', $amount);

            return array_get($response, 'response');
        } catch (\Exception $exception) {
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));
            throw $this->formatException($exception);
        }
    }

    /**
     * 登出會員(因遊戲館未提供登出api, 這裡為發通知到 chat)
     * @param Wallet $wallet
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function logout(Wallet $wallet, array $options = []): bool
    {
        $chatUrl = array_get($options, "chat_url");
        $chatToken = array_get($options, "chat_token");
        $formatLog = PHP_EOL .
            '----------------------------------------------------------------------------------' . PHP_EOL .
            '| 娛樂城會員踢除通知' . PHP_EOL .
            '----------------------------------------------------------------------------------' . PHP_EOL .
            '| APP_ID: ' . array_get($options, 'app_id') . PHP_EOL .
            '| DOMAIN: ' . array_get($options, 'domain') . PHP_EOL .
            '| DATE: ' . Carbon::now()->toDateTimeString() . PHP_EOL .
            '| 觸發事件: ' . array_get($options, 'event_name') . PHP_EOL .
            '| 遊戲館: ' . array_get($options, 'station_name') . PHP_EOL .
            '| Message: ' . "『{$options['username']}/{$wallet->account}』, 觸發剔除事件, 請確認會員是否已登出『" . array_get($options, "station_name") ."』" . PHP_EOL .
            '----------------------------------------------------------------------------------' . PHP_EOL;

        $chatPayload['text'] = $formatLog;
        $chatPayload = json_encode($chatPayload);
        try {
            $response = $this->guzzleClient->request('POST', $chatUrl, [
                'headers' => [
                    'content-type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'token' => $chatToken,
                    'payload' => $chatPayload
                ],
            ]);
            $response = json_decode($response->getBody(), true);
            if (array_get($response, "success")) {
                return true;
            }
            return false;
            // var_dump($response);
        } catch (\Exception $exception) {
            return false;
        }
    }


}