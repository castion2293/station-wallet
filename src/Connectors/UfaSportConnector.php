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
 * 「體彩」帳號錢包連接器
 *
 * @package SuperPlatform\StationWallet\Connectors
 */
class UfaSportConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 體彩遊戲站名稱
     */
    protected $station;

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->station = 'ufa_sport';
        $this->console = new ConsoleOutput();

        parent::__construct();
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * [
     *  'secret' 加密碼
     *  'agent' 代理帳號
     *  'username' 會員帳號
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
                'secret' => config("api_caller.ufa_sport.config.secret_code"),
                'agent' => config("api_caller.ufa_sport.config.agent"),
                'username' => $wallet->account
            ],
        ];
        try {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
            $this->updateLimit($wallet);
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
            $this->updateLimit($wallet);
            $logException = $this->formatException($exception);
            if (strpos($logException->getMessage(), '1') !== false) {
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
                /* required */
                'secret' => config("api_caller.ufa_sport.config.secret_code"),
                'agent' => config("api_caller.ufa_sport.config.agent"),
                'username' => $wallet->account,
                /* optional */
                'max1' => config('station_wallet.stations.ufa_sport.updateProfile.max'),  // <早盤 / 今日 / 滾球的最大賭注>
                'max2' => config('station_wallet.stations.ufa_sport.updateProfile.max'),  // <1X2 / 雙重機會的最大賭注>
                'max3' => config('station_wallet.stations.ufa_sport.updateProfile.max'),  // <混合過關的最大賭注>
                'max4' => config('station_wallet.stations.ufa_sport.updateProfile.max'),  // <正確分數/總進球/半場全場/第一個進球最後一個進球的最大投注>
                'max5' => config('station_wallet.stations.ufa_sport.updateProfile.max'),  // <其他體育早盤 / 今日 / 滾球的最大賭注>
                'lim1' => config('station_wallet.stations.ufa_sport.updateProfile.lim'),  // <早盤 / 今日 / 滾球的每匹配匹配>
                'lim2' => config('station_wallet.stations.ufa_sport.updateProfile.lim'),  // <1X2 / 雙重機會的每場比賽匹配>
                'lim3' => config('station_wallet.stations.ufa_sport.updateProfile.lim'),  // <每組合混合過關限制>
                'lim4' => config('station_wallet.stations.ufa_sport.updateProfile.lim'),  // <每場比賽的正確比分/總進球/半場全場/第一球進球最後一球>
                'lim5' => config('station_wallet.stations.ufa_sport.updateProfile.lim'),  // <其他運動早盤 / 今日 / 滾球的每場比賽匹配>
                'comtype' => config('station_wallet.stations.ufa_sport.updateProfile.comtype'),  // <早盤 / 今日 / 滾球的A，B，C，D，E，F，G，H，I，J的選擇>
                'com1' => config('station_wallet.stations.ufa_sport.updateProfile.com'),  // <早盤 / 今日 / 滾球 佣金>
                'com2' => config('station_wallet.stations.ufa_sport.updateProfile.com'),  // <1X2 / 雙重機會的佣金>
                'com3' => config('station_wallet.stations.ufa_sport.updateProfile.com'),  // <混合過關佣金>
                'com4' => config('station_wallet.stations.ufa_sport.updateProfile.com'),  // <其他佣金>
                'suspend' => 0  // <0：沒有暫停，1：暫停>
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
                'secret' => config("api_caller.ufa_sport.config.secret_code"),
                'agent' => config("api_caller.ufa_sport.config.agent"),
                'username' => $wallet->account
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
            $balance = (string)array_get($response, 'response.result');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            return $this->responseMerge($response, [
                'balance' => array_get($response, 'response.result')
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
        $formParams = [
            'form_params' => [
                /* required */
                'secret' => config("api_caller.ufa_sport.config.secret_code"),
                'agent' => config("api_caller.ufa_sport.config.agent"),
                'username' => $wallet->account,
                'serial' => str_random(32),
                'amount' => $amount
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
            $balance = (string)array_get($response, 'response.result');
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
     * @return array|float
     * @throws \Exception
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'withdraw';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'secret' => config("api_caller.ufa_sport.config.secret_code"),
                'agent' => config("api_caller.ufa_sport.config.agent"),
                'username' => $wallet->account,
                'serial' => str_random(32),
                'amount' => $amount
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
            $balance = (string)array_get($response, 'response.result');
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
                'secret' => config("api_caller.ufa_sport.config.secret_code"),
                'agent' => config("api_caller.ufa_sport.config.agent"),
                'username' => $wallet->account,
                'host' => config("api_caller.ufa_sport.config.host_url"),
                'lang' => config('station_wallet.stations.ufa_sport.passport.lang'),
                'accType' => config('station_wallet.stations.ufa_sport.passport.accType')
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

        $data = $response['response'];
        $host = array_get($data,'result.login.host');
        $params = array_get($data,'result.login.param');

        $web_url = $host. '?us='.$params['us']. '&k='. $params['k']. '&lang='. $params['lang']. '&accType='. $params['accType']. '&r='. $params['r'];
        $mobile_url = 'http://sportmobi.time399.com/public/Validate.aspx?us='. $params['us']. '&k='.$params['k']. '&lang='. $params['lang']. '&accType='. $params['accType']. '&r='. $params['r'];

        return $this->responseMerge($response, [
            'method' => 'redirect',
            'web_url' => $web_url,
            'mobile_url' => $mobile_url,
            'params' => []
        ]);
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
            $response = ApiCaller::make($this->station)->methodAction('get', 'logout', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'username' => $wallet->account,
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
                $this->station,
                'logout',
                []
            ));
            throw $this->formatException($exception);
        }
    }
}