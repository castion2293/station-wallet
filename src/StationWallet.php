<?php

namespace SuperPlatform\StationWallet;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use SuperPlatform\StationWallet\Events\SyncDone;
use SuperPlatform\StationWallet\Exceptions\BalanceNotEnoughException;
use SuperPlatform\StationWallet\Exceptions\ConnectorClassNotExistsException;
use SuperPlatform\StationWallet\Exceptions\NoResponseException;
use SuperPlatform\StationWallet\Exceptions\TransferFailureException;
use SuperPlatform\StationWallet\Exceptions\WalletNotFoundException;
use SuperPlatform\StationWallet\Facades\StationWalletConnector;
use SuperPlatform\StationWallet\Models\StationLoginRecord as LoginRecord;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * 遊戲站錢包管理（服務）器，提供方法執行錢包相關操作，餘額操作需套用交易模式
 */
class StationWallet
{
    /**
     * @var ConsoleOutput 輸出錯誤訊息
     */
    protected $console;

    /**
     * 遊戲站連結器集合
     *
     * @var array
     */
    protected $connectors = [];

    /**
     * constructor.
     */
    public function __construct()
    {
        // 輸出資訊
        $this->console = new ConsoleOutput();

        // 初始化各遊戲站的連結器
        $stations = collect(config('api_caller'))->reject(function ($item) {
            return empty(array_get($item, 'config'));
        })->keys();
        $this->initStationConnectors($stations);
    }

    /**
     * 註冊服務路由
     *
     * @param callable|null $callback
     * @param array $options
     * @return void
     */
    public static function routes($callback = null, array $options = [])
    {
        $callback = $callback ?: function ($router) {
            $router->all();
        };
        $defaultOptions = [
            'namespace' => '\SuperPlatform\StationWallet\Http\Controllers',
        ];
        $options = array_merge($defaultOptions, $options);
        Route::group($options, function ($router) use ($callback) {
            $callback(new RouteRegistrar($router));
        });
    }

    /**
     * 載入(指定遊戲站/所有)連結器
     *
     * @param array|string|null $stations
     */
    public function initStationConnectors($stations = null)
    {
        $stations = collect($stations);

        if ($stations->isNotEmpty()) {

            $stations->each(function ($station) {
                try {
                    $this->connectors[$station] = StationWalletConnector::make($station);
                } catch (ConnectorClassNotExistsException $exception) {
                    $this->console->writeln($exception->getMessage());
                }
            });

        } else {
            foreach (array_keys(config('api_caller')) as $station) {
                try {
                    $this->connectors[$station] = StationWalletConnector::make($station);
                } catch (ConnectorClassNotExistsException $exception) {
                    $this->console->writeln($exception->getMessage());
                }
            }
        }
    }

    /**
     * 透過錢包識別碼取得錢包實體
     *
     * @param string $walletId
     * @return Wallet 遊戲錢包
     * @throws Exception
     */
    public static function getWallet(string $walletId)
    {
        try {
            return Wallet::find($walletId)->firstOrFail();
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }
    }

    /**
     * 透過遊戲站名取得錢包實體
     *
     * @param string $station
     * @return collection
     * @throws Exception
     */
    public static function getWalletsByStation(string $station)
    {
        try {
            return Wallet::where('station', '=', $station)
                ->get();
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }
    }

    /**
     * 透過錢包ID之陣列取得錢包實體
     * (因為ID已為唯一值，所以不用再抓取站別)
     *
     * @param array $ids
     * @return mixed
     * @throws Exception
     */
    public static function getWalletsById(array $ids)
    {
        try {
            return Wallet::whereIn('id', $ids)
                ->get();
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }
    }

