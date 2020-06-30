<?php

namespace SuperPlatform\StationWallet\Connectors;

use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class CockFightConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station bingo遊戲站名稱
     */
    protected $station;

    /**
     * 幣別
     *
     * @var string
     */
    protected $currency = '';

    /**
     * 轉入點數之最小單位對應表
     *
     */
    protected $deposit_limit_map;

    /**
     * 遊戲域名
     *
     * @var string
     */
    protected $gameUrl = '';

    /**
     * 語系
     *
     * @var string
     */
    protected $language = '';

    /**
     * CockFightConnector constructor.
     */
    public function __construct()
    {
        $this->station = 'cock_fight';
        $this->console = new ConsoleOutput();

        $this->gameUrl = config('api_caller.cock_fight.config.game_url');
        $this->language = config('api_caller.cock_fight.config.language');
        $this->currency = config('api_caller.cock_fight.config.currency');
        $this->deposit_limit_map = config("api_caller.cock_fight.deposit_limit");
        parent::__construct();
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」因為S128沒有開通錢包的API，所以直接回傳成功訊息
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
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
                'login_id' => $wallet->account,
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
            $balance = (string)array_get($response, 'response.balance');
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

            // 檢查如果帳號不存在，代表遊戲方還未開通錢包，所以直接回傳餘額為0
            $isLoginIdNotFound = (strpos($arrayData, 'login id not found') !== false);
            if ($isLoginIdNotFound) {
                return $this->responseMerge(
                    [],
                    [
                        'balance' => 0,
                    ]
                );
            }

            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));

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
     * @throws \Exception
     */
    public function deposit(Wallet $wallet, float $amount)
    {
        // 由於鬥雞各幣別有對應的轉換比例 例如:娛樂城 1000VD = 鬥雞遊戲內 1VD
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'deposit';
            // 訪問 parameters
            $refNo = str_random(50);
            // 將欲轉入之點數轉換成 遊戲館對應比例
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            $formParams = [
                'form_params' => [
                    'login_id' => $wallet->account,
                    'name' => $wallet->account,
                    'amount' => $amount,
                    'ref_no' => $refNo,
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
                $afterBalance = (string)array_get($response, 'response.balance_close');
                // 戳API成功則寫入log並return
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $afterBalance, $amount);
                // currency_multiply_transfer() 此helper用於轉換 鬥雞點數 對應各幣別的比例換算結果
                // currency_multiply_transfer() 寫在 api-caller 套件 Helpers/CurrencyHelper.php 內
                return $this->responseMerge(
                    $response,
                    [
                        'balance' => currency_multiply_transfer(data_get($wallet, "station"), $afterBalance),
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
    //            sleep(1);
    //            $checkTransferSuccess = $this->checkTransfer([
    //                'ref_no' => $refNo
    //            ]);
    //            if ($checkTransferSuccess) {
    //                return $this->responseMerge([], [
    //                    'balance' => ''
    //                ]);
    //            }

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
        // 由於鬥雞各幣別有對應的轉換比例 例如:娛樂城 1000VD = 鬥雞遊戲內 1VD
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'withdraw';
            // 訪問 parameters
            $refNo = str_random(50);
            // 將欲轉入之點數轉換成 遊戲館對應比例
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            $formParams = [
                'form_params' => [
                    'login_id' => $wallet->account,
                    'amount' => $amount,
                    'ref_no' => $refNo,
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
                $afterBalance = (string)array_get($response, 'response.balance_close');
                // 戳API成功則寫入log並return
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $afterBalance, $amount);
                return $this->responseMerge(
                    $response,
                    [
                        'balance' => currency_multiply_transfer(data_get($wallet, "station"), $afterBalance),
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
    //            sleep(1);
    //            $checkTransferSuccess = $this->checkTransfer([
    //                'ref_no' => $refNo
    //            ]);
    //            if ($checkTransferSuccess) {
    //                return $this->responseMerge([], [
    //                    'balance' => ''
    //                ]);
    //            }

                throw $this->formatException($exception);
            }
        } else {
            // 欲轉入的點數不符合該幣別最小單位限制
            throw new \Exception("轉入點數必須以". array_get($this->deposit_limit_map, "{$this->currency}", 1). "為單位");
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
        // 訪問 action
        $action = 'passport';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                'login_id' => $wallet->account,
                'name' => $wallet->account,
            ],
        ];

        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );

            $sessionId = array_get($response, 'response.session_id');

            $gameUrl = $this->gameUrl . 'api/auth_login.aspx';
            $mobileUrl = $this->gameUrl . 'api/cash/auth';
            $params = [
                'session_id' => $sessionId,
                'lang' => $this->language,
                'login_id' => $wallet->account
            ];

            return $this->responseMerge($response, [
                'method' => 'post',
                'web_url' => $gameUrl,
                'mobile_url' => $mobileUrl,
                'params' => $params
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
            $refNo = array_get($params, 'ref_no');

            $response = ApiCaller::make('cock_fight')->methodAction('post', 'check_transfer', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'ref_no' => $refNo
            ])->submit();
            print_r($response);
            $statusCode = array_get($response, 'response.status_code');

            if ($statusCode === 0) {
                return true;
            }
        } catch (\Exception $exception) {
            return false;
        }
    }
}