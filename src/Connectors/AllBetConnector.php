<?php

namespace SuperPlatform\StationWallet\Connectors;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationLoginRecord as LoginRecord;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * 「歐博」帳號錢包連接器
 *
 * @package SuperPlatform\StationWallet\Connectors
 */
class AllBetConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 歐博遊戲站名稱
     */
    protected $station;

    /**
     * @var string $scheme
     */
    protected $scheme;

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->station = $this->getStationCode();
        $this->console = new ConsoleOutput();
        $this->scheme = $this->config['scheme'];

        parent::__construct();
    }

    public function getStationCode()
    {
        return 'all_bet';
    }

    /**
     * 取得可用盤口
     * @throws \Exception
     */
    public function getQueryHandicap()
    {
        try {
            // 單錢包與多錢包的接口不同
            $action = (env('APP_IS_SINGLE_BALANCE_SITE') === 'yes') ? 'query_agent_handicaps' : 'query_handicap';
            $response = ApiCaller::make('all_bet')->methodAction('post', $action, [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'agent' => $this->config['build']['agent'],
            ])->submit();
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->getStationCode(),
                'get_query_handicap',
                []
            ));
            throw $exception;
        }

        $handicaps = collect($response['response']['handicaps'])->groupBy('handicapType')->toArray();
        return [
            'normal_handicaps' => array_get($handicaps, 0),
            'vip_handicaps' => array_get($handicaps, 1),
        ];
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * @return array|mixed
     * @throws \Exception
     */
    public function build(Wallet $wallet, array $params = [])
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'createAccount';
        // 訪問 parameters (必需先取得盤口)
        $handicap = $this->getQueryHandicap();
        $defaultNormalHandicap = array_get(
            $handicap,
            'normal_handicaps.0.id',
            $this->config['build']['normal_handicaps']
        );
        $normalHandicapConfig = config('station_wallet.stations.all_bet.build.normal_handicaps');
        $normalHandicap = $normalHandicapConfig ?? $defaultNormalHandicap;
//        $normalHandicap = data_get($params, 'normal_handicaps', $defaultNormalHandicap);
        $defaultVipHandicap = array_get($handicap, 'vip_handicaps.0.id', $this->config['build']['vip_handicaps']);

        $vipHandicapConfig = config('station_wallet.stations.all_bet.build.vip_handicaps');
        $vipHandicap = $vipHandicapConfig ?? $defaultVipHandicap;