    /**
     * 透過本機錢包資料建立遊戲站帳號
     *
     * @param Wallet $wallet
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    public function build(Wallet $wallet, array $params = [])
    {
        try {
            $buildResult = $this->connectors[$wallet->station]->build($wallet, $params);
            // 如果 caller 未實作，可能會回傳 null，丟出例外
            if (is_null($buildResult)) {
                throw new NoResponseException($wallet->station);
            }

            // 再戳遊戲錢包餘額 確保遊戲方有開通錢包
//            sleep(1);
//            $balance = array_get($this->connectors[$wallet->station]->balance($wallet, $params), 'balance');
//            if ($balance != 0) {
//                throw new NoResponseException($wallet->station);
//            }

            // 如果創建成功，開通錢包狀態
            $wallet->activated_status = 'yes';
            $wallet->save();
        } catch (\Exception $exception) {
            // show_exception_message($exception);
            throw $exception;
        }
        return $buildResult;
    }

    /**
     * 透過本機錢包資料取得遊戲站錢包餘額
     *
     * @param Wallet $wallet
     * @param array $params
     * @return mixed
     */
    public function balance(Wallet $wallet, $params = [])
    {
        // 如果目標錢包是「主錢包」沒有同步的需要，直接回傳目標錢包餘額
        if ($wallet->station == config('station_wallet.master_wallet_name')) {
            return $wallet->balance;
        }

        // 取得遠端遊戲錢包的餘額並回寫至本地遊戲錢包
        $walletConnector = $this->connectors[$wallet->station];
        $wallet->balance = array_get($walletConnector->balance($wallet, $params), 'balance');
        $wallet->save();
        return $wallet->balance;
    }

    /**
     * 同步遊戲站帳號餘額到本機錢包資料
     *
     * @param Wallet $wallet 欲處理的錢包
     * @param bool $dispatchEvent 是否發送同步完成事件
     * @return Wallet 同步完成的錢包
     * @throws Exception
     */
    public function sync(Wallet $wallet, $dispatchEvent = false)
    {
        // 暫存同步化之前的餘額
        $beforeBalance = $wallet->balance;

        // 將錢包餘額更新到同步後的餘額
        $wallet->balance = $this->balance($wallet);

        // 將同步狀態改回 "非同步中" 並記錄最後同步時間
        $wallet->sync_status = 'free';
        $wallet->last_sync_at = date("Y-m-d H:i:s");
        $wallet->save();

        // 發送錢包完成同步事件
        if ($dispatchEvent) {
            event(new SyncDone($wallet, $beforeBalance, $wallet->balance));
        }

        return $wallet;
    }

    /**
     * 透過本機錢包資料進行「充值」
     *
     * @param Wallet $wallet
     * @param float $amount
     * @return array
     * @throws Exception
     */
    public function deposit(Wallet $wallet, float $amount)
    {
        // 餘額變動記錄器
        $balance = [
            'sync' => [
                'before' => $wallet->balance,
                'after' => $wallet->balance,
                'variation' => 0
            ],
            'action' => [
                'before' => $wallet->balance,
                'after' => $wallet->balance,
                'variation' => 0,
            ],
        ];

        // 如果充值金額為 0 以下就不處理
        // 因為有些 API 不接受 0 元補點)
        if ($amount <= 0) {
            return [
                'wallet' => $wallet,
                'balance' => $balance
            ];
        }


        if ($wallet->station == config('station_wallet.master_wallet_name')) {
            // -- 同步 --
            // 處理目標是「主錢包」，就不需要遠端同步的動作

            // -- 動作(充值) --
            $balance['action']['before'] = $wallet->balance;
            $wallet->balance = $wallet->balance + $amount;
            $balance['action']['after'] = $wallet->balance;
            $balance['action']['variation'] = $balance['action']['after'] - $balance['action']['before'];
            $wallet->save();
        } else {
            // -- 同步 --
            // 處理目標是「遊戲錢包」，需要在充值之前進行遠端同步
            $wallet = $this->sync($wallet);
            $balance['sync']['after'] = $wallet->balance;
            $balance['sync']['variation'] = $balance['sync']['after'] - $balance['sync']['before'];

            // -- 動作(充值) --
            $balance['action']['before'] = $balance['sync']['after'];
            $balance['action']['variation'] = $amount;
            $balance['action']['after'] = $balance['action']['before'] + $balance['action']['variation'];

            $this->connectors[$wallet->station]->adjust($wallet, $balance['action']['after'], []);
            $wallet->balance = $balance['action']['after'];
            $wallet->save();
        }

        return [
            'wallet' => $wallet,
            'balance' => $balance
        ];
    }

