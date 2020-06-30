<?php


namespace SuperPlatform\StationWallet\Connectors;


use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class BingoBullConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 賓果牛牛遊戲站名稱
     */
    protected $station = 'bingo_bull';

    /**
     * QTechConnector constructor.
     */
    public function __construct()
    {
        $this->console = new ConsoleOutput();

        parent::__construct();

    }

    /**
     * 建立本地錢包對應遊戲站
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
                'account' => config('api_caller.bingo_bull.config.prefix_code') . $wallet->account,
                'password' => $wallet->password,
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
            if (strpos($logException->getMessage(), '10006') !== false) {
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
                'account' => config('api_caller.bingo_bull.config.prefix_code') . $wallet->account,
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
            $balance = (string)array_get($response, 'response.userPoint');
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
            if(array_get($exception->response(), 'errorCode') === '10010') {
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
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'account' => config('api_caller.bingo_bull.config.prefix_code') . $wallet->account,
                'point' => $amount,
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
            $balance = (string)array_get($response, 'response.userPoint');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
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
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'account' => config('api_caller.bingo_bull.config.prefix_code') . $wallet->account,
                'point' => $amount,
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
            $balance = (string)array_get($response, 'response.userPoint');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
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
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'account' => config('api_caller.bingo_bull.config.prefix_code') . $wallet->account,
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
                    'gameUrl' => array_get($response, 'response.loginKey'),
                ]
            );

            $webUrl = array_get($response, 'response.loginKey');
            $mobileUrl = array_get($response, 'response.loginKey');

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
}