//        $vipHandicap = data_get($params, 'vip_handicaps', $defaultVipHandicap);

        $formParams = [
            'form_params' => array(
                /* required */
                'agent' => data_get($params, 'agent', config('api_caller.all_bet.config.agent')),
                'account' => $wallet->account,
                'password' => $wallet->password,
                'normal_handicaps' => $normalHandicap,
                'vip_handicaps' => $vipHandicap,
                'normal_hall_rebate' => data_get(
                    $params,
                    'normal_hall_rebate',
                    $this->config['build']['normal_hall_rebate']
                ),
                /* optional */
                'dv_hall_rebate' => data_get($params, 'dv_hall_rebate'),
                'lax_hall_rebate' => data_get($params, 'lax_hall_rebate'),
                'lst_hall_rebate' => data_get($params, 'lst_hall_rebate'),
                'max_win' => data_get($params, 'max_win'),
                'max_lost' => data_get($params, 'max_lost'),
            ),
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
            return $this->responseMerge($response, [
                'account' => data_get(data_get($response, 'response'), 'client')
            ]);
            // 歐博回傳錯誤是HTTP errorCode
        } catch (\GuzzleHttp\Exception\ServerException $exception) {
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->getStationCode(),
                $action,
                $formParams
            ));
            // 判断字串中是否有帳號重複訊息
            if (strpos($exception->getMessage(), 'CLIENT_EXIST') !== false) {
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
                'account' => $wallet->account,
                'password' => $wallet->password,
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
            $balance = (string)array_get(array_get($response, 'response'), 'balance');
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            return $this->responseMerge($response, [
                'balance' => $balance
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
        $requestId = str_random();
        // 訪問 action
        $action = 'deposit';
        // 訪問 parameters
        $traceId = config("api_caller.all_bet.config.property_id") . substr(md5(time() . $wallet->account), 0, 13);

        $formParams = [
            'form_params' => [
                /* required */
                'trace_id' => $traceId,
                'agent' => config("api_caller.all_bet.config.agent"),
                'account' => $wallet->account,
                'point' => $amount,
                'oper_flag' => 1, // 請求增加點數需傳入 1，請求減少點數傳入 0
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
            $balance = $this->balance($wallet)['balance'];
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
            return $this->responseMerge($response, [
                'balance' => $balance
            ]);
        } catch (\Exception $exception) {
//            show_exception_message($exception);
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
                'sn' => $traceId
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
     * @return array|float
     * @throws \Exception
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'withdraw';
        // 訪問 parameters
        $traceId = config("api_caller.all_bet.config.property_id") .  substr(md5(time() . $wallet->account), 0, 13);
        $formParams = [
            'form_params' => [
                /* required */
                'trace_id' => $traceId,
                'agent' => config("api_caller.all_bet.config.agent"),
                'account' => $wallet->account,
                'point' => $amount,
                'oper_flag' => 0, // 請求增加點數需傳入 1，請求減少點數傳入 0
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
            $balance = $this->balance($wallet)['balance'];
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
            return $this->responseMerge($response, [
                'balance' => $balance
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
                'sn' => $traceId
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
                /**
                 * 這邊密碼在營運平臺專案中使用 md5 擷取固定長度做為密碼寫到 allbet 遊戲館
                 * 在舊專案中中使用 hash("crc32", {password}, false) 做為密碼寫到 allbet 遊戲館
                 * 當使用舊專案帳號如 a61 時，在這需使用原密碼 && hash
                 * 如果使用原密碼 && md5 傳過去 allbet 就會是錯誤的
                 */
                'password' => $wallet->password,
                /* optional */
                'language' => config('station_wallet.stations.all_bet.passport.language'),
                'redirect_to' => array_get($params, 'redirect_to'),
                'game_hall' => array_get($params, 'game_hall'),

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

        // 因為 ALL_BET_API_URL 包含 8443 port，web_url 需特別加上 port 資訊才能串起正確的網址
        //  （要直接 redirect 的做法，網址必須已經串好參數等資訊）
        //
        // 備註：瑪雅也有 port 但不用特別串 port 是因為他們家的 response InGameUrl 已是完整的 redirect 網址
        $data = $this->webUrlParse($response['response']['gameLoginUrl']);
        $port = (array_has($data, 'port') && !empty(array_get($data, 'port')))
            ? ':' . $data['port']
            : '';

        $scheme = (!empty($this->scheme)) ? $this->scheme : $data['scheme'];

        return $this->responseMerge($response, [
            'method' => 'redirect',
            'web_url' => $scheme . '://' . $data['host'] . $port . '?' . http_build_query($data['query']),
            'mobile_url' => '',
            'params' => $data['query'],
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
            $sn = array_get($params, 'sn');

            $response = ApiCaller::make('all_bet')->methodAction('post', 'query_transfer_state', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'random' => mt_rand(),
                'sn' => $sn,
            ])->submit();

            $response = array_get($response, 'response');

            if (array_get($response, 'transferState') == 1) {
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
            $response = ApiCaller::make('all_bet')->methodAction('post', 'logout_game', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                // 一般參數這邊設定
                'random' => mt_rand(),
                'client' => $wallet->account,
            ])->submit();

            $response = array_get($response, 'response');

            if (array_get($response, 'error_code') == "OK") {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->getStationCode(),
                'logout',
                []
            ));
            throw $this->formatException($exception);
        }
    }
}
