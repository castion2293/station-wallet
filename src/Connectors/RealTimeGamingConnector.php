<?php

namespace SuperPlatform\StationWallet\Connectors;

use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;
use function GuzzleHttp\Psr7\str;

/**
 * @property  getGameAgentId
 */
class RealTimeGamingConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * 轉入點數之最小單位對應表
     *
     */
    protected $deposit_limit_map;

    /**
     * 幣別
     *
     * @var string
     */
    protected $currency = '';

    /**
     * @var string $station RTG遊戲站名稱
     */
    protected $station = 'real_time_gaming';

    protected $guzzleClient;

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->console = new ConsoleOutput();

        $this->guzzleClient = new Client();

        parent::__construct();
        $this->currency = config('api_caller.real_time_gaming.config.currency');
        $this->deposit_limit_map = config("api_caller.real_time_gaming.deposit_limit");
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return array|mixed
     * @throws \Exception
     */
    public function build(Wallet $wallet, array $params = []): array
    {
        $requestId = str_random();
        $birthDate = Carbon::now()->toDateString();

        // 訪問 action
        $action = 'createAccount';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* require */
                'agentId' => $this->getGameAgentId($wallet),
                'username' => $wallet->account,
                'firstName' => $wallet->account,
                'lastName' => $wallet->account,
                'email' => array_get($params, 'email', 'test@test.com'),
                'countryId' => 'TW',
                'gender' => array_get($params, 'gender', 'Male'),
                'birthdate' => Carbon::parse($birthDate)->toIso8601String(),
                'currency' => config('api_caller.real_time_gaming.config.currency'),
                /* optional */
                'languageId' => config('station_wallet.stations.real_time_gaming.build.language'),
                'walletId' => $wallet->id
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
            // 回傳錯誤是HTTP errorCode
        } catch (\GuzzleHttp\Exception\ServerException $exception) {
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
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));
            // 判断字串中是否有帳號重複訊息
            if ($exception->getCode() !== 11020) {
                return $this->responseMerge([], [
                    'account' => $wallet->account
                ]);
            }
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
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return array|float
     * @throws \Exception
     */
    public function balance(Wallet $wallet, array $params = []): array
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'getBalance';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'playerLogin' => $wallet->account
            ],
        ];

        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            $balance = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
            $httpCode = array_get($balance, 'http_code');
            $arrayData = json_encode(array_get($balance, 'response'));
            // 因API回傳回來是float格式 所以先轉成陣列後在merge
            $response['balance'] = $balance;
            $balance = (string)array_get($response, 'balance');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            // 由於RTG各幣別有對應的轉換比例 例如:RTG 同步金額若是1000 則會回傳1元
            return $this->responseMerge($response, [
                'balance' => number_format(currency_multiply_transfer(data_get($wallet, "station"), $balance), 4, ".", ""),
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
            if (strpos($arrayData, 'Player not found.') !== false) {
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
    public function deposit(Wallet $wallet, float $amount): array
    {
        // 由於RTG各幣別有對應的轉換比例 例如:RTG 存入金額若是1000 則會回傳1元
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'deposit';
            // 訪問 parameters
            $formParams = [
                'form_params' => [
                    /* required */
                    'playerLogin' => $wallet->account
                ],
                'route_params' => [
                    /* required */
                    'amount' => $amount,
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
                $responseBalance = (string)array_get($response, 'response.currentBalance');
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
                // currency_multiply_transfer() 此helper用於轉換 RTG點數 對應各幣別的比例換算結果
                // currency_multiply_transfer() 寫在 api-caller 套件 Helpers/CurrencyHelper.php 內
                return $this->responseMerge(
                    $response,
                    [
                        'balance' => currency_multiply_transfer(data_get($wallet, "station"), $responseBalance),
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
            // 欲轉入的點數不符合該幣別最小單位限制
            throw new \Exception("轉入點數必須以". array_get($this->deposit_limit_map, "{$this->currency}", 1). "為單位");
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
    public function withdraw(Wallet $wallet, float $amount): array
    {
        // 由於RTG各幣別有對應的轉換比例 例如:RTG 轉出金額若是1000 則會回傳1元
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'withdraw';
            // 訪問 parameters
            $formParams = [
                'form_params' => [
                    /* required */
                    'playerLogin' => $wallet->account
                ],
                'route_params' => [
                    /* required */
                    'amount' => $amount,
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
                $responseBalance = (string)array_get($response, 'response.currentBalance');
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
                // currency_multiply_transfer() 此helper用於轉換 RTG點數 對應各幣別的比例換算結果
                // currency_multiply_transfer() 寫在 api-caller 套件 Helpers/CurrencyHelper.php 內
                return $this->responseMerge(
                    $response,
                    [
                        'balance' => currency_multiply_transfer(data_get($wallet, "station"), $responseBalance),
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
            // 欲轉入的點數不符合該幣別最小單位限制
            throw new \Exception("轉入點數必須以". array_get($this->deposit_limit_map, "{$this->currency}", 1). "為單位");
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
     * 取得進入遊戲站的通行證資訊
     *
     * @param Wallet $wallet 錢包
     * @param array $options
     * @return array|mixed
     * @throws \Exception
     */
    public function passport(Wallet $wallet, array $options = []): array
    {

        //玩家進入遊戲大廳

        // 訪問 action
        $action = 'launcher/lobby';
        // 訪問 parameters
        $formParams = [
            /* required */
            'player' => [
                'playerLogin' => $wallet->account,
            ],
            'locale' => config('station_wallet.stations.real_time_gaming.build.locale'),
            'language' => config('station_wallet.stations.real_time_gaming.passport.language_lobby'),
            'isDemo' => false
        ];

        // 指定單一遊戲站轉方式
        $gameId = array_get($options, 'game_id');
        if (!empty($gameId)) {
            // 訪問 action
            $action = 'GameLauncher';
            // 訪問 parameters
            $formParams = [
                /* required */
                'player' => [
                    'playerId' => '',
                    'agentId' => '',
                    'playerLogin' => $wallet->account,
                ],
                'gameId' => $gameId,
                'locale' => config('station_wallet.stations.real_time_gaming.build.locale'),
                'language' => config('station_wallet.stations.real_time_gaming.passport.language_lobby'),
                'isDemo' => false
            ];
        }

        try {
            $response = ApiCaller::make($this->station)->methodAction('post', $action, [
                // 路由參數這邊設定
            ])->params(
                // 一般參數這邊設定
                $formParams
            )->submit();
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
            'method' => 'redirect',
            'web_url' => array_get($response,'response.instantPlayUrl'),
            'mobile_url' => array_get($response,'response.instantPlayUrl'),
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
            throw $exception;
        }

        $response['balance'] = $aResponseFormatData;

        if ($sAction == 'getBalance'){
            return $response;
        }

            return $aResponseFormatData;
    }
    /**
     * 取得RTG的 agentID (戳其他 API 需要)
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參數
     * @return array|float
     * @throws \Exception
     */
    public function getGameAgentId(Wallet $wallet, $params = [])
    {
        // 訪問 parameters
        $formParams = [
            // 一般參數這邊設定
        ];
        try {
            $agentId = (new \SuperPlatform\ApiCaller\ApiCaller)->make('real_time_gaming')->methodAction('GET', 'start', [
                // 路由參數這邊設定
            ])->params(

            )->submit()['response']['agentId'];

        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                'getGameAgentId',
                $formParams
            ));
            throw $this->formatException($exception);
        }
        return $agentId;
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