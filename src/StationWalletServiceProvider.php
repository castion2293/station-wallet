<?php

namespace SuperPlatform\StationWallet;

use Illuminate\Support\ServiceProvider;
use SuperPlatform\StationWallet\Commands\UpdateStationBetLimit;

class StationWalletServiceProvider extends ServiceProvider
{
    protected $commands = [
        Commands\WalletSynchronizePoint::class,
    ];

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // 合併套件設定檔
        $this->mergeConfigFrom(
            __DIR__ . '/../config/station_wallet.php', 'station_wallet'
        );

        // include helpers after 合併套件設定檔
        $this->includeHelpers();

        // 載入 package views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'station_wallet');

        if ($this->app->runningInConsole()) {
            // 執行所有套件 migrations
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
            // 註冊所有 commands
            $this->commands([
                UpdateStationBetLimit::class
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register('SuperPlatform\ApiCaller\ApiCallerServiceProvider');

        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('station_wallet', 'SuperPlatform\StationWallet\StationWallet');
        $loader->alias('station_login_record', 'SuperPlatform\StationWallet\StationLoginRecord');
        $loader->alias('station_wallet_connector', 'SuperPlatform\StationWallet\StationWalletConnector');
        $loader->alias('Hashids', 'Vinkla\Hashids\Facades\Hashids');

        $this->commands($this->commands);
    }

    /**
     * include helpers
     */
    protected function includeHelpers()
    {
        if (config('station_wallet.enable_exception_helper')) {
            $file = __DIR__ . '/Helpers/ExceptionHelper.php';
            if (file_exists($file)) {
                require_once($file);
            }
        }
    }
}