    /**
     * 透過本機錢包資料進行點數「回收」
     *
     * @param Wallet $wallet
     * @param float $amount
     * @return array
     * @throws Exception
     */
    public function withdraw(Wallet $wallet, float $amount)
    {
        // 餘額變動記錄器
        $balance = [
            'sync' => [
                'before' => $wallet->balance,
                'after' => $wallet->balance,
                'variation' => 0
            ],
            'action' => [
                'before' => $wallet->balance,
                'after' => $wallet->balance,
                'variation' => 0,
            ],
        ];

        // 如果充值金額為 0 以下就不處理
        // 因為有些 API 不接受 0 元補點)
        if ($amount <= 0) {
            return [
                'wallet' => $wallet,
                'balance' => $balance
            ];
        }

        if ($wallet->station == config('station_wallet.master_wallet_name')) {
            // -- 同步 --
            // !! 處理目標是「主錢包」，就不需要遠端同步的動作 !!

            // -- 動作(回收) --
            $balance['action']['before'] = $wallet->balance;
            $wallet->balance = $wallet->balance - $amount;
            $balance['action']['after'] = $wallet->balance;
            $balance['action']['variation'] = $balance['action']['after'] - $balance['action']['before'];
            $wallet->save();
        } else {
            // -- 同步 --
            // 處理目標是「遊戲錢包」，需要在充值之前進行遠端同步
            $wallet = $this->sync($wallet);
            $balance['sync']['after'] = $wallet->balance;
            $balance['sync']['variation'] = $balance['sync']['after'] - $balance['sync']['before'];

            // -- 動作(回收) --
            $balance['action']['before'] = $balance['sync']['after'];
            $balance['action']['variation'] = -$amount;
            $balance['action']['after'] = $balance['action']['before'] + $balance['action']['variation'];

            $this->connectors[$wallet->station]->adjust($wallet, $balance['action']['after'], []);
            $wallet->balance = $balance['action']['after'];
            $wallet->save();
        }

        return [
            'wallet' => $wallet,
            'balance' => $balance
        ];
    }

    /**
     * 透過本機錢包資料進行點數「調整」
     *
     * @param Wallet $wallet
     * @param float $finalBalance
     * @return array
     * @throws Exception
     */
    public function adjust(Wallet $wallet, float $finalBalance)
    {
        // 餘額變動記錄器
        $balance = [
            'sync' => [
                'before' => $wallet->balance,
                'after' => $wallet->balance,
                'variation' => 0
            ],
            'action' => [
                'before' => $wallet->balance,
                'after' => $wallet->balance,
                'variation' => 0,
            ],
        ];

        // -- 同步 --
        $wallet = $this->sync($wallet);
        $balance['sync']['after'] = $wallet->balance;
        $balance['sync']['variation'] = $balance['sync']['after'] - $balance['sync']['before'];

        // -- 動作(回收) --
        $balance['action']['before'] = $balance['sync']['after'];
        $balance['action']['variation'] = $finalBalance - $balance['action']['before'];
        $balance['action']['after'] = $finalBalance;

        $this->connectors[$wallet->station]->adjust($wallet, $balance['action']['after'], []);
        $wallet->balance = $balance['action']['after'];
        $wallet->save();

        return [
            'wallet' => $wallet,
            'balance' => $balance
        ];
    }


    /**
     * 同步多個錢包，並回傳這些錢包的總餘額
     *
     * @param $wallets
     * @return array
     * @throws Exception
     */
    public function syncWallets($wallets)
    {
        $totalBalance = 0;
        $result = [];

        foreach ($wallets as $wallet) {
            try {
                if ($wallet->station == config('station_wallet.master_wallet_name')) {
                    $balanceResult = $wallet->balance;
                } else {
                    // 同步錢包
                    $wallet = $this->sync($wallet);
                    $balanceResult = $wallet->balance;
                }
                $result[$wallet->station] = number_format($balanceResult, 2, '.', '');
                $totalBalance += $balanceResult;
            } catch (\Exception $exception) {
                // show_exception_message($exception);
                // Log::error($exception->getTraceAsString());
                $result[$wallet->station] = '發生錯誤';
                continue;
            }
        }

        return [
            'detail' => $result,
            'totalBalance' => number_format($totalBalance, 2, '.', ''),
        ];
    }

