<?php

namespace SuperPlatform\StationWallet\Traits;

use Illuminate\Support\Collection;
use SebastianBergmann\CodeCoverage\Report\PHP;
use SuperPlatform\StationWallet\Exceptions\StationWalletExistsException;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;

/**
 * Class HasStationWalletTraitsTest
 *
 * 提供給 ORM 使用 Trait 使其具有 Wallet 特性
 */
trait HasStationWalletTrait
{
    /**
     * Boot HasStationWalletTrait trait.
     *
     * @return void
     */
    protected static function bootHasStationWalletTrait()
    {
    }

    /**
     * 統一定義遊戲站錢包帳號的產生規則
     *
     * @return string
     * @throws \Exception
     */
    public function getStationWalletAccount()
    {
        return strtoupper(
            config('station_wallet.wallet_account_prefix') .
            hash(
                "crc32",
                $this->getKey(),
                false
            )
        );
    }

    /**
     * 統一定義遊戲站錢包密碼的產生規則: 8 碼
     *
     * @return string
     */
    public function getStationWalletPassword()
    {
        return config('station_wallet.wallet_account_prefix') . hash(
                "crc32",
                $this->getKey() . array_get($this->attributes, 'password'),
                false
            ) . substr(array_get($this->attributes, 'mobile', '0'), -1, 1);
    }

    /*
    |--------------------------------------------------------------------------
    | 擴充：取得錢包資料
    |--------------------------------------------------------------------------
    */
    /**
     * 取得對應 ORM 指定遊戲館錢包
     *
     * @param string $station 遊戲站識別碼
     * @return Wallet
     * @throws \Exception
     */
    public function wallet(string $station)
    {
        return Wallet::lockForUpdate()
            ->where('account', '=', $this->getStationWalletAccount())
            ->where('station', '=', $station)
            ->first();
    }

    /**
     * 取得對應 ORM (指定) 遊戲館錢包 (一到多個)
     *
     * @param null $stations 遊戲站識別碼群
     * @param null $filterStations 不要的遊戲站識別碼群
     * @return mixed
     * @throws \Exception
     */
    public function wallets($stations = null, $filterStations = null)
    {
        $stations = collect($stations);
        $filterStations = collect($filterStations);

        $wallets = Wallet::where('account', '=', $this->getStationWalletAccount());

        // 若有指定遊戲站識別碼，過濾遊戲站
        if ($stations->isNotEmpty()) {
            $wallets = $wallets->whereIn('station', $stations);
        }
        // 如果有不要的遊戲站識別碼，過濾遊戲站
        if ($filterStations->isNotEmpty()) {
            $wallets = $wallets->whereNotIn('station', $filterStations);
        }

        return $wallets->get();
    }

    /**
     * 過濾出對應 ORM 可用錢包
     *
     * @param null $stations
     * @param null $filterStations
     * @return mixed
     * @throws \Exception
     */
    public function activatedWallets($stations = null, $filterStations = null)
    {
        $stations = collect($stations);
        $filterStations = collect($filterStations);

        $wallets = Wallet::lockForUpdate()
            ->where('account', '=', $this->getStationWalletAccount())
            ->where('activated_status', 'yes');

        // 若有指定遊戲站識別碼，過濾遊戲站
        if ($stations->isNotEmpty()) {
            $wallets = $wallets->whereIn('station', $stations);
        }
        // 如果有不要的遊戲站識別碼，過濾遊戲站
        if ($filterStations->isNotEmpty()) {
            $wallets = $wallets->whereNotIn('station', $filterStations);
        }

        return $wallets->get();
    }

    /**
     * 取得對應 ORM 主錢包
     *
     * @param bool $lockForUpdate
     * @return Wallet
     * @throws \Exception
     */
    public function masterWallet($lockForUpdate = false)
    {
        $wallet = Wallet::where('account', '=', $this->getStationWalletAccount())
            ->where('station', '=', config('station_wallet.master_wallet_name'));

        //
        if ($lockForUpdate) {
            $wallet->lockForUpdate();
        }

        // todo 如有需要另外實作拋出錢包不存在的例外，可在此擴充 StationWalletNotExistsException

        return $wallet->first();
    }

