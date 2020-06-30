<?php

namespace SuperPlatform\StationWallet\Connectors;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationLoginRecord as LoginRecord;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * 「Royal」帳號錢包連接器
 *
 * @package SuperPlatform\StationWallet\Connectors
 */
class RoyalGameConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 遊戲站名稱
     */
    protected $station;

    protected $guzzleClient;

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->station = 'royal_game';
        $this->console = new ConsoleOutput();
        $this->guzzleClient = new Client();

        parent::__construct();
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet
     * @param array $params
     * @return mixed|void
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
                'RequestID' => str_random(32),
                'Control' => 1,
                'BucketID' => config("api_caller.royal_game.config.bucket_id"),
                'MemberID' => $wallet->account,
                'MemberName' => $wallet->account,
                'Operator' => $wallet->account,
                'Currency' => config('station_wallet.stations.royal_game.build.currency'),
                'IP' => request()->getClientIp(),
            ],
        ];
        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
            // 進入遊戲時設置限紅
            $this->getLimit($wallet);
            $httpCode = array_get($response, 'http_code');
            $arrayData = array_get($response, 'response');
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
            // 進入遊戲時設置限紅
            $this->getLimit($wallet);
            $logException = $this->formatException($exception);
            if (strpos($logException->getMessage(), 'W429') !== false) {
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
                'RequestID' => str_random(32),
                'BucketID' => config("api_caller.royal_game.config.bucket_id"),
                'MemberID' => $wallet->account,
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
            $arrayData = array_get($response, 'response');
            $balance = array_get($response,'response.Money');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            return $this->responseMerge($response, [
                'balance' => $balance
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
            // 若「取得餘額API」回傳「查无此帐号,请检查」，則再次戳「建立帳號API」
            if(array_get($exception->response(), 'errorCode') === 'W407') {
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
     * @param array $params 參照表參數
     * @return array|float
     * @throws \Exception
     */
    public function deposit(Wallet $wallet, float $amount, array $params = [])
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'deposit';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'RequestID' => str_random(32),
                'Control' => 1,
                'TransferID' => str_random(32),
                'BucketID' => config("api_caller.royal_game.config.bucket_id"),
                'MemberID' => $wallet->account,
                'TransferMoney' => (string)$amount,
                'Operator' => $wallet->account,
                'IP' => request()->getClientIp(),
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
            $arrayData = array_get($response, 'response');
            $balance = (string)array_get($response, 'response.Result.AfterMoney');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
            return $this->responseMerge($response, [
                'balance' => $balance
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
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);
            throw $this->formatException($exception);
        }

    }

    /**
     * 「回收」本地錢包對應遊戲站帳號「點數」
     *
     * @param Wallet $wallet 錢包
     * @param float $amount 扣款金額
     * @param array $params
     * @return array|float
     * @throws \Exception
     */
    public function withdraw(Wallet $wallet, float $amount, array $params = [])
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'withdraw';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'RequestID' => str_random(32),
                'Control' => 1,
                'TransferID' => str_random(32),
                'BucketID' => config("api_caller.royal_game.config.bucket_id"),
                'MemberID' => $wallet->account,
                'TransferMoney' => (string)-$amount,
                'Operator' => $wallet->account,
                'IP' => request()->getClientIp(),
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
            $arrayData = array_get($response, 'response');
            $balance = (string)array_get($response, 'response.Result.AfterMoney');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
            return $this->responseMerge($response, [
                'balance' => $balance
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
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);
            throw $this->formatException($exception);
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
        return StationWallet::generatePlayUrl(StationWallet::getWallet($walletId, $this->station));
    }

    /**
     * 取得下注限紅設定
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return array|mixed
     * @throws \Exception
     */
    public function getLimit(Wallet $wallet)
    {
        // 訪問 action
        $action = 'getBetLimits';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                'RequestID' => str_random(32),
                'Member' => config("api_caller.royal_game.config.bucket_id"). '@' . $wallet->account,
                'Control' => 5,
                'LevelList' => config('station_wallet.stations.royal_game.getLimit.limit')
            ],
        ];
        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
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

        return $this->responseMerge($response, [
            'limit' => $wallet->account
        ]);
    }

    /**
     * 更新「RG」下注限紅設定
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return array|mixed
     * @throws \Exception
     */
    public function updateLimit(Wallet $wallet)
    {
        // 訪問 action
        $action = 'updateBetLimit';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                'RequestID' => str_random(32),
                'Member' => config("api_caller.royal_game.config.bucket_id"). '@' . $wallet->account,
                'Control' => 6,
                'List' => array([
                    // 百家樂
                    'GameID' => 'Bacc',
                    'Level' => config('station_wallet.stations.royal_game.updateBetLimit.update_bacc_limit'),
                ],[
                    'GameID' => 'InsuBacc',
                    'Level' => config('station_wallet.stations.royal_game.updateBetLimit.update_InsuBacc_limit'),
                ],[
                    'GameID' => 'LunPan',
                    'Level' => config('station_wallet.stations.royal_game.updateBetLimit.update_LunPan_limit'),
                ],[
                    'GameID' => 'ShaiZi',
                    'Level' => config('station_wallet.stations.royal_game.updateBetLimit.update_ShziZi_limit'),
                ],[
                    'GameID' => 'FanTan',
                    'Level' => config('station_wallet.stations.royal_game.updateBetLimit.update_FanTan_limit'),
                ],[
                    'GameID' => 'LongHu',
                    'Level' => config('station_wallet.stations.royal_game.updateBetLimit.update_LongHu_limit'),
                ]
                ),
            ],
        ];
        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
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


        return $this->responseMerge($response, [
            'limit' => $wallet->account
        ]);
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
                'RequestID' => str_random(32),
                'BucketID' => config("api_caller.royal_game.config.bucket_id"),
                'MemberID' => $wallet->account,
                'Password' => $wallet->password,
                'GameType' => 1,
                'ServerName' => 'lobby',
                'Lang' => config('station_wallet.stations.royal_game.passport.lang'),
            ],
        ];
        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
            // 進入遊戲時修改限紅
            $this->updateLimit($wallet);
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));
            // 進入遊戲時修改限紅
            $this->updateLimit($wallet);
            throw $this->formatException($exception);
        }

        $SessionKey = array_get($response,'response.SessionKey');

        return $this->responseMerge($response, [
            'method' => 'redirect',
            'web_url' => config("api_caller.royal_game.config.game_url").'/Entrance?SessionKey='.$SessionKey,
            'mobile_url' => config("api_caller.royal_game.config.game_url").'/Entrance?SessionKey='.$SessionKey,
        ]);
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