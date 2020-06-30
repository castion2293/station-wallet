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

class ForeverEightConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station WM真人遊戲站名稱
     */
    protected $station = 'forever_eight';

    protected $guzzleClient;

    /**
     * QTechConnector constructor.
     */
    public function __construct()
    {
        $this->console = new ConsoleOutput();

        parent::__construct();

        $this->guzzleClient = new Client();

    }

    /**
     * 建立本地錢包對應遊戲站「帳號」因為QT沒有開通錢包的API，所以直接回傳成功訊息
     *
     * @param Wallet $wallet
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function build(Wallet $wallet, array $params = [])
    {
        $requestId = str_random();

        // 訪問 action
        $action = 'createAccount';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                'Loginname' => $wallet->account,
                'Oddtype' => 'A',
                'Cur' => config('station_wallet.stations.forever_eight.build.currency'),
                'NickName' => $wallet->account,
                'SecureToken' => str_random(16),
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
     * @param Wallet $wallet
     * @param array $params
     * @return array
     * @throws \Exception
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
                'Loginname' => $wallet->account,
                'Cur' => config('station_wallet.stations.forever_eight.build.currency'),
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
            $balance = (string)array_get($response, 'response.Data');
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            return $this->responseMerge(
                $response,
                [
                    'balance' => $balance,
                ]
            );
        } catch (\Exception $exception) {
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若「取得餘額API」回傳「查无此帐号,请检查」，則再次戳「建立帳號API」
            if(array_get($exception->response(), 'errorCode') === 'M1147') {
                $this->build($wallet);
            }
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
        $requestId = str_random();
        // 訪問 action
        $action = 'deposit';
        // 取得序號
        $rand = rand(1000000000000,9999999999999);
        // 轉帳類型
        $transferType = 100;
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'Loginname' => $wallet->account,
                'Billno' => config('api_caller.forever_eight.config.client_ID').$rand,
                'Type' => $transferType,
                'Cur' => config('station_wallet.stations.forever_eight.build.currency'),
                'Credit' => $amount,
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

            $transferId = array_get($aResponseFormatData, 'response.Data');

            // 回戳完成交易API
            $transferResponse = $this->completeTransfer($rand, $transferId, $wallet, $amount, $transferType);
            $status = array_get($transferResponse, 'Status');

            if ($status === '1') {
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
        $requestId = str_random();
        // 訪問 action
        $action = 'withdraw';
        // 取得序號
        $rand = rand(1000000000000,9999999999999);
        // 轉帳類型
        $transferType = 200;
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'Loginname' => $wallet->account,
                'Billno' => config('api_caller.forever_eight.config.client_ID').$rand,
                'Type' => $transferType,
                'Cur' => config('station_wallet.stations.forever_eight.build.currency'),
                'Credit' => $amount,
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

            $transferId = array_get($aResponseFormatData, 'response.Data');
            // 回戳完成交易API
            $transferResponse = $this->completeTransfer($rand, $transferId, $wallet, $amount, $transferType);
            $status = array_get($transferResponse, 'Status');

            if ($status === '1') {
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
     * @throws \Exception
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
        $action = 'passport';

        $gameId = array_get($options, 'game_id');

        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'Loginname' => $wallet->account,
                'Cur' => config('station_wallet.stations.forever_eight.build.currency'),
                'Lang' => config('station_wallet.stations.forever_eight.passport.language'),
                'GameId' => $gameId,
                'Oddtype' => 'A',
                'SecureToken' => str_random(16),
                'HomeURL' => 0
            ],
        ];

        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );

            $response = $this->responseMerge(
                $response,
                [
                    'gameUrl' => array_get($response, 'response.Data'),
                ]
            );

            $webUrl = array_get($response, 'response.Data');
            $mobileUrl = array_get($response, 'response.Data');

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
     * @param Wallet $wallet
     * @param $amount
     * @param $rand
     * @param $transferType
     * @return mixed
     * @throws \Exception
     */
    private function completeTransfer($rand, $transferId, $wallet, $amount, $transferType)
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'tcc';
        // 訪問 parameters
        $formParams = [
            'Billno' => config('api_caller.forever_eight.config.client_ID').$rand,
            'TGSno' => $transferId,
            'Loginname' => $wallet->account,
            'Type' => $transferType,
            'Cur' => config('station_wallet.stations.forever_eight.build.currency'),
            'Credit' => $amount,
        ];

        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            $response = ApiCaller::make('forever_eight')->methodAction('post', $action, [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'Billno' => config('api_caller.forever_eight.config.client_ID').$rand,
                'TGSno' => $transferId,
                'Loginname' => $wallet->account,
                'Type' => $transferType,
                'Cur' => config('station_wallet.stations.forever_eight.build.currency'),
                'Credit' => $amount,
            ])->submit();

            $httpCode = array_get($response, 'http_code');
            $arrayData = json_encode(array_get($response, 'response'));
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, '', $amount);

            return array_get($response, 'response');
        } catch (\Exception $exception) {
            $httpCode = $exception->getCode();
            $errorCode = $exception->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $errorCode, $action, $amount);
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