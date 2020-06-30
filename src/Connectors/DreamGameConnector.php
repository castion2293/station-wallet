<?php

namespace SuperPlatform\StationWallet\Connectors;

use Carbon\Carbon;
use GuzzleHttp\Client;
use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationLoginRecord as LoginRecord;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * 「DG」帳號錢包連接器
 *
 * @package SuperPlatform\StationWallet\Connectors
 */
class DreamGameConnector extends Connector
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

    protected $guzzleClient;

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->station = 'dream_game';
        $this->console = new ConsoleOutput();
        $this->guzzleClient = new Client();

        parent::__construct();
        $this->currency = config('api_caller.dream_game.config.currency');
        $this->deposit_limit_map = config("api_caller.dream_game.deposit_limit");
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
                /**
                 * data is 限紅 TWD
                 *   A 100-250000
                 *   B 50-5000 # 最低限紅
                 *   C 50-10000
                 *   D 100-10000
                 *   E 100-20000
                 *   F 100-50000
                 *   G 100-100000
                 */

                'data' => config('api_caller.dream_game.config.api_member_betting_limit'),

                /**
                 * json
                 * {
                 *    属性名        属性类型     属性说明
                 *    ----------- require -----------
                 *    username     String      会员登入账号
                 *    password     String      会员密码（MD5）
                 *    currency     String      会员货币简称
                 *    winLimit     Double      会员当天最大可赢取金额[仅统计当天下注], < 1表示无限制
                 *    ----------- optional -----------
                 *    status       Integer     会员状态：0:停用, 1:正常, 2:锁定(不能下注) (optional: default 1)
                 *    balance      Double      会员余额 (optional: default 0)
                 * }
                 */
                'member' => [
                    'username' => $wallet->account,
                    'password' => $wallet->password,
                    'currencyName' => array_get($params, 'currency', $this->currency),
                    'winLimit' => config('api_caller.dream_game.config.api_member_winning_limit'),
                ],
            ],
            'route_params' => [
                'agent' => $this->config['build']['agent'],
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
            if ($logException->getMessage() !== 116) {
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
                'member' => [
                    'username' => $wallet->account,
                ],
            ],
            'route_params' => [
                'agent' => $this->config['build']['agent'],
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
            $balance = (string)array_get(array_get($response['response'], 'member'), 'balance');
            // 戳API成功則寫入log並return
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
            if(array_get($exception->response(), 'codeId') === 114) {
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
        // 由於DG各幣別有對應的轉換比例 例如:娛樂城 1000VD = DG遊戲內 1VD
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'deposit';
            // 訪問 parameters
            $transferNum = str_random();
            // 將欲轉入之點數轉換成 遊戲館對應比例
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            $formParams = [
                'form_params' => [
                    'data' => $transferNum,
                    'member' => [
                        'username' => $wallet->account,
                        'amount' => $amount,
                    ],
                ],
                'route_params' => [
                    'agent' => $this->config['build']['agent'],
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
                $balance = (string)array_get(array_get($response['response'], 'member'), 'balance');
                // 戳API成功則寫入log並return
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
                // currency_multiply_transfer() 此helper用於轉換 DG點數 對應各幣別的比例換算結果
                // currency_multiply_transfer() 寫在 api-caller 套件 Helpers/CurrencyHelper.php 內
                return $this->responseMerge($response, [
                    'balance' => currency_multiply_transfer(data_get($wallet, "station"), $balance),
                ]);
            } catch (\Exception $exception) {
                show_exception_message($exception);
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
                if(array_get($exception->response(), 'codeId') === 323) {
                    throw $this->formatException($exception);
                }
                sleep(1);
                $checkTransferSuccess = $this->checkTransfer([
                    'agent' => $this->config['build']['agent'],
                    'transfer_num' => $transferNum
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
        // 由於DG各幣別有對應的轉換比例 例如:娛樂城 1000VD = DG遊戲內 1VD
        // 這裡需檢查欲轉入的金額是否符合, 幣別對應的最小轉入單位
        if ($amount % array_get($this->deposit_limit_map, "{$this->currency}", 1) == 0) {
            $requestId = str_random();
            // 訪問 action
            $action = 'withdraw';
            // 訪問 parameters
            $transferNum = str_random();
            // 將欲轉入之點數轉換成 遊戲館對應比例
            $amount = currency_divide_transfer(data_get($wallet, "station"), $amount);
            $formParams = [
                'form_params' => [
                    'data' => $transferNum,
                    'member' => [
                        'username' => $wallet->account,
                        'amount' => -$amount,
                    ],
                ],
                'route_params' => [
                    'agent' => $this->config['build']['agent'],
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
                $balance = (string)array_get(array_get($response['response'], 'member'), 'balance');
                // 戳API成功則寫入log並return
                $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance, $amount);
                // currency_multiply_transfer() 此helper用於轉換 DG點數 對應各幣別的比例換算結果
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
                
                if(array_get($exception->response(), 'codeId') === 323) {
                    throw $this->formatException($exception);
                }
                // 再次檢查轉帳流水號，確認轉帳是否真的失敗，如果成功就不需回傳錯誤訊息
                sleep(1);
                $checkTransferSuccess = $this->checkTransfer([
                    'agent' => $this->config['build']['agent'],
                    'transfer_num' => $transferNum
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
        $lang = array_get($params, 'language', $this->config['build']['language']);
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                'language' => $lang,
                'member' => [
                    'username' => $wallet->account,
                    'password' => $wallet->password,
                ],
            ],
            'route_params' => [
                'agent' => $this->config['build']['agent'],
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
        $list = array_get($data, 'list');
        $flash = $list[0];
        $h5 = $list[1];
        // $app = $list[2];

        /**
         * 登入地址類型：
         *
         * "list":["flash 登入地址","wap 登入地址","直接打开APP地址"]
         *   array(3) {
         *     // wap (Flash) 登入地址
         *     0 => "https://a.2023168.com/?token="
         *     // wap (H5) 登入地址
         *     1 => "https://dg-asia.lyhfzb.com/wap/index.html?token="
         *     // APP 地址
         *     2 => "http://f.wechat668.com/download/cn.html?t="
         *   }
         * ---------
         * 1.返回的數據僅當 codeId = 0 時 token 為有效 token,
         * 2.進入游戲地址為游戲地址加上 token, 例如:
         *    PC 瀏覽器進入游戲: list[0] + token
         *    手機瀏覽器進入游戲: list[1] + token + &language=lang
         */
        return $this->responseMerge($response, [
            'method' => 'redirect',
            'web_url' => $flash . array_get($data, 'token'),
            'mobile_url' => $h5 . array_get($data, 'token') . '&language=' . $lang . config('station_wallet.stations.dream_game.passport.hideMobileAppLogo'),
            'params' => []
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
            $agent = array_get($params, 'agent');
            $transferNum = array_get($params, 'transfer_num');

            $response = ApiCaller::make('dream_game')->methodAction('post', 'account/checkTransfer/{agent}', [
                // 路由參數這邊設定
                'agent' => $agent
            ])->params([
                // 一般參數這邊設定
                // 轉帳流水號
                'data' => $transferNum,
            ])->submit();

            $response = array_get($response, 'response');

            if (array_get($response, 'codeId') == 0) {
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