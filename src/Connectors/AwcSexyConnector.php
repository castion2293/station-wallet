<?php

namespace SuperPlatform\StationWallet\Connectors;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class AwcSexyConnector extends Connector
{

    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 性感百家遊戲站名稱
     */
    protected $station = 'awc_sexy';

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
     * 限紅組
     *
     * @var array
     */
    protected $betLimitSet = [];

    /**
     * @var int
     */
    protected static $partial = 0;

    /**
     * AwcSexyConnector constructor.
     */
    public function __construct()
    {
        $this->console = new ConsoleOutput();

        $this->currency = config('api_caller.awc_sexy.config.currency');
        $this->language = config('api_caller.awc_sexy.config.language');
        $this->betLimitSet = explode(',', config('api_caller.awc_sexy.config.bet_limit'));

        parent::__construct();

    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return array
     * @throws ApiCallerException
     */
    public function build(Wallet $wallet, array $params = [])
    {
        $requestId = str_random();

        $betLimit = [
            'SEXYBCRT' => [
                'LIVE' => [
                    'limitId' => $this->betLimitSet
                ]
            ]
        ];

        // 訪問 action
        $action = 'createAccount';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                'userId' => $wallet->account,
                'currency' => $this->currency,
                'betLimit' => json_encode($betLimit),
                'language' => $this->language,
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
        } catch (ApiCallerException $e){
            // 檢查如果帳號已經存在，就直接回傳成功
            $errorCode = array_get($e->response(), 'errorCode');
            if ($errorCode === '1001') {
                return $this->responseMerge([], [
                    'account' => $wallet->account
                ]);
            }
            throw $e;
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
                'userIds' => $wallet->account,
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
            $results = array_get($response, "response.results");
            // 若「取得餘額API」回傳「空的」代表「無此帳號」，則再次戳「建立帳號API」
            if(empty($results)) {
                $this->build($wallet);
            }
            $balance = array_get($results, '0.balance');
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
        $txCode = str_random(50);
        $formParams = [
            'form_params' => [
                /* required */
                'userId' => $wallet->account,
                'transferAmount' => currency_divide_transfer($this->station, $amount),
                'txCode' => $txCode,
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
            $balance = array_get($response, 'response.currentBalance');
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
                'txCode' => $txCode,
            ]);
            if ($checkTransferSuccess) {
                return $this->responseMerge([], [
                    'balance' => ''
                ]);
            }

            throw $this->formatException($exception);
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
        $txCode = str_random(50);
        $formParams = [
            'form_params' => [
                /* required */
                'userId' => $wallet->account,
                'txCode' => $txCode,
                'withdrawType' => static::$partial,
                'transferAmount' => (string)currency_divide_transfer($this->station, $amount),
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
            $balance = array_get($response, 'response.currentBalance');
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
                'txCode' => $txCode,
            ]);
            if ($checkTransferSuccess) {
                return $this->responseMerge([], [
                    'balance' => ''
                ]);
            }

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
                'userId' => $wallet->account,
                'isMobileLogin' => 'false',
            ],
        ];

        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );

            $webUrl = array_get($response, 'response.url');

            // 組手機版跳轉URL
            $domain = explode('?', $webUrl)[0];
            parse_str(explode('?', $webUrl)[1], $query);
            $query['isMobileLogin'] = 'true';

            $mobileUrl = $domain . '?' . http_build_query($query);

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
            // 若「進入遊戲API」回傳「查无此帐号,请检查」，則再次戳「建立帳號API」
            if(array_get($exception->response(), 'errorCode') === '1002') {
                $this->build($wallet);
            }
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
            $txCode = array_get($params, 'txCode');

            $response = ApiCaller::make('awc_sexy')->methodAction('post', 'checkTransferOperation', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'txCode' => $txCode,
            ])->submit();

            $status = array_get($response, 'response.status');
            $txStatus = array_get($response, 'response.txStatus');

            if ($status === '0000' && $txStatus === 1) {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            return false;
        }
    }
}