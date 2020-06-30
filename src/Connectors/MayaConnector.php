<?php

namespace SuperPlatform\StationWallet\Connectors;

use Illuminate\Support\Facades\Log;
use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationLoginRecord as LoginRecord;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * 「瑪雅」帳號錢包連接器
 *
 * @package SuperPlatform\StationWallet\Connectors
 */
class MayaConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station 瑪雅遊戲站名稱
     */
    protected $station;

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->station = 'maya';
        $this->console = new ConsoleOutput();

        parent::__construct();
    }

    /**
     * 建立本地錢包對應遊戲站「帳號」
     *
     * @param Wallet $wallet
     * @param array $params 參照表參數
     *      * [
     *  'VenderNo' 代理商編碼
     *  'SiteNo' 分站編碼：用於區分不同站的會員
     *  'VenderMembrID' 代理商會員主鍵 ID
     *  'MemberName' 代理商會員帳號
     *  'TestState' 帳號類型 (0: 正式 1:測試，測試最多只可建 5 個)
     *  'CurrencyNo' 貨幣編碼 (USD, RMB, THB, TWD, MYR, IDR, SGD, KRW)
     *
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
                'VenderNo' => data_get($params, 'VenderNo', $this->config['build']['VenderNo']),
                'SiteNo' => data_get($params, 'SiteNo', 'test'),
                'VenderMemberID' => $wallet->account,
                'MemberName' => $wallet->account,
                'TestState' => '0',
                'CurrencyNo' => data_get($params, 'CurrencyNo', 'TWD')
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
                'account' => data_get(data_get($response, 'response'), 'GameMemberID')
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
            if ($logException->getMessage() !== 11028 ) {
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
                'vender_no' => data_get($params, 'VenderNo', $this->config['build']['VenderNo']),
                'game_identifies' => $this->getGameMemberID($wallet),
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
            $balance = (string)$response['response']['MemberBalanceList'][0]['Balance'];
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
        $venderTransactionID = 'IN' . date('YmdHis') . $wallet->account;
        $formParams = [
            'form_params' => [
                /* required */
                'VenderNo' => $this->config['build']['VenderNo'],
                'GameMemberID' => $this->getGameMemberID($wallet),
                'VenderTransactionID' => $venderTransactionID,
                // ID:IN+YYYYMMDDHHMMSS+Username
                'Amount' => $amount,
                'Direction' => 'in',
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
            $balance = (string)array_get(array_get($response, 'response'), 'AfterBalance');
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
                'vender_no' => $this->config['build']['VenderNo'],
                'vender_transaction_id' => $venderTransactionID
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
        $venderTransactionID = 'OUT' . date('YmdHis') . $wallet->account;
        $formParams = [
            'form_params' => [
                /* required */
                'VenderNo' => $this->config['build']['VenderNo'],
                'GameMemberID' => $this->getGameMemberID($wallet),
                'VenderTransactionID' => $venderTransactionID,
                // ID:IN+YYYYMMDDHHMMSS+Username
                'Amount' => $amount,
                'Direction' => 'out',
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
            $balance = (string)array_get(array_get($response, 'response'), 'AfterBalance');
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
                'vender_no' => $this->config['build']['VenderNo'],
                'vender_transaction_id' => $venderTransactionID
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
        $gameMemberID = $this->getGameMemberID($wallet);
        $token = $this->config['build']['VenderNo'] . '_' . $gameMemberID . '_' .
            'login_at' . '_' . date('Y_m_d_H_i_s');
        $formParams = [
            'form_params' => [
                /* required */
                'vender_no' => $this->config['build']['VenderNo'],
                'game_identify' => $gameMemberID,
                'account' => $wallet->account,
                'normal_handicaps' => data_get($params, 'normal_handicaps', $this->config['build']['GameConfigId']),
                'language' => data_get($params, 'language', 'zh_tw'),
                'pass_token' => $token,
                /* optional */
                'host' => data_get($params, 'host', null),
                'page_style' => data_get($params, 'page_style', null),
                'show_recharge' => data_get($params, 'show_recharge', null),
                'open_url' => data_get($params, 'open_url', null),
                'open_back_url' => data_get($params, 'open_back_url', null),
                'is_trial' => data_get($params, 'is_trial', null),
                'entry_type' => data_get($params, 'entry_type', null),
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
            'method' => 'redirect',
            'web_url' => 'http://' . array_get($response, 'response.InGameUrl'),
            'mobile_url' => '',
            'params' => [
                'ErrorCode' => array_get($response, 'response.ErrorCode'),
            ]
        ]);
    }

    /**
     * 取得瑪雅方的會員遊戲 ID (戳其他 API 需要)
     *
     * @param Wallet $wallet 錢包
     * @param array $params 參數
     * @return array|float
     * @throws \Exception
     */
    public function getGameMemberID(Wallet $wallet, $params = [])
    {
        // 訪問 parameters
        $formParams = [
            // 一般參數這邊設定
            'VenderNo' => data_get($params, 'VenderNo', $this->config['build']['VenderNo']),
            'VenderMemberID' => $wallet->account,
        ];
        try {
            $gameMemberID = ApiCaller::make('maya')->methodAction('get', 'GetGameMemberID', [
                // 路由參數這邊設定
            ])->params(
                $formParams
            )->submit()['response']['GameMemberID'];

        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                'GetGameMemberID',
                $formParams
            ));
            throw $this->formatException($exception);
        }
        return $gameMemberID;
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
            $venderNo = array_get($params, 'vender_no');
            $venderTransactionID = array_get($params, 'vender_transaction_id');

            $response = ApiCaller::make('maya')->methodAction('get', 'CheckFundTransfer', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'VenderNo' => $venderNo,
                'VenderTransactionID' => $venderTransactionID,
            ])->submit();

            $response = array_get($response, 'response');

            if (array_get($response, 'ErrorCode') == 0) {
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
            $gameMemberID = $this->getGameMemberID($wallet);

            $response = ApiCaller::make('maya')->methodAction('get', 'KickMembers', [
                // 路由參數這邊設定
            ])->params([
                // 一般參數這邊設定
                'VenderNo' => config('api_caller.maya.config.property_id'),
                'GameMemberIDs' => $gameMemberID
            ])->submit();

            $response = array_get($response, 'response');

            if (array_get($response, 'ErrorCode') == 0) {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                'KickMembers',
                []
            ));
            throw $this->formatException($exception);
        }
    }
}