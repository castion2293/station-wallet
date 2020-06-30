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
class SuperSportConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

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
     * @var string $station 體彩遊戲站名稱
     */
    protected $station;

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->station = 'super_sport';
        $this->console = new ConsoleOutput();

        parent::__construct();
        $this->currency = config('api_caller.super_sport.config.currency');
        $this->deposit_limit_map = config("api_caller.super_sport.deposit_limit");
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
     * [
     *  'account' 帳號
     *  'passwd' 密碼
     *  'nickname' 暱稱 不超過20字元
     *  'level' 帳號等級 會員1 代理2
     *  'up_account' 上層帳號
     *  'up_passwd' 上層密碼
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
                'act' => config('station_wallet.stations.super_sport.build.act'),
                'up_account' => data_get($params, 'up_account', $this->config['build']['up_account']),
                'up_password' => data_get($params, 'up_password', $this->config['build']['up_password']),
                'account' => $wallet->account,
                'password' => $wallet->password,
                'nickname' => data_get($params, 'nickname', $wallet->account),
                'level' => data_get($params, 'level', 1),
                /* optional*/
                'copy_target' => config('station_wallet.stations.super_sport.build.copyAccount'),
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
            if (strpos($logException->getMessage(), '912') !== false) {
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
                'account' => $wallet->account,
                'act' => 'search',
                'up_account' => data_get($params, 'up_account', $this->config['build']['up_account']),
                'up_password' => data_get($params, 'up_account', $this->config['build']['up_password'])
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
            $balance = (string)array_get(array_get($response['response'], 'data'), 'point');
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
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
            if(array_get($exception->response(), 'code') === 903) {
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
        // 由於Super體育各幣別有對應的轉換比例 例如:娛樂城 1000VD = Super體育遊戲內 1VD
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'deposit';
            // 訪問 parameters
            $trackId = str_random(32);
            // 將欲轉入之點數轉換成 遊戲館對應比例
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            $formParams = [
                'form_params' => [
                    /* required */
                    'account' => $wallet->account,
                    'up_account' => $this->config['build']['up_account'],
                    'up_password' => $this->config['build']['up_password'],
                    'act' => 'add',
                    'point' => $amount,
                    /* optional */
                    'track_id' => $trackId
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
                $balance = (string)array_get(array_get($response['response'], 'data'), 'point');
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
                    'up_account' => $this->config['build']['up_account'],
                    'up_passwd' => $this->config['build']['up_password'],
                    'account' => $wallet->account,
                    'track_id' => $trackId
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
            $trackId = str_random(32);
            // 將欲轉入之點數轉換成 遊戲館對應比例
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            $formParams = [
                'form_params' => [
                    /* required */
                    'account' => $wallet->account,
                    'up_account' => $this->config['build']['up_account'],
                    'up_password' => $this->config['build']['up_password'],
                    'act' => 'sub',
                    'point' => $amount,
                    /* optional */
                    'track_id' => $trackId
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
                $balance = (string)array_get(array_get($response['response'], 'data'), 'point');
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
                return $this->responseMerge($response, [
                    'balance' => currency_multiply_transfer(data_get($wallet, "station"), $balance),
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
    
                // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
                sleep(1);
                $checkTransferSuccess = $this->checkTransfer([
                    'up_account' => $this->config['build']['up_account'],
                    'up_passwd' => $this->config['build']['up_password'],
                    'account' => $wallet->account,
                    'track_id' => $trackId
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
                'account' => $wallet->account,
                'password' => $wallet->password,
                'responseFormat' => "json" // optional
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

        $data = $response['response']['data'];
        $url = $this->webUrlParse($data['login_url']);
        $port = (array_has($url, 'port') && !empty(array_get($url, 'port')))
            ? ':' . $url['port']
            : '';
        $scheme = (!empty($this->config['scheme'])) ? $this->config['scheme'] : $data['scheme'];

        return $this->responseMerge($response, [
            'method' => 'redirect',
            'web_url' => $scheme . '://' . $url['host'] . $port . '/api/loginCRD?' . http_build_query($url['query']),
            'mobile_url' => '',
            'params' => [
                'h5web' => array_get($params, 'responseFormat'),
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
        $upAccount = array_get($params, 'up_account');
        $upPasswd = array_get($params, 'up_passwd');
        $account = array_get($params, 'account');
        $trackId = array_get($params, 'track_id');

        try {
            $response = ApiCaller::make('super_sport')->methodAction('post', 'points', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'act' => 'checking',
                'up_account' => $upAccount,
                'up_passwd' => $upPasswd,
                'account' => $account,
                'track_id' => $trackId
            ])->submit();

            $response = array_get($response, 'response.data');

            if (array_get($response, 'result') == 1) {
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
            $response = ApiCaller::make('super_sport')->methodAction('post', 'logout', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'account' => $wallet->account,
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