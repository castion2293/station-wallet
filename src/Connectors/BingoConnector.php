<?php

namespace SuperPlatform\StationWallet\Connectors;

use Illuminate\Support\Facades\Log;
use SuperPlatform\ApiCaller\Facades\ApiPoke;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\Models\StationLoginRecord as LoginRecord;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * 「bingo」帳號錢包連接器
 *
 * @package SuperPlatform\StationWallet\Connectors
 */
class BingoConnector extends Connector
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * @var string $station bingo遊戲站名稱
     */
    protected $station;

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->station = 'bingo';
        $this->console = new ConsoleOutput();

        parent::__construct();
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
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                /* required */
                'account' => $wallet->account,
                'password' => $wallet->password,
                'password_again' => $wallet->password,
                'name' => data_get($params, 'name', $wallet->account),
                /* optional */
                'day_max_win' => data_get($params, 'day_max_win'),
                'day_max_lost' => data_get($params, 'day_max_lost'),
                'muster' => data_get($params, 'muster'),
                'remark' => data_get($params, 'remark')
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
            $httpCode = $exception->getCode();
            $arrayData = $exception->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action);
            throw $exception;
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
            $balance = (string)array_get($response['response'], 'balance');
            // 戳API成功則寫入log並return
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $balance);
            return $this->responseMerge($response, [
                'balance' => $balance
            ]);
        } catch (\Exception $exception) {
            $this->beforeRequestLog($wallet, $requestId, json_encode($formParams), $action);
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $this->station,
                $action,
                $formParams
            ));
            $httpCode = $exception->getCode();
            $arrayData = $exception->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action);
            throw $exception;
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
                'point' => $amount,
            ],
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
            $balance = (string)array_get($response['response'], 'after_balance');
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
            $httpCode = $exception->getCode();
            $arrayData = $exception->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);
            throw $exception;
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
                'point' => $amount,
            ],
            'route_params' => [
                /* required */
                'account' => $wallet->account,
            ],
        ];
        try {
            $response = ApiPoke::poke(
                $this->station,
                $action,
                $formParams
            );
            $httpCode = array_get($response, 'http_code');
            $arrayData = json_encode(array_get($response, 'response'));
            $balance = (string)array_get($response['response'], 'after_balance');
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
            $httpCode = $exception->getCode();
            $arrayData = $exception->getMessage();
            // 若發生錯誤則顯示失敗並寫入log
            $this->afterResponseLog($wallet, $requestId, $httpCode, $arrayData, $action, $amount);
            throw $exception;
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
                /* optional */
                'language' => array_get($params, 'language'),
                'expires_in' => array_get($params, 'expires_in')
            ],
            'route_params' => [
                /* required */
                'account' => $wallet->account,
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
            throw $exception;
        }

        $data = $response['response'];

        $playUrl = array_get($data, 'play_url');
        $mobileUrl = array_get($data, 'mobile_url');
        if (!empty($this->config['scheme']) && $this->config['scheme'] === 'http') {
            $playUrl = str_replace('https', $this->config['scheme'], $playUrl);
            $mobileUrl = str_replace('https', $this->config['scheme'], $mobileUrl);
        }

        return $this->responseMerge($response, [
            'method' => 'redirect',
            'web_url' => $playUrl,
            'mobile_url' => $mobileUrl,
        ]);
    }

    /**
     * 取得遊戲站帳號「限紅」設定
     *
     * @param Wallet $wallet 錢包
     * @return array|float
     * @throws \Exception
     */
    public function betLimit(Wallet $wallet)
    {
        // 訪問 action
        $action = 'getBetLimits';
        // 訪問 parameters
        $formParams = [
            'form_params' => [
                'ticket_limits' => [
                    /* optional */
                    //一般玩法：單、雙、平，可設定: bet_max(上限)，bet_min(下限)
                    'normal_odd_even_draw' => [
                        'bet_min' => config('station_wallet.stations.bingo.getLimit.normal_odd_even_draw.normal_odd_even_draw_bet_min'),
                        'bet_max' => config('station_wallet.stations.bingo.getLimit.normal_odd_even_draw.normal_odd_even_draw_bet_max')
                    ],
                    //一般玩法：大、小、合，可設定: bet_max(上限)，bet_min(下限)
                    'normal_big_small_tie' => [
                        'bet_min' => config('station_wallet.stations.bingo.getLimit.normal_big_small_tie.normal_big_small_tie_min'),
                        'bet_max' => config('station_wallet.stations.bingo.getLimit.normal_big_small_tie.normal_big_small_tie_max'),
                    ],
                    //超級玩法(特別號)：大、小，可設定: bet_max(上限)，bet_min(下限)
                    'super_big_small' => [
                        'bet_min' => config('station_wallet.stations.bingo.getLimit.super_big_small.super_big_small_min'),
                        'bet_max' => config('station_wallet.stations.bingo.getLimit.super_big_small.super_big_small_min'),
                    ],
                    //超級玩法(特別號)：單、雙，可設定: bet_max(上限)，bet_min(下限)
                    'super_odd_even' => [
                        'bet_min' => config('station_wallet.stations.bingo.getLimit.super_odd_even.super_odd_even_min'),
                        'bet_max' => config('station_wallet.stations.bingo.getLimit.super_odd_even.super_odd_even_max'),
                    ],
                    //超級玩法(特別號)：獨猜，可設定: bet_max(上限)，bet_min(下限)
                    'super_guess' => [
                        'bet_min' => config('station_wallet.stations.bingo.getLimit.super_guess.super_guess_min'),
                        'bet_max' => config('station_wallet.stations.bingo.getLimit.super_guess.super_guess_max'),
                    ],
                    //星號，可設定: bet_max(上限)，bet_min(下限)
                    'star' => [
                        'bet_min' => config('station_wallet.stations.bingo.getLimit.star.star_min'),
                        'bet_max' => config('station_wallet.stations.bingo.getLimit.star.star_max'),
                    ],
                    //五行，可設定: bet_max(上限)，bet_min(下限)
                    'elements' => [
                        'bet_min' => config('station_wallet.stations.bingo.getLimit.elements.elements_min'),
                        'bet_max' => config('station_wallet.stations.bingo.getLimit.elements.elements_max'),
                    ],
                    //四季，可設定: bet_max(上限)，bet_min(下限)
                    'seasons' => [
                        'bet_min' => config('station_wallet.stations.bingo.getLimit.seasons.seasons_min'),
                        'bet_max' => config('station_wallet.stations.bingo.getLimit.seasons.seasons_max'),
                    ],
                    //不出球，可設定: bet_max(上限)，bet_min(下限)
                    'other_fanbodan' => [
                        'bet_min' => config('station_wallet.stations.bingo.getLimit.other_fanbodan.other_fanbodan_min'),
                        'bet_max' => config('station_wallet.stations.bingo.getLimit.other_fanbodan.other_fanbodan_max'),
                    ],
                ]
            ],
            'route_params' => [
                /* required */
                'account' => $wallet->account,
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
            throw $exception;
        }

        return $this->responseMerge($response, [
            'limit' => $response['response']
        ]);
    }
}