<?php

namespace SuperPlatform\StationWallet\Connectors;

use Carbon\Carbon;
use GuzzleHttp\Client;
use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use SuperPlatform\StationWallet\Tests\User;
use Symfony\Component\Console\Output\ConsoleOutput;

class BoboPokerConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 人人棋牌遊戲站名稱
     */
    protected $station = 'bobo_poker';

    protected $guzzleClient;

    /**
     * 上層代理帳號
     *
     * @var \Illuminate\Config\Repository|mixed
     */
    protected $agentId = '';

    /**
     * BoboPokerConnector constructor.
     */
    public function __construct()
    {
        $this->console = new ConsoleOutput();

        parent::__construct();

        $this->guzzleClient = new Client();

        $this->agentId = config('api_caller.bobo_poker.config.agent_id');
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參照表參數
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
                'spId' => $this->agentId,
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
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action);
            return $this->responseMerge($response, [
                'account' => $wallet->account
            ]);
        } catch (\Exception $exception) {
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));
            $logException = $this->formatException($exception);
            if (strpos($logException->getMessage(), 'ACCOUNT_ALREADY_EXIST') !== false) {
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
                'spId' => $this->agentId,
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
            $arrayData = json_encode(array_get($response, 'response.data'));
            $balance = (string)array_get($response, 'response.data.balance');
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            return $this->responseMerge(
                $response,
                [
                    'balance' => array_get($response, 'response.data.balance') / 100, // 單位為分，需除100
                ]
            );
        } catch (\Exception $exception) {
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
            if(array_get($exception->response(), 'errorCode') === 'ACCOUNT_NOT_EXIST') {
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
     */
    public function deposit(Wallet $wallet, float $amount)
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'deposit';
        // 訪問 parameters
        $tranId = str_random(32);
        $formParams = [
            'form_params' => [
                /* required */
                'spId' => $this->agentId,
                'tranId' => $tranId,
                'account' => $wallet->account,
                'type' => 0, // 0:轉入 1:轉出
                'amount' => intval(bcmul($amount, 100)) // 單位為分，需 * 100
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
            $afterBalance = (string)array_get($response, 'response.data.afterBalance');
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $afterBalance, $amount);
            return $this->responseMerge(
                $response,
                [
                    'balance' => array_get($response, 'response.data.afterBalance') / 100, // 單位為分，需除100
                ]
            );
        } catch (\Exception $exception) {
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
                'sp_id' => $this->agentId,
                'tran_id' => $tranId
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
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        $requestId = str_random();
        // 訪問 action
        $action = 'withdraw';
        // 訪問 parameters
        $tranId = str_random(32);
        $formParams = [
            'form_params' => [
                /* required */
                'spId' => $this->agentId,
                'tranId' => $tranId,
                'account' => $wallet->account,
                'type' => 1, // 0:轉入 1:轉出
                'amount' => intval(bcmul($amount, 100)) // 單位為分，需 * 100
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
            $afterBalance = (string)array_get($response, 'response.data.afterBalance');
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $afterBalance, $amount);
            return $this->responseMerge(
                $response,
                [
                    'balance' => array_get($response, 'response.data.afterBalance') / 100, // 單位為分，需除100
                ]
            );
        } catch (\Exception $exception) {
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
                'sp_id' => $this->agentId,
                'tran_id' => $tranId
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
     * @param Wallet $wallet 錢包
     * @param float $finalBalance 經過異動點數後，最後的 balance 餘額應為多少
     * @param array $params 參照表參數
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
     */
    public function passport(Wallet $wallet, array $options = [])
    {
        // 訪問 action
        $action = 'passport';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'spId' => $this->agentId,
                'productId' => '8857',
                'returnUrl' => config('app.url'),
                'account' => $wallet->account,
                'logoUrl' => 'http://d87b.com/image/PC/logo.jpg',
                'storeUrl' => config('app.url')
            ],
            'route_params' => [
                /* required */
                'device' => 'pc'
            ],
        ];

        // 指定單一遊戲站轉方式 (寶寶彩票)
        $gameId = array_get($options, 'game_id');
        if (!empty($gameId)) {
            // 訪問 parameters
            $formParams = [
                'form_params' => [
                    /* required */
                    'spId' => $this->agentId,
                    'productId' => 'bb1',
                    'returnUrl' => config('app.url'),
                    'account' => $wallet->account,
                    'logoUrl' => 'http://d87b.com/image/PC/logo.jpg',
                    'storeUrl' => config('app.url')
                ],
                'route_params' => [
                    /* required */
                    'device' => 'pc'
                ],
            ];
        }

        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );

            $gameUrl = array_get($response, 'response.data.gameUrl');

            return $this->responseMerge($response, [
                'method' => 'redirect',
                'web_url' => $gameUrl,
                'mobile_url' => $gameUrl
            ]);
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
            $spId = array_get($params, 'sp_id');
            $tranId = array_get($params, 'tran_id');

            $response = ApiCaller::make('bobo_poker')->methodAction('post', 'datasouce/checkOrder', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'spId' => $spId,
                'tranId' => $tranId,
                'createTranTime' => date('YmdHis'),
            ])->submit();

            $response = array_get($response, 'response.data');
            $isExist = array_get($response, 'isExist');
            $status = array_get($response, 'status');

            if ($isExist == 1 && $status == 1) {
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