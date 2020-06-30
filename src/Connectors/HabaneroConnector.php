<?php


namespace SuperPlatform\StationWallet\Connectors;

use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class HabaneroConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station HB電子遊戲站名稱
     */
    protected $station = 'habanero';

    /**
     * 幣別
     *
     * @var string
     */
    protected $currency = '';

    /**
     * 語言
     *
     * @var string
     */
    protected $language = '';

    /**
     * 轉入點數之最小單位對應表
     *
     */
    protected $deposit_limit_map;

    /**
     * SlotFactoryConnector constructor.
     */
    public function __construct()
    {
        $this->console = new ConsoleOutput();

        parent::__construct();
        $this->currency = config('api_caller.habanero.config.currency');
        $this->language = config('api_caller.habanero.config.language');
        $this->deposit_limit_map = config("api_caller.habanero.deposit_limit");
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
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
                'PlayerHostAddress' => request()->getClientIp(),
                'UserAgent' => config('api_caller.habanero.config.agent_account'),
                'KeepExistingToken' => true,
                'Username' => $wallet->account,
                'Password' => $wallet->password,
                'CurrencyCode' => $this->currency,
            ],
        ];
        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
            $token = array_get($response, 'response.token');
            $httpCode = array_get($response, 'http_code');
            $arrayData = json_encode(array_get($response, 'response'));
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action);
            return $this->responseMerge($response, [
                'account' => $wallet->account,
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
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
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
                'Username' => $wallet->account,
                'Password' => $wallet->password,
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
            $balance = (string)array_get($response, 'response.RealBalance');
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            return $this->responseMerge(
                $response,
                [
                    'balance' => number_format(currency_multiply_transfer(data_get($wallet, "station"), $balance), 4, ".", ""),
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
     * @param Wallet $wallet 錢包
     * @param float $amount 充值金額
     * @throws \Exception
     */
    public function deposit(Wallet $wallet, float $amount)
    {
        // 由於HB電子各幣別有對應的轉換比例 例如:娛樂城 1000VD = HB電子遊戲內 1VD
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'deposit';
            // 將欲轉入之點數轉換成 遊戲館對應比例
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            // 訪問 parameters
            $formParams = [
                'form_params' => [
                    /* required */
                    'Username' => $wallet->account,
                    'Password' => $wallet->password,
                    'CurrencyCode' => $this->currency,
                    'Amount' => $amount,
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
                $balance = array_get($aResponseFormatData, 'response.RealBalance');
                return $this->responseMerge(
                    $aResponseFormatData,
                    [
                        'balance' => currency_multiply_transfer(data_get($wallet, "station"), $balance),
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
        } else {
            // 欲轉入的點數不符合該幣別最小單位限制
            throw new \Exception("轉入點數必須以". array_get($this->deposit_limit_map, "{$this->currency}", 1). "為單位");
        }
    }

    /**
     * 「回收」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 扣款金額
     * @throws \Exception
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        // 由於HB電子各幣別有對應的轉換比例 例如:娛樂城 1000VD = HB電子遊戲內 1VD
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'withdraw';
            // 訪問 parameters
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            $formParams = [
                'form_params' => [
                    /* required */
                    'Username' => $wallet->account,
                    'Password' => $wallet->password,
                    'CurrencyCode' => $this->currency,
                    'Amount' => 0-$amount,
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
                $balance = array_get($response, 'response.RealBalance');
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
                return $this->responseMerge(
                    $response,
                    [
                        'balance' => currency_multiply_transfer(data_get($wallet, "station"), $balance),
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
        } else {
            // 欲轉出的點數不符合該幣別最小單位限制
            throw new \Exception("轉出點數必須以". array_get($this->deposit_limit_map, "{$this->currency}", 1). "為單位");
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
     */
    public function passport(Wallet $wallet, array $options = [])
    {
        $apiUrl = config('api_caller.habanero.config.api_lobby');

        $gameId = array_get($options, 'game_id');
        // 取得遊戲的token
        $gameMemberID = $this->getGameMemberID($wallet);

        $params = [
            'brandid' => config('api_caller.habanero.config.brand_ID'),
            'keyname' => $gameId,
            'token' => $gameMemberID,
            'mode' => 'real',
            'locale' => config('api_caller.habanero.config.language')
        ];

        $params = http_build_query($params);

        // Act
        $url = $apiUrl . '?' . $params;

        $webUrl = $url;
        $mobileUrl = $url;

        return $this->responseMerge([], [
            'method' => 'redirect',
            'web_url' => $webUrl,
            'mobile_url' => $mobileUrl,
        ]);
    }

    /**
     * 取得HB電子的會員遊戲 token (戳其他 API 需要)
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參數
     * @return array|float
     * @throws \Exception
     */
    public function getGameMemberID(Wallet $wallet, $params = [])
    {
        $action = 'LoginOrCreatePlayer';

        // 訪問 parameters
        $formParams = [
            // 一般參數這邊設定
            'PlayerHostAddress' => request()->getClientIp(),
            'UserAgent' => config('api_caller.habanero.config.agent_account'),
            'KeepExistingToken' => true,
            'Username' => $wallet->account,
            'Password' => $wallet->password,
            'CurrencyCode' => $this->currency,
        ];
        try {
            $gameMemberID = ApiCaller::make('habanero')->methodAction('post', $action, [
                // 路由參數這邊設定
            ])->params(
                $formParams
            )->submit()['response']['Token'];
            return $gameMemberID;
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