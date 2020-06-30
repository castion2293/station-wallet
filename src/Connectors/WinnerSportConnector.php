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

class WinnerSportConnector extends Connector
{
    protected $console;
    protected $station = 'winner_sport';
    protected $guzzleClient;

    public function __construct()
    {
        $this->console = new ConsoleOutput();
        $this->guzzleClient = new Client();

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
                'username' => $wallet->account,
                'alias' => $wallet->account,
//                'currency' => 1,
                'istest' => array_get($this->config, 'build.istest') === false ? 1 : 2,
                'top' => array_get($aParams, 'top_account', $this->config['build']['top_account']),
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
            $errorCode = json_encode(array_get($response, 'response'));
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
            // 檢查如果帳號已經存在，就直接回傳成功
            if (strpos($logException->getMessage(), '002') !== false) {
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
                'username' => $wallet->account,
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
            $errorCode = json_encode(array_get($aResponseFormatData, 'response'));
            $balance = (string)array_get($aResponseFormatData, 'response.money');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $errorCode, $action, $balance);
            return $this->responseMerge(
                $aResponseFormatData,
                [
                    'balance' => $balance,
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
            // 檢查如果帳號不存在，就直接在建立帳號並同步餘額
            if (strpos($logException->getMessage(), '002') !== false) {
                $this->build($wallet);
                $this->balance($wallet);
            }
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
        $requestId = str_random();
        // 訪問 action
        $action = 'deposit';
        // 訪問 parameters
        $billno = mt_rand();
        $formParams = [
            'form_params' => [
                /* required */
                'username' => $wallet->account,
                'money' => $amount,
                /* optional */
                'billno' => $billno,
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
            $balance = (string)array_get($aResponseFormatData, 'response.money');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
            return $this->responseMerge(
                $aResponseFormatData,
                [
                    'balance' => $balance,
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
            // 檢查如果帳號不存在，就直接在建立帳號並再次轉點
            if (strpos($logException->getMessage(), '002') !== false) {
                $this->build($wallet);
                $this->deposit($wallet, $amount);
            }
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);

            // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
            sleep(1);
            $checkTransferSuccess = $this->checkTransfer([
                'username' => $wallet->account,
                'billno' => $billno
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
     * @param Wallet $wallet
     * @param float $amount
     * @return array
     * @throws Exception
     */
    public function withdraw(Wallet $wallet, float $amount): array
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'withdraw';
        // 訪問 parameters
        $billno = mt_rand();
        $formParams = [
            'form_params' => [
                /* required */
                'username' => $wallet->account,
                'money' => -$amount,
                /* optional */
                'billno' => $billno,
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
            $errorCode = json_encode(array_get($aResponseFormatData, 'response'));
            $balance = (string)array_get($aResponseFormatData, 'response.money');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $errorCode, $action, $balance, $amount);
            return $this->responseMerge(
                $aResponseFormatData,
                [
                    'balance' => $balance,
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
            // 檢查如果帳號不存在，就直接在建立帳號並再次轉點
            if (strpos($logException->getMessage(), '002') !== false) {
                $this->build($wallet);
                $this->withdraw($wallet, $amount);
            }
            $httpCode = $logException->getCode();
            $arrayData = $logException->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);

            // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
            sleep(1);
            $checkTransferSuccess = $this->checkTransfer([
                'username' => $wallet->account,
                'billno' => $billno
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
        $fAdjustValue = abs($fBalance - $fFinalBalance);
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
        // 訪問 action
        $action = 'passport';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'username' => $wallet->account,
                'slangx' => 'zh-cn',
//                'mobile' => 0,
            ],
        ];
        try {
            $aResponseFormatData = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
        }catch (ApiCallerException $exception) {
            // 檢查如果帳號不存在，就在戳一次建立帳號API
            $errorCode = array_get($exception->response(), 'errorCode');
            if ($errorCode == '002') {
                $this->build($wallet);
                $this->passport($wallet);
            }
        }
        return $this->responseMerge(
            $aResponseFormatData,
            [
                'method' => 'redirect',
                'web_url' => $this->config['build']['login_path'] . array_get($aResponseFormatData, 'response.lid'),
                'mobile_url' => $this->config['build']['login_path'] . array_get($aResponseFormatData, 'response.lid'),
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
        $username = array_get($params, 'username');
        $billNo = array_get($params, 'billno');

        try {
            $response = ApiCaller::make('winner_sport')->methodAction('post', 'Transfer_Check', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'username' => $username,
                'billno' => $billNo
            ])->submit();

            $response = array_get($response, 'response');

            if (array_get($response, 'code') == '001') {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            return false;
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