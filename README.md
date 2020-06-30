# 遊戲站錢包模組 未壓縮

遊戲站錢包等同於（自有/第三方）遊戲站內的遊戲會員帳號，在我方應用上會搭配 User Model 一併建立所有遊戲站的錢包（遊戲會員帳號），結構如下：

    /*
    |--------------------------------------------------------------------------
    | 錢包資料結構
    |--------------------------------------------------------------------------
    |
    | 建立使用者自身主錢包，與其各遊戲站錢包，需傳入遊戲站錢包的啟用狀態預設值，否則預設為
    | 全啟用。
    | 
    | 在此使用者指的是：節點樹中的 file 類型節點
    | 
    | 自身主錢包的 station 預設為 config 中配置的 master_wallet_name
    |
    */
    |--------------------------------------------------------------------------
    |     id    |    station    |    status    |    balance    |    created_at ...
    |--------------------------------------------------------------------------
    |  ulid001  |    MASTER     |    active    |       0.00    |    Y-m-d H:i:s ...
    |--------------------------------------------------------------------------
    |  ulid001  |    all_bet    |    active    |       0.00    |    Y-m-d H:i:s ...
    |--------------------------------------------------------------------------
    |  ulid001  |    bingo      |    active    |       0.00    |    Y-m-d H:i:s ...
    |--------------------------------------------------------------------------
    |  ulid001  |    holdem     |    active    |       0.00    |    Y-m-d H:i:s ...
    |--------------------------------------------------------------------------
    |  ulid001  |    sa_gming   |    active    |       0.00    |    Y-m-d H:i:s ...
    |--------------------------------------------------------------------------
    |  ulid002  |    MASTER     |    active    |       0.00    |    Y-m-d H:i:s ...
    |--------------------------------------------------------------------------
    |  ...      |    ...        |    ...       |       ...     |    ...
    
請注意，每一個使用者，對應一個遊戲站，只會存在唯一個錢包

## 安裝
因為此套件是私有版控庫，如果要安裝此 package 的專案，必需在自己的 composer.json 先定義版控庫來源

1. 本套件私有庫來源
2. super-platform/api-caller 私有庫來源

    // composer.json
        
    ...(略)...
    "repositories": [
        {
            "type": "git",
            "url": "git@git.sp168.cc:super-platform/laravel-package/station-wallet.git"
        },
        {
            "type": "git",
            "url": "git@git.sp168.cc:super-platform/laravel-package/api-caller.git"
        }
    ],
    ...(略)...


接著就可以透過下列指令進行安裝

    composer require super-platform/station-wallet

如果 Laravel 版本在 5.4 以下，你必需手動追加 ServerProvider，手動追加是比較保險的作法

    // config/app.php
    
    ...(略)...
    'providers' => [
        ...
        SuperPlatform\StationWallet\StationWalletServiceProvider::class,
    ],
    ...(略)...

## 使用方法

### 建立錢包
追加 HasStationWallet 的 trait 到 User 的 ORM 模組

    use SuperPlatform\StationWallet\HasStationWallet;
    
    class User extends Authenticatable
    {
        use HasStationWallet;
    }

接下來就可以直接透過 User 的 ORM 模組建立錢包

    // 先找出要綁定錢包的會員
    $user = User::where('account','richman')->first();
    
    // 建立主錢包
    $user->buildMasterWallet();

    // 建立一個「所有遊戲館」錢包，所有遊戲館可參考 config 中 station_wallet.stations 的設定值 
    $user->buildStationWallets();
    
    // 建立一個「賓果」錢包
    $user->buildStationWallets('bingo');

    // 建立一個「賓果」錢包，與一個「沙龍」錢包
    $user->buildStationWallets(['bingo', 'sa_gaming']);

### 取得錢包資料
找出指定會員的相關錢包

    // 找出 $user 所有遊戲站錢包
    $wallets = $user->wallets();
    
    // 找出 $user 在「沙龍」的錢包
    $wallet = $user->wallet('sa_gaming');
    or
    $wallet = $user->wallets('sa_gaming');
    
    
