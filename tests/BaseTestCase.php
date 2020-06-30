<?php

namespace SuperPlatform\StationWallet\Tests;

use Ariby\Ulid\HasUlid;
use Dotenv\Dotenv;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\StationWallet\Traits\HasStationWalletTrait;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class BaseTestCase
 *
 * @package SuperPlatform\StationWallet\Tests
 */
class BaseTestCase extends TestCase
{
    // 開啟交易模式
    use DatabaseTransactions;

    /**
     * @var ConsoleOutput 終端器輸出器
     */
    protected $console;

    /**
     * @var \Faker\Factory 假資料產生器
     */
    protected $faker;

    /**
     * @var string 固定玩家創建時的 ULID
     */
    protected $userId;

    /**
     * @var string 固定玩家創建時的密碼
     */
    protected $userPassword;

    /**
     * @var string 固定玩家使用的手機號碼
     */
    protected $gamePlayerMobile;

    /**
     * @var string 歐博測試用的一般限紅
     */
    protected $allBetNormalHandicaps;

    /**
     * @var string 歐博測試用的 vip 限紅
     */
    protected $allBetVIPHandicaps;

    /**
     * Set-up 等同建構式
     */
    protected function setUp()
    {
        parent::setUp();

        $this->console = new ConsoleOutput();
        $this->faker = \Faker\Factory::create();

        // 新增 users table，注入此套件所必要資料庫 migrations, seeds, factories
        $this->initUserTable();
        $this->injectDatabase();

        // env 定義測試用玩家創建時的 ULID 與密碼
        $this->userId = env('TEST_USER_ID');
        $this->userPassword = env('TEST_USER_PASSWORD');

        // env 定義測試用玩家使用的手機號碼
        $this->gamePlayerMobile = env('TEST_GAME_PLAYER_MOBILE');

        // 歐博測試用的一般限紅與 vip 限紅
        $this->allBetNormalHandicaps = env('ALL_BET_TEST_NORMAL_HANDICAPS');
        $this->allBetVIPHandicaps = env('ALL_BET_TEST_VIP_HANDICAPS');
    }

    /**
     * 注入此套件所必要資料庫 migrations, seeds, factories
     */
    protected function injectDatabase()
    {
        // 避免 MySQL 版本過舊產生的問題
        Schema::defaultStringLength(191);

        // 載入測試用的 migrations 檔案
        $this->loadMigrationsFrom([
            '--database' => 'testing',
            '--realpath' => realpath(__DIR__ . '/../database/migrations'),
        ]);

        // 載入輔助資料產生的工廠類別
        $this->withFactories(__DIR__ . '/../database/factories');
    }

    /**
     * 初始化 User Table
     */
    protected function initUserTable()
    {
        Schema::dropIfExists('users');

        // 測試時建立臨時的使用者 table
        Schema::create('users', function ($table) {
            // 唯一識別碼 ULID
            $table->char('id', 26)->primary();
            // 密碼：hash crc32 作為遊戲端密碼
            $table->string('password');
            // 使用者手機：前綴 APP_ID + 手機號碼 mobile 作為遊戲端帳號
            $table->string('mobile');
            $table->datetime('created_at');
            $table->datetime('updated_at');
        });

        // 測試完畢後再刪掉臨時建立的使用者 table
        $this->beforeApplicationDestroyed(function () {
            Schema::dropIfExists('users');
        });
    }

    /**
     * 測試時的 Package Providers 設定
     *
     *  ( 等同於原 laravel 設定 config/app.php 的 Autoloaded Service Providers )
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \Orchestra\Database\ConsoleServiceProvider::class,
            \SuperPlatform\StationWallet\StationWalletServiceProvider::class,
        ];
    }

    /**
     * 測試時的 Class Aliases 設定
     *
     * ( 等同於原 laravel 中設定 config/app.php 的 Class Aliases )
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [

        ];
    }

    /**
     * 測試時的時區設定
     *
     * ( 等同於原 laravel 中設定 config/app.php 的 Application Timezone )
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return string|null
     */
    protected function getApplicationTimezone($app)
    {
        return 'Asia/Taipei';
    }