    /**
     * 將錢包的 A 站點數 轉到 B 站
     *
     * @param $fromWallet Wallet 欲轉點錢包
     * @param $toWallet Wallet 轉點至錢包
     * @param $amount float 金額
     * @return array
     * @throws Exception
     */
    public function transfer($fromWallet, $toWallet, $amount)
    {
        // 來源餘額變動記錄器
        $fromBalance = [
            'sync' => [
                'before' => $fromWallet->balance,
                'after' => $fromWallet->balance,
                'variation' => 0
            ],
            'action' => [
                'before' => $fromWallet->balance,
                'after' => $fromWallet->balance,
                'variation' => 0,
                'amend' => 0,
            ],
        ];
        // 目標餘額變動記錄器
        $toBalance = [
            'sync' => [
                'before' => $toWallet->balance,
                'after' => $toWallet->balance,
                'variation' => 0
            ],
            'action' => [
                'before' => $toWallet->balance,
                'after' => $toWallet->balance,
                'variation' => 0,
                'amend' => 0,
            ],
        ];

        // 用來記錄「遊戲錢包」轉「遊戲錢包」的中斷點
        $breakPoint = null;

        try {
            // 更新「來源同步」錢包資訊
            $fromWallet = $this->sync($fromWallet);
            $fromBalance['sync']['after'] = $fromWallet->balance;
            $fromBalance['sync']['variation'] = $fromBalance['sync']['after'] - $fromBalance['sync']['before'];

            // 更新「目標同步」錢包資訊
            $toWallet = $this->sync($toWallet);
            $toBalance['sync']['after'] = $toWallet->balance;
            $toBalance['sync']['variation'] = $toBalance['sync']['after'] - $toBalance['sync']['before'];

            // --- 若點數不足以轉點，拋出例外
            if ($fromBalance['sync']['after'] < 0 || $fromBalance['sync']['after'] < $amount) {
                throw new BalanceNotEnoughException;
            }

            // --- 檢查主錢包是否存在
            $masterWallet = Wallet::where('station', config('station_wallet.master_wallet_name'))
                ->where('user_id', $fromWallet->user_id)
                ->first();
            if (is_null($masterWallet)) {
                throw new WalletNotFoundException;
            }

            // --- 動作(交易) ---
            $fromBalance['action']['before'] = $fromWallet->balance;
            $fromBalance['action']['before'] = $fromBalance['sync']['after'];
            $fromBalance['action']['variation'] = -$amount;
            $fromBalance['action']['after'] = $fromBalance['action']['before'] + $fromBalance['action']['variation'];

            $toBalance['action']['before'] = $toWallet->balance;
            $toBalance['action']['before'] = $toBalance['sync']['after'];
            $toBalance['action']['variation'] = $amount;
            $toBalance['action']['after'] = $toBalance['action']['before'] + $toBalance['action']['variation'];

            if ($fromWallet->station == config('station_wallet.master_wallet_name')) {
                // 【主錢包 -> 其他錢包】
                $breakPoint = [
                    'amount' => $amount,
                    'from' => [
                        'id' => $masterWallet->id,
                        'station' => $masterWallet->station,
                        'use_id' => $masterWallet->user_id,
                        'account' => $masterWallet->account,
                    ],
                    'to' => [
                        'id' => $toWallet->id,
                        'station' => $toWallet->station,
                        'use_id' => $toWallet->user_id,
                        'account' => $toWallet->account,
                    ],
                ];

                $breakPoint['break'] = 'from';
                $masterWallet->balance = $fromBalance['action']['after'];
                $masterWallet->save();

                $breakPoint['break'] = 'to';
                $toWalletConnector = $this->connectors[$toWallet->station];
                $toWalletConnector->adjust($toWallet, $toBalance['action']['after'], []);
                $toWallet->balance = $toBalance['action']['after'];
                $toWallet->save();

                $breakPoint = null;
            } elseif ($toWallet->station == config('station_wallet.master_wallet_name')) {
                // 【其他錢包 -> 主錢包】
                $breakPoint = [
                    'amount' => $amount,
                    'from' => [
                        'id' => $fromWallet->id,
                        'station' => $fromWallet->station,
                        'use_id' => $fromWallet->user_id,
                        'account' => $fromWallet->account,
                    ],
                    'to' => [
                        'id' => $masterWallet->id,
                        'station' => $masterWallet->station,
                        'use_id' => $masterWallet->user_id,
                        'account' => $masterWallet->account,
                    ],
                ];

                $breakPoint['break'] = 'from';
                $fromWalletConnector = $this->connectors[$fromWallet->station];
                $fromWalletConnector->adjust($fromWallet, $fromBalance['action']['after'], []);
                $fromWallet->balance = $fromBalance['action']['after'];
                $fromWallet->save();

                $breakPoint['break'] = 'to';
                $masterWallet->balance = $toBalance['action']['after'];
                $masterWallet->save();

                $breakPoint = null;
            } else {
                // 【其他錢包 -> 其他錢包】
                $breakPoint = [
                    'amount' => $amount,
                    'from' => [
                        'id' => $fromWallet->id,
                        'station' => $fromWallet->station,
                        'use_id' => $fromWallet->user_id,
                        'account' => $fromWallet->account,
                    ],
                    'to' => [
                        'id' => $toWallet->id,
                        'station' => $toWallet->station,
                        'use_id' => $toWallet->user_id,
                        'account' => $toWallet->account,
                    ],
                ];
                // 先從來源錢包扣點
                $breakPoint['break'] = 'from';
                $fromWalletConnector = $this->connectors[$fromWallet->station];
                $fromWalletConnector->adjust($fromWallet, $fromBalance['action']['after'], []);
                $fromWallet->balance = $fromBalance['action']['after'];
                $fromWallet->save();

                // 將點數補至目標錢包
                $breakPoint['break'] = 'to';
                $toWalletConnector = $this->connectors[$toWallet->station];
                $toWalletConnector->adjust($toWallet, $toBalance['action']['after'], []);
                $toWallet->balance = $toBalance['action']['after'];
                $toWallet->save();

                $breakPoint = null;
            }

        } catch (BalanceNotEnoughException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            // 轉點發生例外
            if ($breakPoint) {

                // 如果斷點是 to，要將 from 的點數補回去
                if ($breakPoint['break'] === 'to') {
                    if($breakPoint['from']['station'] != 'master') {
                        $fromWalletConnector = $this->connectors[$breakPoint['from']['station']];
                        $fromWalletConnector->adjust(
                            $fromWallet,
                            $fromBalance['action']['after'] + $amount,
                            []
                        );
                    } else {
                        $masterWallet->balance = $masterWallet->balance + $amount;
                        $masterWallet->save();
                    }
                }
                throw new TransferFailureException($breakPoint);
            } else {
                throw new \Exception("資料錯誤 請聯繫客服: {$exception->getMessage()}");
            }
        }

        return [
            'result' => true,
            'from' => [
                'wallet' => $fromWallet,
                'balance' => $fromBalance,
            ],
            'to' => [
                'wallet' => $toWallet,
                'balance' => $toBalance,
            ],
        ];
    }

