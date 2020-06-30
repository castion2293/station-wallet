<?php

namespace SuperPlatform\StationWallet\Connectors;

use Illuminate\Support\Facades\Log;
use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationLoginRecord as LoginRecord;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * 「沙龍」帳號錢包連接器
 *
 * @package SuperPlatform\StationWallet\Connectors
 */
class SaGamingConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 沙龍遊戲站名稱
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
     * Connector constructor.
     */
    public function __construct()
    {
        $this->station = $this->getStationCode();
        $this->console = new ConsoleOutput();

        parent::__construct();
        $this->currency = config('api_caller.sa_gaming.config.currency');
        $this->deposit_limit_map = config("api_caller.sa_gaming.deposit_limit");
    }

    protected function getStationCode()
    {
        return 'sa_gaming';
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet
     * @param array $params 參照表參數
     *      * [
     *  'account' 帳號
     *  'currency_type' 貨幣類型
     *
     *      CNY 人民币
     *      USD 美元
     *      EUR 欧元
     *      JPY 日元
     *      VND 越南盾
     *      AUD 澳元
     *      TWD 新台币
     *      MYR 马来西亚林吉特
     *      IDR 印尼盾
     *      SGD 新加坡元
     *      GBP 英镑
     *      THB 泰铢
     *      TRY 土耳其里拉
     *      UAH 乌克格里夫纳
     *      XBT 比特币
     *      CAD 加拿大元
     *      NOK 挪威克朗
     *      SEK 瑞典克朗
     *      ZAR 南非蘭德
     *      BDT 孟加拉塔卡
     * ]
     * @return array|mixed
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
                /* required */
                'account' => $wallet->account,
                'currency_type' => $this->currency,
            ],
        ];
        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            $response = ApiPoke::poke(
                $this->getStationCode(),
                $action,
                $formParams
            );
            $httpCode = array_get($response, 'http_code');
            $arrayData = json_encode(array_get($response, 'response'));
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action);
            // 創建帳號成功後會更新「當日最大贏額」設定
            $this->winningLimit($wallet);
            return $this->responseMerge($response, [
                'account' => data_get(data_get($response, 'response'), 'Username')
            ]);
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->getStationCode(),
                $action,
                $formParams
            ));
            $logException = $this->formatException($exception);
            if (strpos($logException->getMessage(), '113') !== false) {
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
     * @param string $account
     * @param array $params
     * @return mixed
     */

    /**
     * 取得本地錢包對應遊戲站帳號「餘額」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return array|float
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
                'account' => $wallet->account,
            ],
        ];
        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            $response = ApiPoke::poke(
                $this->getStationCode(),
                $action,
                $formParams
            );
            $httpCode = array_get($response, 'http_code');
            $arrayData = json_encode(array_get($response, 'response'));
            $balance = (string)array_get(array_get($response, 'response'), 'Balance');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            return $this->responseMerge($response, [
                'balance' => number_format(currency_multiply_transfer(data_get($wallet, "station"), $balance), 4, ".", ""),
            ]);
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->getStationCode(),
                $action,
                $formParams
            ));
            $logException = $this->formatException($exception);
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若「取得餘額API」回傳「查无此帐号,请检查」，則再次戳「建立帳號API」
            if(array_get($exception->response(), 'ErrorMsgId') === 116) {
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
     * @return array|float
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
            $orderId = 'IN' . date('YmdHis') . $wallet->account; // ID:IN+YYYYMMDDHHMMSS+Username
            // 將欲轉入之點數轉換成 遊戲館對應比例
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            $formParams = [
                'form_params' => [
                    /* required */
                    'account' => $wallet->account,
                    'trace_id' => $orderId,
                    'point' => $amount,
                ],
            ];
            try {
                $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
                $response = ApiPoke::poke(
                    $this->getStationCode(),
                    $action,
                    $formParams
                );
                $httpCode = array_get($response, 'http_code');
                $arrayData = json_encode(array_get($response, 'response'));
                $balance = (string)array_get(array_get($response, 'response'), 'Balance');
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
                // currency_multiply_transfer() 此helper用於轉換 Super體育點數 對應各幣別的比例換算結果
                // currency_multiply_transfer() 寫在 api-caller 套件 Helpers/CurrencyHelper.php 內
                return $this->responseMerge($response, [
                    'balance' => currency_multiply_transfer(data_get($wallet, "station"), $balance),
                ]);
            } catch (\Exception $exception) {
                // show_exception_message($exception);
                event(new ConnectorExceptionOccurred(
                    $exception,
                    $this->getStationCode(),
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
                    'OrderId' => $orderId
                ]);
                if ($checkTransferSuccess) {
                    return $this->responseMerge([], [
                        'balance' => ''
                    ]);
                }

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
     * @return array|float
     * @throws \Exception
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        // 由於Super體育各幣別有對應的轉換比例 例如:娛樂城 1000VD = Super體育遊戲內 1VD
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'withdraw';
            // 訪問 parameters
            $orderId = 'OUT' . date('YmdHis') . $wallet->account;
            // 將欲轉入之點數轉換成 遊戲館對應比例
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            $formParams = [
                'form_params' => [
                    /* required */
                    'account' => $wallet->account,
                    'trace_id' => $orderId, // ID:IN+YYYYMMDDHHMMSS+Username
                    'point' => $amount,
                ],
            ];
            try {
                $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
                $response = ApiPoke::poke(
                    $this->getStationCode(),
                    $action,
                    $formParams
                );
                $httpCode = array_get($response, 'http_code');
                $arrayData = json_encode(array_get($response, 'response'));
                $balance = (string)array_get(array_get($response, 'response'), 'Balance');
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
                return $this->responseMerge($response, [
                    'balance' => currency_multiply_transfer(data_get($wallet, "station"), $balance),
                ]);
            } catch (\Exception $exception) {
                // show_exception_message($exception);
                event(new ConnectorExceptionOccurred(
                    $exception,
                    $this->getStationCode(),
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
                    'OrderId' => $orderId
                ]);
                if ($checkTransferSuccess) {
                    return $this->responseMerge([], [
                        'balance' => ''
                    ]);
                }

                throw $this->formatException($exception);
            }
        } else {
            // 欲轉出的點數不符合該幣別最小單位限制
            throw new \Exception("轉出點數必須以". array_get($this->deposit_limit_map, "{$this->currency}", 1). "為單位");
        }
    }

    /**
     * 調整點數，決定動作，與要「增加」或「回收」的點數量
     *
     * @param Wallet $wallet 錢包
     * @param float $finalBalance 經過異動點數後，最後的 balance 餘額應為多少
     * @param array $params 參照表參數
     * @return array|float|mixed
     * @throws \Exception
     */
    public function adjust(Wallet $wallet, float $finalBalance, array $params = [])
    {
        $getBalance = $this->balance($wallet, ['password' => $wallet->password]);
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
     * @return LoginRecord
     */
    public function play(string $walletId)
    {
        // 寫入 passport 資料，產生對應的遊戲連結記錄 StationLoginRecord (Model)，返回夾心連結實體
        return StationWallet::generatePlayUrl(StationWallet::getWallet($walletId, $this->getStationCode()));
    }

    /**
     * 取得進入遊戲站的通行證資訊
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return array|mixed
     * @throws \Exception
     */
    public function passport(Wallet $wallet, array $params = [])
    {
        // 訪問 action
        $action = 'passport';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'account' => $wallet->account,
                /* optional */
                'currency_type' => array_get($params, 'currency_type', 'TWD'),
            ],
        ];
        try {
            $response = ApiPoke::poke(
                $this->getStationCode(),
                $action,
                $formParams
            );
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->getStationCode(),
                $action,
                $formParams
            ));
            throw $this->formatException($exception);
        }

        $data = $response['response'];

        return $this->responseMerge($response, [
            'method' => 'post',
            'web_url' => config("api_caller.{$this->getStationCode()}.config.play_url"),
            'mobile_url' => '',
            'params' => [
                'username' => array_get($data, "DisplayName"),
                'token' => array_get($data, 'Token'),
                'lobby' => config("api_caller.{$this->getStationCode()}.config.lobby_code"),
                'lang' => config('station_wallet.stations.sa_gaming.passport.language'),
                'returnurl' => array_get($params, 'returnurl', ''),
                'mobile' => array_get($params, 'mobile', false),
                'h5web' => array_get($params, 'h5web', false),
            ]
        ]);
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
            $orderId = array_get($params, 'OrderId');

            // 再進行查帳動做
            $response = ApiCaller::make('sa_gaming')->methodAction('post', 'CheckOrderId')
                ->params([
                    'OrderId' => $orderId
                ])->submit();

            $response = array_get($response, 'response');

            if (array_get($response, 'isExist') == 'true') {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            return false;
        }
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
            $response = ApiCaller::make('sa_gaming')->methodAction('post', 'KickUser', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'Username' => $wallet->account,
            ])->submit();

            $response = array_get($response, 'response');

            if ($response) {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->getStationCode(),
                'KickUser',
                []
            ));
            throw $this->formatException($exception);
        }
    }

    /**
     * 新建會員設定贏額
     *
     * @param Wallet $wallet
     * @param array $params
     *
     * @return bool
     * @throws \Exception
     */
    public function winningLimit(Wallet $wallet, array $params = []) {
        try {
            $response = ApiCaller::make('sa_gaming')->methodAction('post', 'SetUserMaxWinning', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'Username' => $wallet->account,
                'MaxWinning' => config("api_caller.sa_gaming.config.maxWinning"),
            ])->submit();

            $response = array_get($response, 'response');

            if ($response) {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->getStationCode(),
                'SetUserMaxWinning',
                $params
            ));
            throw $this->formatException($exception);
        }
    }
}