    /**
     * 測試時使用的 HTTP Kernel
     *
     * ( 等同於原 laravel 中 app/HTTP/kernel.php )
     * ( 若需要用自訂時，把 Orchestra\Testbench\Http\Kernel 改成自己的 )
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function resolveApplicationHttpKernel($app)
    {
        $app->singleton(
            'Illuminate\Contracts\Http\Kernel',
            'Orchestra\Testbench\Http\Kernel'
        );
    }

    /**
     * 測試時使用的 Console Kernel
     *
     * ( 等同於原 laravel 中 app/Console/kernel.php )
     * ( 若需要用自訂時，把 Orchestra\Testbench\Console\Kernel 改成自己的 )
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function resolveApplicationConsoleKernel($app)
    {
        $app->singleton(
            'Illuminate\Contracts\Console\Kernel',
            'Orchestra\Testbench\Console\Kernel'
        );
    }


    /**
     * 測試時的環境設定
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        // 若有環境變數檔案，嘗試著讀取使用
        if (file_exists(dirname(__DIR__) . '/.env')) {
            $dotenv = new Dotenv(dirname(__DIR__));
            $dotenv->load();
        }

        // 定義測試時使用的資料庫
        // php.ini 要打開 pdo_extension 為 enabled
        $app['config']->set('database.connections.testing', [
            'driver' => env('TEST_DB_CONNECTION', 'sqlite'),
            'host' => env('TEST_DB_HOST', 'localhost'),
            'database' => env('TEST_DB_DATABASE', ':memory:'),
            'port' => env('TEST_DB_PORT'),
            'username' => env('TEST_DB_USERNAME'),
            'password' => env('TEST_DB_PASSWORD'),
            'prefix' => env('TEST_DB_PREFIX',''),
            'unix_socket' => env('TEST_DB_SOCKET', ''),
        ]);
        $app['config']->set('database.default', 'testing');
    }

    /**
     * 初始化Mock物件
     *
     * @param string $className
     * @return MockInterface
     */
    public function initMock(string $className): MockInterface
    {
        $mock = \Mockery::mock($className);
        App::instance($className, $mock);

        return $mock;
    }

    /**
     * 根據遊戲館代碼（all_bet, super_sport, sa_gaming,...）實例化 connector
     *
     * @param $stationName
     * @return mixed
     */
    public function makeConnector($stationName)
    {
        $connectorName = ucfirst(camel_case($stationName)) . 'Connector';
        $connectorClassName = 'SuperPlatform\\StationWallet\\Connectors\\' . $connectorName;

        return app()->make($connectorClassName);
    }

    /**
     * 統一定義遊戲站錢包帳號的產生規則
     *
     * @return string
     * @throws \Exception
     */
    public function getStationWalletAccount($id)
    {
        return strtoupper(config('station_wallet.wallet_account_prefix') .
            hash(
                "crc32",
                $id,
                false
            )
        );
    }

    /**
     * @return mixed
     */
    protected function getGameMemberID($vendorNo, $playerAccount)
    {
        return ApiCaller::make('maya')->methodAction('get', 'GetGameMemberID', [
            // 路由參數這邊設定
        ])->params([
            // 一般參數這邊設定
            'VenderNo' => $vendorNo,
            'VenderMemberID' => $playerAccount,
        ])->submit()['response']['GameMemberID'];
    }
}

/**
 * 測試用使用者 Model
 *
 * @property String $id
 * @property String $password
 * @property String $mobile
 */
class User extends Model
{
    use HasStationWalletTrait;
    use HasUlid;

    public $incrementing = false;

    public function getIncrementing()
    {
        return false;
    }
}