<?php

namespace SuperPlatform\StationWallet\Connectors;


use Illuminate\Support\Facades\Log;
use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

class Cq9GameConnector extends Connector
{

    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    protected $userToken;

    /**
     * @var string $station cq9遊戲站名稱
     */
    protected $station = 'cq9_game';

    public function __construct()
    {
        $this->console = new ConsoleOutput();
        parent::__construct();
    }


    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet 錢包
     * @param array  $params 參照表參數
     *
     * @return mixed
     * @throws \Exception
     */
    public function build(Wallet $wallet, array $params = [])
    {
        $requestId = str_random();
        // 檢查帳號是否已存在
        $accountIsExist = $this->checkAccountExist($wallet);

        // 若已建立
        if (array_get($accountIsExist, 'data')) {
            if (data_get($wallet, 'status') === "no") {
                $wallet->status = "yes";
                $wallet->save();
            }
            return $this->responseMerge($accountIsExist, [
                'account_exist' => array_get($accountIsExist, 'data')
            ]);
        } else {
            // 還未建立
            // 訪問 action
            $action = "createAccount";

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
                    $this->station,
                    $action,
                    $formParams
                );
                $httpCode = array_get($response, 'http_code');
                $arrayData = json_encode(array_get($response, 'response'));
                // 戳API成功則寫入log並return
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action);
                return $this->responseMerge($response, [
                    'account' => data_get(data_get($response, 'response'), 'data.account')
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
                if (strpos($logException->getMessage(), 6) !== false) {
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
        // 單錢包禁止使用多錢包取餘額API
        if ((env('APP_IS_SINGLE_BALANCE_SITE') === 'yes')) {
            throw new \Exception('單錢包禁止使用balance接口');
        }

        $requestId = str_random();
        // 訪問 action
        $action = 'getBalance';
        // 訪問 parameters
        $formParams = [
            'route_params' => [
                /* required */
                'account' => $wallet->account,
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
            $balance = (string)array_get($response['response'], 'data.balance');
            // 戳API成功則寫入log並return
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
            if(array_get($exception->response(), 'errorCode') === '2') {
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
     *
     * @return float
     * @throws \Exception
     */
    public function deposit(Wallet $wallet, float $amount)
    {
        // 單錢包禁止使用多錢包取充值API
        if ((env('APP_IS_SINGLE_BALANCE_SITE') === 'yes')) {
            throw new \Exception('單錢包禁止使用depoist接口');
        }

        $requestId = str_random();
        // 訪問 action
        $action = "deposit";

        // 訪問 parameters
        $mtcode = $wallet->account.time();
        $formParams = [
            'form_params' => [
                /* required */
                'account' => $wallet->account,
                'amount' => $amount,
                'mtcode' => $mtcode,
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
            $balance = (string)array_get($response['response']['data'], 'balance');
            // 戳API成功則寫入log並return
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

            // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
            sleep(1);
            $checkTransferSuccess = $this->checkTransfer([
                'mtcode' => $mtcode
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
     *
     * @return float
     * @throws \Exception
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        // 單錢包禁止使用多錢包扣款API
        if ((env('APP_IS_SINGLE_BALANCE_SITE') === 'yes')) {
            throw new \Exception('單錢包禁止使用withdraw接口');
        }

        $requestId = str_random();
        // 訪問 action
        $action = "withdraw";

        // 訪問 parameters
        $mtcode = $wallet->account.time();
        $formParams = [
            'form_params' => [
                /* required */
                'account' => $wallet->account,
                'amount' => $amount,
                'mtcode' => $mtcode,
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
            $balance = (string)array_get($response['response']['data'], 'balance');
            // 戳API成功則寫入log並return
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

            // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
            sleep(1);
            $checkTransferSuccess = $this->checkTransfer([
                'mtcode' => $mtcode
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
    public function adjust(Wallet $wallet, float $finalBalance, array $params = []) {
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
     *
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
     * @param Wallet $wallet  錢包
     * @param array  $options 參照表參數
     *
     * @return mixed
     * @throws \Exception
     */
    public function passport(Wallet $wallet, array $options = [])
    {
        $userToken = $this->getUserToken($wallet);
        // 訪問 action
        // 單錢包與多錢包的接口不同
        $action = (env('APP_IS_SINGLE_BALANCE_SITE') === 'yes') ? 'player/sw/lobbylink' : 'player/lobbylink';
        // 訪問 parameters
        $formParams = [
            'usertoken' => $userToken,
            'lang' => 'th'
        ];

        // 指定單一遊戲站轉方式
        $gameId = array_get($options, 'game_id');
        if (!empty($gameId)) {
            // 訪問 action
            // 單錢包與多錢包的接口不同
            $action = (env('APP_IS_SINGLE_BALANCE_SITE') === 'yes') ? 'player/sw/gamelink' : 'player/gamelink';
            // 訪問 parameters
            $formParams = [
                'usertoken' => $userToken,
                'gamehall' => 'CQ9',
                'gamecode' => $gameId,
                'gameplat' => "web",
                'lang' => 'th'
            ];
        }

        // 多錢包 帶入參數 usertoken
        // 單錢包 帶入參數 account
        if (env('APP_IS_SINGLE_BALANCE_SITE') === 'yes') {
            array_forget($formParams, 'usertoken');
            $formParams['account'] = $wallet->account;
        }

        try {
            $response = ApiCaller::make($this->station)
                ->methodAction('post', $action, [
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
        $data = array_get($response, 'response');
        return $this->responseMerge(
            $data,
            [
                'method' => 'redirect',
                'web_url' => array_get($data, 'data.url'),
                'mobile_url' => array_get($data, 'data.url'),
                'params' => [],
            ]
        );

    }

    /**
     * 取得cq9的 userToken (進入遊戲大廳需要)
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參數
     * @return array|float
     * @throws \Exception
     */
    public function getUserToken(Wallet $wallet, $params = [])
    {
        try {
            $cq9Caller = (new \SuperPlatform\ApiCaller\ApiCaller)->make('cq9_game');
            // 登出
//            $cq9Caller->methodAction('POST', 'player/logout', [
//                "account" => $wallet->account,
//            ])->params([
//                    'account' => $wallet->account,
//            ])->submit();

            // 檢查玩家狀態,login:登入中/logout:尚未登入/gaming:遊戲中
            $userStatusResponse = $cq9Caller->methodAction('GET', 'player/token/{account}', [
                "account" => $wallet->account,
            ])->params([
                    // 一般參數這邊設定
//                    'account' => $wallet->account,
//                    'password' => $wallet->password,
                ]
            )->submit()['response']['data'];

            // 若玩家已登入則直接取userToken
            if (array_get($userStatusResponse, 'status') === "login" || array_get($userStatusResponse, 'status') === "gaming") {
                $userToken = array_get($userStatusResponse, 'usertoken');
            }

            // 若玩家未登入則先將該會員登入cq9,並取得 'userToken'
            if (array_get($userStatusResponse, 'status') === "logout") {
                $userToken = $cq9Caller->methodAction('POST', 'player/login', [
                ])->params([
                        // 一般參數這邊設定
                        'account' => $wallet->account,
                        'password' => $wallet->password,
                    ]
                )->submit()['response']['data']['usertoken'];
            }
        } catch (\Exception $exception) {
//             show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                'login',
                []
            ));
            throw $exception;
        }
        return $userToken;
    }

    public function checkAccountExist(Wallet $wallet)
    {
        try {
            $cq9Caller = (new \SuperPlatform\ApiCaller\ApiCaller)->make('cq9_game');
            $response = $cq9Caller->methodAction('GET', 'player/check/{account}', [
                "account" => $wallet->account,
            ])->params([
                    // 一般參數這邊設定
                ]
            )->submit()['response'];
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                'checkAccountExist',
                []
            ));
            throw $exception;
        }

        return $response;
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
            $mtcode = array_get($params, 'mtcode');

            $response = ApiCaller::make('cq9_game')
                ->methodAction('get', 'transaction/record/{mtcode}', [
                    // 路由參數這邊設定
                    'mtcode' => $mtcode
                ])->params([
                    // 一般參數這邊設定
                ])->submit();

            $response = array_get($response, 'response.data');

            if (array_get($response, 'status') == 'success') {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function logout(Wallet $wallet)
    {
        try {
            $cq9Caller = (new \SuperPlatform\ApiCaller\ApiCaller)->make('cq9_game');
            $response = $cq9Caller->methodAction('POST', 'player/logout', [
            ])->params([
                    // 一般參數這邊設定
                    "account" => $wallet->account,
                ]
            )->submit()['response'];
            if (array_get($response, "status.message") == "Success") {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                'logout',
                []
            ));
            throw $exception;
        }
    }
}