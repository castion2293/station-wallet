<?php


namespace SuperPlatform\StationWallet\Connectors;


use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class KkLotteryConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station KK彩票遊戲站名稱
     */
    protected $station = 'kk_lottery';

    /**
     * 幣別
     *
     * @var string
     */
    protected $currency = '';

    /**
     * 國別
     *
     * @var string
     */
    protected $country = '';

    /**
     * 玩家賠率
     *
     * @var
     */
    protected $odds;

    /**
     * 用戶類型
     *
     * @var
     */
    protected static $normal = 0;

    /**
     * KkLotteryConnector constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->console = new ConsoleOutput();

        $this->currency = config('api_caller.kk_lottery.config.currency');
        $this->country = config('api_caller.kk_lottery.config.country');
        $this->odds = config('api_caller.kk_lottery.config.odds');
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
                'username' => $wallet->account,
                'usertype' => static::$normal,
                'countrycode' => $this->country,
                'currencycode' => $this->currency,
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
            // 檢查如果帳號已經存在，就直接回傳成功
            if (strpos($logException->getMessage(), 'user is exist') !== false) {
                return $this->responseMerge([], [
                    'account' => $wallet->account,
                ]);
            }
            $httpCode = $logException->getCode();
            $errorData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $errorData, $action);
            throw $this->formatException($exception);
        }
    }

    /**
     * 取得本地錢包對應遊戲站帳號「餘額」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
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
            $balance = array_get($response, 'response.data.available_balance');
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, (string)$balance);
            return $this->responseMerge(
                $response,
                [
                    'balance' => number_format(currency_multiply_transfer($this->station, $balance), 4, ".", ""),
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
     * @return array
     * @throws \Exception
     */
    public function deposit(Wallet $wallet, float $amount)
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'deposit';
        // 訪問 parameters
        $orderId = str_random(32);
        $formParams = [
            'form_params' => [
                /* required */
                'username' => $wallet->account,
                'amount' => currency_divide_transfer($this->station, $amount),
                'currencycode' => $this->currency,
                'orderid' => $orderId,
                'deposittime' => now()->timestamp * 1000,
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
            $balance = $this->balance($wallet)['balance'];
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, (string)$balance, $amount);
            return $this->responseMerge(
                $response,
                [
                    'balance' => currency_multiply_transfer($this->station, $balance),
                ]
            );
        } catch (\Exception $exception) {
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

            // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
            sleep(1);
            $checkTransferSuccess = $this->checkTransfer([
                'order_id' => $orderId,
            ]);
            if ($checkTransferSuccess) {
                return $this->responseMerge([], [
                    'balance' => ''
                ]);
            }

            throw $logException;
        }
    }

    /**
     * 「回收」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 扣款金額
     * @return array
     * @throws \Exception
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'withdraw';
        // 訪問 parameters
        $orderId = str_random(32);
        $formParams = [
            'form_params' => [
                /* required */
                'username' => $wallet->account,
                'amount' => currency_divide_transfer($this->station, $amount),
                'currencycode' => $this->currency,
                'orderid' => $orderId,
                'withdrawtime' => now()->timestamp * 1000,
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
            $balance = $this->balance($wallet)['balance'];
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, (string)$balance, $amount);
            return $this->responseMerge(
                $response,
                [
                    'balance' => currency_multiply_transfer($this->station, $balance),
                ]
            );
        } catch (\Exception $exception) {
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

            // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
            sleep(1);
            $checkTransferSuccess = $this->checkTransfer([
                'order_id' => $orderId,
            ]);
            if ($checkTransferSuccess) {
                return $this->responseMerge([], [
                    'balance' => ''
                ]);
            }

            throw $logException;
        }
    }

    /**
     * 調整點數
     *
     * @param Wallet $wallet 錢包
     * @param float $finalBalance 經過異動點數後，最後的 balance 餘額應為多少
     * @param array $params 參照表參數
     * @return array
     * @throws \Exception
     */
    public function adjust(Wallet $wallet, float $finalBalance, array $params = [])
    {
        $getBalance = $this->balance($wallet);
        $balance = array_get($getBalance, 'balance');

        if (number_format($balance, 2, '.', '') === number_format($finalBalance, 2, '.', '')) {
            return $getBalance;
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
                'username' => $wallet->account,
                'logintime' => now()->timestamp * 1000,
                'odds' => floatval($this->odds),
                'backurl' => config('app.url'),
            ],
        ];

        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );

            $gameUrl = array_get($response, 'response.data.url');

            return $this->responseMerge($response, [
                'method' => 'redirect',
                'web_url' => $gameUrl,
                'mobile_url' => $gameUrl,
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
     * 再次檢查轉帳流水號，確認轉帳是否真的失敗
     *
     * @param array $params
     * @return bool
     */
    private function checkTransfer(array $params): bool
    {
        try {
            $orderId = array_get($params, 'order_id');

            $response = ApiCaller::make('kk_lottery')->methodAction('post', '/data/fundlog', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'orderid' => $orderId,
                'pagenumber' => 1,
                'pagesize' => 1000,
            ])->submit();

            $response = array_get($response, 'response.rows');
            $relatedOrder = array_get(array_first($response), 'related_order');

            if ($orderId === $relatedOrder) {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            return false;
        }
    }
}