    /*
    |--------------------------------------------------------------------------
    | 擴充：建立錢包資料
    |--------------------------------------------------------------------------
    */
    /**
     * 建立對應 ORM 主錢包
     *
     * @param string $status
     * @param float $balance
     * @return Wallet
     * @throws \Exception
     */
    public function buildMasterWallet($status = 'active', float $balance = 0)
    {
        $masterWallet = $this->masterWallet();
        if (!empty($masterWallet)) {
            throw new StationWalletExistsException($masterWallet->toArray());
        }
        $wallet = new Wallet();
        $wallet->account = $this->getStationWalletAccount();
        $wallet->password = $this->getStationWalletPassword();
        $wallet->user_id = $this->id;
        $wallet->station = config('station_wallet.master_wallet_name');
        $wallet->status = $status;
        $wallet->balance = $balance;
        $wallet->activated_status = 'yes';
        $wallet->save();
        return $wallet;
    }

    /**
     * 建立對應 ORM 遊戲站錢包
     *
     * @param null  $stations
     * @param array $status 錢包啟用狀態
     *
     * @return collection
     * @throws \Exception
     */
    public function buildStationWallets($stations = null, array $status = [])
    {
        $stations = collect($stations);

        if (app()->environment() === 'testing') {
            $stations = ($stations->isNotEmpty()) ? $stations : collect(array_keys(config('api_caller')));
        }

        // 先試著檢查此會員是否已經有此「遊戲站」的錢包
        // 如果已存在，就排除已存在錢包
        $wallets = $this->wallets($stations);
        if ($wallets->isNotEmpty()) {
            $existStations = array_keys($wallets->keyBy('station')->toArray());
            $stations = $stations->reject(function ($station) use ($existStations) {
                return in_array($station, $existStations);
            });
        }

        if ($stations->isEmpty()) {
            return $wallets;
        }

        // 為此會員建立本機的遊戲站錢包
        $wallets = $stations->mapWithKeys(function ($station) use ($status) {

            $wallet = new Wallet();
            $wallet->account = $this->getStationWalletAccount();
            $wallet->password = $this->getStationWalletPassword();
            $wallet->user_id = $this->id;
            $wallet->station = $station;
            $wallet->status = (array_has($status, $station)) ? array_get($status, $station) : 'active';
            $wallet->balance = 0.00;
            $wallet->save();
            return [$station => $wallet];
        });

        return collect($wallets);
    }

    /**
     * 建立對應 ORM 主錢包，與遊戲站錢包
     *
     * @param string $masterWalletStatus
     * @param array|string $station 遊戲站識別碼群
     * @param array $status 錢包啟用狀態
     * @return Collection
     * @throws \Exception
     */
    public function buildWallets($masterWalletStatus = 'active', $stations = null, array $status = [])
    {
        $masterWallet = $this->buildMasterWallet($masterWalletStatus);
        $stationWallets = $this->buildStationWallets($stations, $status);

        return $stationWallets->merge(collect($masterWallet));
    }

    /*
    |--------------------------------------------------------------------------
    | 擴充：修改錢包資料
    |--------------------------------------------------------------------------
    */
    /**
     * 啟用(對應傳入/所有)錢包
     *
     * @param array|string $station 遊戲站識別碼群
     * @return Collection
     * @throws \Exception
     */
    public function activeWallets($stations = null)
    {
        $wallets = $this->wallets($stations);

        $updateWallets = collect();
        if ($wallets->isNotEmpty()) {
            $updateWallets = $wallets->mapWithKeys(function ($wallet) {
                $wallet->status = 'active';
                $wallet->save();
                return [$wallet->station => $wallet];
            });
        }

        return $updateWallets;
    }

    /**
     * 停用(對應傳入/所有)錢包
     *
     * @param array|string $station 遊戲站識別碼群
     * @return Collection
     * @throws \Exception
     */
    public function freezeWallets($stations = null)
    {
        $wallets = $this->wallets($stations);

        $updateWallets = collect();
        if ($wallets->isNotEmpty()) {
            $updateWallets = $wallets->mapWithKeys(function ($wallet) {
                $wallet->status = 'freezing';
                $wallet->save();
                return [$wallet->station => $wallet];
            });
        }

        return $updateWallets;
    }
}