不指定會員，找出特定遊戲站的多個錢包
 
    // 根據「錢包識別碼」與「遊戲站名」取得錢包實體
    StationWallet::getWallet(string $walletId, string $station)
     
    // 根據「遊戲站名」取得 collction 多個錢包實體
    StationWallet::getWalletsByStation(string $station)
    
### 操作錢包(透過錢包管理器)
載入錢包管理器 `use SuperPlatform\StationWallet\Facades\StationWallet;`

案例： 同步 $user 的「賓果」遊戲站錢包的餘額

    $wallet = $user->wallet('bingo');
    StationWallet::sync($wallet);
         
案例： 對 $user 的「賓果」錢包儲值 1000

    $wallet = $user->wallet('bingo');
    StationWallet::deposit($wallet, 1000);
    
案例： 對 $user 的「賓果」錢包回收 1000

    $wallet = $user->wallet('bingo');
    StationWallet::withdraw($wallet, 1000);
    
案例： 對 $user 的「歐博」錢包餘額調整至 1000
 
    $wallet = $user->wallet('all_bet');
    StationWallet::adjust($wallet, 1000);
    
案例： 對 $user 的「歐博」錢包凍結

    $wallet = $user->wallet('all_bet');
    StationWallet::freezing($wallet);
    
案例： 對 $user 的「歐博」錢包啟用

    $wallet = $user->wallet('all_bet');
    StationWallet::activate($wallet);
    
### Artisan 指令集

(規劃中)

### 登入管理器

載入登入管理器 `use SuperPlatform\StationWallet\Facades\StationLoginRecord;`
載入錢包管理器 `use SuperPlatform\StationWallet\Facades\StationWallet;`

    // 取得「賓果」遊戲登入連結
    $playUrl = StationWallet::generatePlayUrl($user->wallet('bingo'));
    
    // $playUrl is like /play/ulidxxxxxxxxxxx

遊戲登入連結對應路由，需要在 ```RouteServiceProvider.php```.method ```mapWebRoutes``` 中添加錢包套件路由才會生效

    StationWallet::route();
    
當使用者，或使用套件的設計者連到 ```GET /play/ulidxxxxxxxxxxx``` 會取得視圖，視圖的唯一動作就是將當前客端跳往真正的遊戲畫面

### 登入記錄

登入記錄查詢

    // 透過錢包取得曾經的登入記錄，$options 可過濾 status 錢包狀態
    StationLoginRecord::getRecord(StationWallet $wallet, array $options = [])
     
## 其他補充說明

### HasStationWalletTrait

本 Trait 會自動關閉使用他的 ORM 的 primary key auto increment，本套件預設關連的資料識別碼都是 ulid，產生 ulid 的套件是使用 [robinvdvleuten/php-ulid](https://github.com/robinvdvleuten/php-ulid)。若要啟用 auto increment 可以修改 config 的 ```trait_get_incrementing``` 為 true（不建議）。

### composer 

由於 vinkla/hashids 套件所相依的套件，需要較高版本，建議參考以下列出的版本修改 composer.json，再進行安裝本套件
```
  "require": {
    "php": "^7.1.3",
    "laravel/framework": "5.6.*",
    "illuminate/database": "~5.3",
    "illuminate/support": "~5.3",
    "vinkla/hashids": "^5.0"
  },
  "require-dev": {
    "phpunit/phpunit": "~7.0",
    "orchestra/testbench": "3.6.*",
    "orchestra/database": "3.6.*"
  },
```

### vinkla/hashids

套件網址：[https://github.com/vinkla/laravel-hashids](https://github.com/vinkla/laravel-hashids)

安裝完成後需發佈 config 來設定 KEY-LENGTH，一般使用都會套用 
```
$ php artisan vendor:publish
```
修改 config/hashids.php
```
...(略)...
'main' => [
    'salt' => config('station_wallet.hashids.salt'),
    'length' => config('station_wallet.hashids.length'),
],
...(略)...

```
若要從 .env 定義 config/station_wallet.php 設定的 salt, length，可設定
```
HASH_ID_SALT=YOUR_SALT # 不設定預設為 APP_KEY
HASH_ID_LENGTH=YOUR_LENGTH # 不設定預設為 12
```