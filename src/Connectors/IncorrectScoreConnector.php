<?php

namespace SuperPlatform\StationWallet\Connectors;

use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class IncorrectScoreConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 反波膽遊戲站名稱
     */
    protected $station = 'incorrect_score';

    /**
     * IncorrectScoreConnector constructor.
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
                'agentid' => config('api_caller.incorrect_score.config.agentid', ''),
                'user' => $wallet->account,
                'password' => $wallet->password,
                'username' => $wallet->account,
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
            return $this->responseMerge(
                $response,
                [
                    'account' => $wallet->account
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(
                new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $formParams
                )
            );
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            // 檢查如果帳號已經存在，就直接回傳成功
            if (array_get($logException->getCode(), 'errorCode') === 202) {
                return $this->responseMerge([], [
                    'account' => $wallet->account,
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
                'user' => $wallet->account,
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
            $balance = (string)array_get($aResponseFormatData, 'response.result.balance');
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            return $this->responseMerge(
                $aResponseFormatData,
                [
                    'balance' => array_get($aResponseFormatData, 'response.result.balance'),
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(
                new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $formParams
                )
            );
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若「取得餘額API」回傳「查无此帐号,请检查」，則再次戳「建立帳號API」
            if ($httpCode === 203) {
                $this->build($wallet);
            }
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
     * @throws \Exception
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
                'user' => $wallet->account,
                'money' => $amount,
                'code' => 121,
                'bussId' => $requestId
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
            $data = array_get($aResponseFormatData, 'response.result');
            $afterBalance = (string)array_get($data, 'balance');
            // 回戳完成交易API
            $orderId = array_get($data, 'orderId');
            $transferResponse = $this->getTransferStatus($requestId, $wallet, $amount);
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $afterBalance, $amount);
            if ($orderId = $transferResponse) {
                return $this->responseMerge(
                    $aResponseFormatData,
                    [
                        'balance' => array_get($aResponseFormatData, 'response.result.balance'),
                    ]
                );
            } else {
                $exception = new \Exception('orderId not coincidence');
                // show_exception_message($exception);
                event(
                    new ConnectorExceptionOccurred(
                        $exception,
                        $this->station,
                        $action,
                        $formParams
                    )
                );
                throw $exception;
            }
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(
                new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $formParams
                )
            );
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
     * @throws \Exception
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'withdraw';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'user' => $wallet->account,
                'money' => $amount,
                'code' => 122,
                'bussId' => $requestId
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
            $data = array_get($aResponseFormatData, 'response.result');
            $afterBalance = (string)array_get($data, 'balance');
            // 回戳完成交易API
            $orderId = array_get($data, 'orderId');
            $transferResponse = $this->getTransferStatus($requestId, $wallet, $amount);
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $afterBalance, $amount);
            if ($orderId = $transferResponse) {
                return $this->responseMerge(
                    $aResponseFormatData,
                    [
                        'balance' => array_get($aResponseFormatData, 'response.result.balance'),
                    ]
                );
            } else {
                $exception = new \Exception('orderId not coincidence');
                // show_exception_message($exception);
                event(
                    new ConnectorExceptionOccurred(
                        $exception,
                        $this->station,
                        $action,
                        $formParams
                    )
                );
                throw $exception;
            }
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(
                new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $formParams
                )
            );
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
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function passport(Wallet $wallet, array $options = [], array $params = [])
    {
        // 訪問 action
        $action = 'passport';
        // 訪問 parameters
        $webFormParams = [
            'form_params' => [
                /* required */
                'user' => $wallet->account,
                'password' => $wallet->password,
                'lang' => config('api_caller.incorrect_score.config.lang'),
                'trailmode' => 0,
                'ver' => 7,
            ],
        ];
        // 手機登入遊戲參數
        $mobileFormParams = [
            'form_params' => [
                /* required */
                'user' => $wallet->account,
                'password' => $wallet->password,
                'lang' => config('api_caller.incorrect_score.config.lang'),
                'trailmode' => 0,
                'ver' => 6,
            ],
        ];

        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $webFormParams
            );

            $result = array_get($response, 'response.result');
            $webUrl = array_get($result, 'url');

            $response = ApiPoke::poke(
                $this->station,
                $action,
                $mobileFormParams
            );

            $result = array_get($response, 'response.result');
            $mobileUrl = array_get($result, 'url');

            return $this->responseMerge(
                $response,
                [
                    'method' => 'redirect',
                    'web_url' => $webUrl,
                    'mobile_url' => $mobileUrl,
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(
                new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $webFormParams
                )
            );
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $mobileFormParams
            ));
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            // 若「取得餘額API」回傳「查无此帐号,请检查」，則再次戳「建立帳號API」
            if ($httpCode === 203) {
                $this->build($wallet);
            }
            throw $this->formatException($exception);
        }
    }

    /**
     * 额度转换查询（单一钱包不适用）
     *
     * @param string $requestId
     * @param $wallet
     * @param $amount
     * @return mixed
     * @throws \Exception
     */
    private function getTransferStatus(string $requestId, $wallet, $amount)
    {
        // 訪問 action
        $action = 'GetbussStatus';
        // 訪問 parameters
        $params = [
            'user' => $wallet->account,
            'bussId' => $requestId,
        ];
        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($params), $action);
            $response = ApiCaller::make('incorrect_score')->methodAction(
                'post',
                $action,
                [
                    // 路由參數這邊設定
                ]
            )->params(
                [
                    // 一般參數這邊設定
                    'user' => $wallet->account,
                    'bussId' => $requestId,
                ]
            )->submit();

            $httpCode = array_get($response, 'http_code');
            $arrayData = json_encode(array_get($response, 'response'));
            $balance = array_get($response, 'response.result.balance');
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);

            return array_get($response, 'response.result.orderId');
        } catch (\Exception $exception) {
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);
            // show_exception_message($exception);
            event(
                new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $params
                )
            );
            throw $this->formatException($exception);
        }
    }

    /**
     * 向遊戲站端請求遊玩連結(包網用)
     *
     * @param array $options 參照表參數
     * @return array
     * @throws \Exception
     */
    public function singleStationPassport(array $options = [])
    {
        $device = array_get($options, 'device', 'desktop');
        $lang = array_get($options, 'lang', config('api_caller.incorrect_score.config.lang'));
        $trailMode = array_get($options, 'trail_mode', 1);
        $userName = array_get($options, 'user_name', 'test');
        $password = array_get($options, 'password', 'test');
        $wallet = array_get($options, 'wallet', '');
        $callbackUrl = array_get($options, 'callbackUrl', '');
        $token = array_get($options, 'token', '');
        if (!empty($wallet)) {
            $userName = $wallet->account;
            $password = $wallet->password;
        }

        $ver = 7;
        if ($device == 'mobile') {
            $ver = 6;
        }

        // 訪問 action
        $action = 'passport';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'user' => $userName,
                'password' => $password,
                'lang' => $lang,
                'trailmode' => $trailMode,
                'ver' => $ver,
            ],
        ];

        if (!empty($callbackUrl)) {
            $formParams['form_params']['callbackUrl'] = $callbackUrl;
        }
        if (!empty($token)) {
            $formParams['form_params']['game_token'] = $token;
        }

        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );

            $result = array_get($response, 'response.result');
            $url = array_get($result, 'url');

            return $this->responseMerge(
                $response,
                [
                    'method' => 'redirect',
                    'web_url' => $url,
                    'mobile_url' => $url
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
            // 若「取得餘額API」回傳「查无此帐号,请检查」，則再次戳「建立帳號API」
            if ($httpCode === 203) {
                $this->build($wallet);
            }
            throw $this->formatException($exception);
        }
    }

    public function getGameMoreList(array $options = [])
    {
        $type = array_get($options, 'type', '');
        $date = array_get($options, 'date', '');
        // 訪問 action
        $action = 'getGameMoreList';
        // 訪問 parameters
        $webFormParams = [
            'form_params' => [
                /* required */
                'stype' => $type,
                'sdate' => $date,
            ],
        ];

        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $webFormParams
            );

            $result = array_get($response, 'response.result');

            return $this->responseMerge(
                $response,
                [
                    'game_result' => $result
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(
                new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $webFormParams
                )
            );
            throw $this->formatException($exception);
        }
    }

    public function getGameResults(array $options = [])
    {
        // 訪問 action
        $action = 'getGameResults';
        // 訪問 parameters
        $webFormParams = [
            'form_params' => [
                /* required */
            ],
        ];

        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $webFormParams
            );

            $result = array_get($response, 'response.result');
            $gameResultUrl = array_get($result, 'url');
            return $this->responseMerge(
                $response,
                [
                    'game_result_url' => $gameResultUrl
                ]
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(
                new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $webFormParams
                )
            );
            throw $this->formatException($exception);
        }
    }

    public function logout($options = [])
    {
        $user = array_get($options, 'account');
        // 訪問 action
        $action = 'LogoutGame';
        // 訪問 parameters
        $webFormParams = [
            'form_params' => [
                /* required */
                'user' => $user,
            ],
        ];

        try {
            ApiPoke::poke(
                $this->station,
                $action,
                $webFormParams
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $webFormParams
            ));
           throw $this->formatException($exception);
        }
    }

    /**
     * 取得會員在遊戲端在線狀態，1 為在線中，0 為離線，-1 為遊戲不支援
     *
     * @param Wallet $wallet 錢包
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function getUserOnline(Wallet $wallet, array $options = [])
    {
        try {
            $action = 'GetUserOnline';
            $formParams = [
                'user' => $wallet->account,
            ];
            $response = ApiCaller::make($this->station)->methodAction('post', $action, [])
                ->params($formParams)
                ->submit();
            $result = array_get($response, 'response.result');
            return $this->responseMerge(
                $response,
                [
                    'result' => $result
                ]
            );
        } catch (\Exception $exception) {
            show_exception_message($exception);
            event(
                new ConnectorExceptionOccurred(
                    $exception,
                    $this->station,
                    $action,
                    $formParams
                )
            );
            throw $this->formatException($exception);
        }
    }
}