    /**
     * 傳入本機錢包資料設為啟用
     *
     * @param $wallets
     * @return Collection
     * @throws Exception
     */
    public function active($wallets)
    {
        try {
            $wallets = collect($wallets);
            $wallets->each(function ($wallet) {
                $wallet->status = 'active';
                $wallet->save();
            });
        } catch (\Exception $exception) {
            throw $exception;
        }

        return $wallets;
    }

    /**
     * 傳入本機錢包資料設為停用
     *
     * @param $wallets
     * @return Collection
     */
    public function freezing($wallets)
    {
        $wallets = collect($wallets);
        $wallets->each(function ($wallet) {
            $wallet->status = 'freezing';
            $wallet->save();
        });
        return $wallets;
    }

    /**
     * 透過本機錢包資料取得遊玩連結資料包，寫入 StationLoginRecord 記錄，返回夾心連結記錄實體
     *
     * @param Wallet $wallet
     * @param array $params
     * @return LoginRecord
     */
    public function generatePlayUrl(Wallet $wallet, array $params = [])
    {
        // 取得 passport 通行證
        $passport = $this->connectors[$wallet->station]->passport($wallet, $params);

        // 寫入 passport 資料，產生對應的遊戲連結記錄 StationLoginRecord (Model)，返回夾心連結實體
        return (new StationLoginRecord())->create($wallet, $passport);
    }

    /**
     * 包網連結
     *
     * @param array $params
     * @return array $result
     */
    public function singleStationPlayUrl( array $params = [])
    {
        $station = array_get($params, 'station', '');
        // 取得 passport 通行證
        $result = $this->connectors[$station]->singleStationPassport($params);

        return $result;
    }

    /**
     * 輔助方法：產生一組 10 位數假手機號碼
     *
     * 備註：這邊不另外建立 helper 只是想相對單純一點
     *
     * 第一碼絕對不是 0 作為假手機號碼使用 + 100~999 隨機碼 + 後 6 碼則是微秒數字
     */
    public static function generateFakeMobile()
    {
        list($usec, $sec) = explode(" ", microtime());
        $result = ((float)$usec + (float)$sec);
        return random_int(1, 9) .
            random_int(100, 999) .
            substr(str_replace('.', '', $result), -6);
    }
}