<?php

namespace SuperPlatform\StationWallet\Connectors;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationLoginRecord;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class VsLotteryConnector extends Connector
{
    protected $console;
    protected $station = 'vs_lottery';
    protected $guzzleClient;
    protected $currency;
    protected $deposit_limit_map;

    public function __construct()
    {
        $this->console = new ConsoleOutput();
        $this->guzzleClient = new Client();
        $this->currency = config('api_caller.vs_lottery.config.currency');
        $this->deposit_limit_map = config("api_caller.vs_lottery.deposit_limit");
        parent::__construct();
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet
     * @param array $aParams
     * @return array
     * @throws Exception
     */
    public function build(Wallet $wallet, array $aParams = []): array
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'createAccount';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'userName' => $wallet->account,
                'password' => $wallet->password,
                'currencyCode' => config("api_caller.vs_lottery.config.currency"),
                'firstName' => $wallet->account,
                'lastName' => $wallet->account,
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
            $errorCode = json_encode(array_get($response, 'CreatePlayerAccountResult'));
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $errorCode, $action);
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
            if (strpos($logException->getMessage(), '5100004') !== false) {
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
     * @param array $oParams
     * @return array
     * @throws Exception
     */
    public function balance(Wallet $wallet, array $oParams = []): array
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'getBalance';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'userName' => config("api_caller.vs_lottery.config.member_account_prefix") . $wallet->account,
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
            $errorCode = json_encode(array_get($aResponseFormatData, 'GetPlayerBalanceResult'));
            $balance = (string)array_get($aResponseFormatData, 'response.balance');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $errorCode, $action, $balance);
            return $this->responseMerge(
                $aResponseFormatData,
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
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action);
            throw $this->formatException($exception);
        }
    }

    /**
     * 「增加」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet
     * @param float $amount
     * @return array
     * @throws Exception
     */
    public function deposit(Wallet $wallet, float $amount): array
    {
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'deposit';
            // 訪問 parameters
            $billno = mt_rand();
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            $formParams = [
                'form_params' => [
                    /* required */
                    'userName' => config("api_caller.vs_lottery.config.member_account_prefix") . $wallet->account,
                    'amount' => $amount,
                    'clientRefTransId' => $billno,
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
                $arrayData = json_encode(array_get($aResponseFormatData, 'DepositWithdrawRefResult'));
                $balance = (string)array_get($aResponseFormatData, 'response.balanceAfter');
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
                return $this->responseMerge(
                    $aResponseFormatData,
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
                // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
                sleep(1);
                $checkTransferSuccess = $this->checkTransfer([
                    'billno' => $billno
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
            throw new \Exception("轉入點數必須以" . array_get($this->deposit_limit_map, "{$this->currency}", 1) . "為單位");
        }
    }

    /**
     * 「回收」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet
     * @param float $amount
     * @return array
     * @throws Exception
     */
    public function withdraw(Wallet $wallet, float $amount): array
    {
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'withdraw';
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            // 訪問 parameters
            $billno = mt_rand();
            $formParams = [
                'form_params' => [
                    /* required */
                    'userName' => config("api_caller.vs_lottery.config.member_account_prefix") . $wallet->account,
                    'amount' => 0 - $amount,
                    'clientRefTransId' => $billno,
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
                $errorCode = json_encode(array_get($aResponseFormatData, 'DepositWithdrawRefResult'));
                $balance = (string)array_get($aResponseFormatData, 'response.balanceAfter');
                $this->afterResponseLog($wallet, $requestId, $httpCode, $errorCode, $action, $balance, $amount);
                return $this->responseMerge(
                    $aResponseFormatData,
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

                // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
                sleep(1);
                $checkTransferSuccess = $this->checkTransfer([
                    'billno' => $billno
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
            throw new \Exception("轉入點數必須以" . array_get($this->deposit_limit_map, "{$this->currency}", 1) . "為單位");
        }
    }

    /**
     * 調整點數
     *
     * @param Wallet $wallet
     * @param float $fFinalBalance
     * @param array $aParams
     * @return array|float|mixed
     * @throws Exception
     */
    public function adjust(Wallet $wallet, float $fFinalBalance, array $aParams = [])
    {
        $fBalance = array_get($this->balance($wallet, ['password' => $wallet->password]), 'balance');
        if (number_format($fBalance, 2, '.', '') === number_format($fFinalBalance, 2, '.', '')) {
            return $fBalance;
        }

        /**
         * 應該要異動的點數量
         *
         * $fBalance 餘額大於 $fFinalBalance 例如：剩餘 1000，$fFinalBalance 為 600，需「回收 400」
         * $fBalance 餘額小於 $fFinalBalance 例如：剩餘 1000，$fFinalBalance 為 2100，需「增加 1100」
         */
        $fAdjustValue = $fFinalBalance - $fBalance;

        if ($fBalance > $fFinalBalance) {
            return $this->withdraw($wallet, $fAdjustValue);
        } else {
            return $this->deposit($wallet, $fAdjustValue);
        }
    }

    /**
     * 透過錢包 ID 取得夾心連結
     *
     * @param string $sWalletId
     * @return StationLoginRecord
     */
    public function play(string $sWalletId): StationLoginRecord
    {
        return StationWallet::generatePlayUrl(StationWallet::getWallet($sWalletId, $this->station));
    }

    /**
     * 向遊戲站端請求遊玩連結
     *
     * @param Wallet $wallet
     * @param array $aOptions
     * @return array
     * @throws Exception
     */
    public function passport(Wallet $wallet, array $aOptions = []): array
    {
        $aResponseFormatData = $this->getResponseFormatData(
            'passport',
            [
                'userName' => config("api_caller.vs_lottery.config.member_account_prefix") . $wallet->account,
                'password' => $wallet->password,
                'lang' => config('station_wallet.stations.vs_lottery.passport.language'),
            ]
        );
        return $this->responseMerge(
            $aResponseFormatData,
            [
                'method' => 'redirect',
                'web_url' => array_get($aResponseFormatData, 'response.url'),
                'mobile_url' => array_get($aResponseFormatData, 'response.url'),
                'params' => [],
            ]
        );
    }

    /**
     * @param string $sAction
     * @param array $aFormParams
     * @return array
     * @throws Exception
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
        } catch (Exception $exception) {
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
        $billNo = array_get($params, 'billno');

        try {
            $response = ApiCaller::make('vs_lottery')->methodAction('post', 'CheckDepositWithdrawStatus', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'clientRefTransId' => $billNo
            ])->submit();

            $response = array_get($response, 'response');

            if (array_get($response, 'CheckDepositWithdrawStatusResult') == '0') {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * 登出會員 TODO: kick out player
     * @param Wallet $wallet
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function logout(Wallet $wallet): bool
    {
        try {
            $response = ApiCaller::make('vs_lottery')->methodAction('post', 'KickOutPlayer', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'userName' => config("api_caller.vs_lottery.config.member_account_prefix") . $wallet->account,
            ])->submit();

            $response = array_get($response, 'response');

            if (array_get($response, 'KickOutPlayerResult') == '0') {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            return false;
        }
    }
}