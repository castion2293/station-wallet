<?php

namespace SuperPlatform\StationWallet\Connectors;

use Exception;
use Illuminate\Support\Facades\Log;
use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class CmdSportConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 遊戲站名稱
     */
    protected $station = 'cmd_sport';

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
     * 模板
     *
     * @var string
     */
    protected $templateName = '';

    /**
     * 風格
     *
     * @var string
     */
    protected $view = '';

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
        $this->console = new ConsoleOutput();
        parent::__construct();

        $this->currency = config('api_caller.cmd_sport.config.currency');
        $this->language = config('api_caller.cmd_sport.config.lang');
        $this->templateName = config('api_caller.cmd_sport.config.template_name');
        $this->view = config('api_caller.cmd_sport.config.view');
        $this->deposit_limit_map = config("api_caller.cmd_sport.deposit_limit");
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

        // 訪問 action
        $action = 'createAccount';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* require */
                'UserName' => $wallet->account,
                'Currency' => $this->currency,
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
            if (strpos($logException->getMessage(), '-98') !== false) {
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
                'UserName' => $wallet->account,
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

            $balance = (string)array_get($aResponseFormatData, 'response.Data.0.BetAmount');

            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            // cmd_currency_multiply_transfer() 此helper用於轉換 CMD體育點數 對應各幣別的比例換算結果
            // cmd_currency_multiply_transfer() 寫在 api-caller 套件 Helpers/CmdSportHelper.php 內
            return $this->responseMerge(
                $aResponseFormatData,
                [
                    'balance' => number_format(currency_multiply_transfer(data_get($wallet, "station"), array_get($aResponseFormatData, 'response.Data.0.BetAmount')), 4, ".", ""),//number_format(cmd_currency_multiply_transfer(array_get($aResponseFormatData, 'response.Data.0.BetAmount')), 4, ".", ""),
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
        // 由於CMD體育各幣別有對應的轉換比例 例如:娛樂城 1000VD = CMD體育遊戲內 1VD
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            // 參數 PaymentType 為1表示存款
            $requestId = str_random();
            // 訪問 action
            $action = 'deposit';
            // 交易流水號
            $ticketNo = str_random(50);
            // 將欲轉入之點數轉換成 遊戲館對應比例
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            // 訪問 parameters
            $formParams = [
                'form_params' => [
                    /* required */
                    'UserName' => $wallet->account,
                    'Money' => $amount,
                    'PaymentType' => 1,
                    'TicketNo' => $ticketNo,
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
                $balance = (string)array_get($aResponseFormatData, 'response.Data.0.BetAmount');
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
                // cmd_currency_divide_transfer() 此helper用於轉換 CMD體育點數 對應各幣別的比例換算結果
                // cmd_currency_divide_transfer() 寫在 api-caller 套件 Helpers/CmdSportHelper.php 內
                return $this->responseMerge(
                    $aResponseFormatData,
                    [
                        'balance' => currency_multiply_transfer(data_get($wallet, "station"), array_get($aResponseFormatData, 'response.Data.0.BetAmount')),
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

                // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
//            sleep(1);
//            $checkTransferSuccess = $this->checkTransfer([
//                'ticketNo' => $ticketNo,
//                'username' => $wallet->account,
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
     * @return float
     * @throws Exception
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        // 由於CMD體育各幣別有對應的轉換比例 例如:娛樂城 1000VD = CMD體育遊戲內 1VD
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            // 參數 PaymentType 為0表示提領
            $requestId = str_random();
            // 訪問 action
            $action = 'withdraw';
            // 交易流水號
            $ticketNo = str_random(50);
            // 將欲轉入之點數轉換成 遊戲館對應比例
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);

            // 訪問 parameters
            $formParams = [
                'form_params' => [
                    /* required */
                    'UserName' => $wallet->account,
                    'Money' => $amount,
                    'PaymentType' => 0,
                    'TicketNo' => $ticketNo,
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
                $balance = (string)array_get($aResponseFormatData, 'response.Data.0.BetAmount');
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
                return $this->responseMerge(
                    $aResponseFormatData,
                    [
                        'balance' => currency_multiply_transfer(data_get($wallet, "station"), array_get($aResponseFormatData, 'response.Data.0.BetAmount')),
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

                // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
//            sleep(1);
//            $checkTransferSuccess = $this->checkTransfer([
//                'ticketNo' => $ticketNo,
//                'username' => $wallet->account,
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
            throw new \Exception("轉出點數必須以". array_get($this->deposit_limit_map, "{$this->currency}", 1). "為單位");
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
        $balance = array_get($getBalance, 'response.Data.0.BetAmount');

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
     * @return mixed
     */
    public function passport(Wallet $wallet, array $options = [])
    {
        $account = $wallet->account;
        $queryString = "lang={$this->language}&user={$account}&token={$account}&currency={$this->currency}&templatename={$this->templateName}&view={$this->view}";
        $webUrl = config('api_caller.cmd_sport.config.web_url') . "auth.aspx?" . $queryString;
        $mobileUrl = config('api_caller.cmd_sport.config.mobile_url') . "auth.aspx?" . $queryString;


        return $this->responseMerge([], [
            'method' => 'redirect',
            'web_url' => $webUrl,
            'mobile_url' => $mobileUrl,
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
     * 再次檢查轉帳流水號，確認轉帳是否真的失敗
     *
     * @param array $params
     * @return bool
     */
    private function checkTransfer(array $params): bool
    {
        try {
            $ticketNo = array_get($params, 'ticketNo');
            $username = array_get($params, 'username');
            $response = ApiCaller::make('cmd_sport')->methodAction('GET', 'checkfundtransferstatus', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'UserName' => $username,
                'TicketNo' => $ticketNo,
            ])->submit();

            $response = array_get($response, 'response');

            if (!empty(array_get($response, 'Data'))) {
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
            $response = ApiCaller::make($this->station)->methodAction('GET', 'kickuser', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'UserName' => $wallet->account
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
