<?php

namespace SuperPlatform\StationWallet\Tests;

use Illuminate\Http\Response;
use SuperPlatform\StationWallet\Connectors\SaGamingConnector;
use SuperPlatform\StationWallet\Facades\StationLoginRecord;
use SuperPlatform\StationWallet\Facades\StationWallet;
use SuperPlatform\StationWallet\StationLoginRecord as ClassStationLoginRecord;

/**
 * Class StationLoginTest
 *
 * @package SuperPlatform\StationWallet\Tests
 */
class StationLoginTest extends BaseTestCase
{
    /**
     * Set-up 等同建構式
     */
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * 測試透過本機錢包請求取得登入遊戲站前的夾心連結
     *
     * 測試方法：
     *   StationWallet::generatePlayUrl(Wallet)->
     *   [Station]Connector::passport(Wallet ID)->
     *   StationLoginRecord::create(Wallet, Passport)
     *
     * @throws \Exception
     */
    public function test_測試透過本機錢包請求取得登入遊戲站前的夾心連結()
    {
        //Arrange
        StationWallet::routes();

        $station = 'sa_gaming';

        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets($station);

        /**
         * 訪問 passport 後，返回產生的資料包，將這些資料填入對應 StationLoginRecord 中欄位資料，
         * 其資料識別碼 StationLoginRecord id 作為進入遊戲前的跳轉連結（夾心連結）的唯一識別碼
         *
         * 夾心連結範例：
         *      http://localhot.test/play/{login_record_id} -> 點了會進入遊戲
         *      login_record_id: ulid 記錄識別碼
         */
        $stationLoginRecord = StationWallet::generatePlayUrl($user->wallet($station));
        // $this->console->writeln(json_encode($stationLoginRecord, 128));

        /**
         * 1. 當使用者點了夾心連結，經過 /play/{login_record_id} 路由 ->
         * 2. 修改夾心連結識別碼對應的資料庫記錄，為「夾心被點過」，StationLoginRecord status 設為 clicked ->
         * 3. 載入視圖，把相關參數填入視圖中表單，自動提交，進入遊戲
         */
        $response = $this->get("/play/{$stationLoginRecord->id}");
        // 將結果轉成好存取的資料型態：陣列
        // $response = json_decode($response->content(), true);
        // $responseParams = json_decode(array_get($response, 'params'), true);
        // $this->console->writeln($response->content());
        $this->console->writeln("/play/{$stationLoginRecord->id}");

        // Assert
        // 訪問路由結果應為 HTTP_OK
        $response->assertStatus(Response::HTTP_OK);
        // 路由名稱應為 station_wallet::redirecting
        $response->assertViewIs('station_wallet::redirecting');
        // 寫入的夾心連結對應記錄
        $this->assertEquals(
            $this->getStationWalletAccount($user->id),
            $stationLoginRecord->account
        );
        $this->assertEquals(strtolower($station), strtolower($stationLoginRecord->station));
    }

    /**
     * 測試透過本機錢包請求取得登入遊戲站前的夾心連結：取得跳轉資訊 json data
     *
     * @throws \Exception
     */
    public function test_測試透過本機錢包請求取得登入遊戲站前的夾心連結_取得跳轉資訊_json_data()
    {
        //Arrange
        StationWallet::routes();
        $station = 'sa_gaming';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets($station);

        /**
         * 訪問 passport 後，返回產生的資料包，將這些資料填入對應 StationLoginRecord 中欄位資料，
         * 其資料識別碼 StationLoginRecord id 作為進入遊戲前的跳轉連結（夾心連結）的唯一識別碼
         *
         * 夾心連結範例：
         *      http://localhot.test/play/{login_record_id} -> 點了會進入遊戲
         *      login_record_id: ulid 記錄識別碼
         */
        $stationLoginRecord = StationWallet::generatePlayUrl($user->wallet($station));
        // $this->console->writeln(json_encode($stationLoginRecord, 128));

        /**
         * 1. 當使用者點了夾心連結，經過 /play/{login_record_id} 路由 ->
         * 2. 修改夾心連結識別碼對應的資料庫記錄，為「夾心被點過」，StationLoginRecord status 設為 clicked ->
         * 3. 載入視圖，把相關參數填入視圖中表單，自動提交，進入遊戲
         */
        $response = $this->post("/play/{$stationLoginRecord->id}");
        // 將結果轉成好存取的資料型態：陣列
        $responseContent = json_decode($response->content(), true);
        // $this->console->writeln(json_encode($responseContent, 128));
        // $this->console->writeln("/play/{$stationLoginRecord->id}");

        // Assert
        // 訪問路由結果應為 HTTP_OK
        $response->assertStatus(Response::HTTP_OK);
        // 寫入的夾心連結對應記錄
        $this->assertEquals(
            $this->getStationWalletAccount($user->id),
            $stationLoginRecord->account
        );
        $this->assertEquals(strtolower($station), strtolower($stationLoginRecord->station));
    }

    /**
     * 測試 create
     *
     * @throws \Exception
     */
    public function testCreate()
    {
        //Arrange
        $station = 'sa_gaming';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets($station);
        // 沙龍
        $saGamingConnector = new SaGamingConnector();
        $saGamingWallet = $user->wallet($station);
        $loginRecord = StationLoginRecord::create($saGamingWallet, $saGamingConnector->passport($saGamingWallet));

        // Assert
        $this->assertEquals($saGamingWallet->account, $loginRecord->account);
    }

    /**
     * 測試 getRecord
     *
     * @throws \Exception
     */
    public function testGetRecord()
    {
        //Arrange
        $station = 'sa_gaming';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets($station);
        // 沙龍
        $saGamingConnector = new SaGamingConnector();
        $saGamingWallet = $user->wallet($station);
        $loginRecord = StationLoginRecord::create($saGamingWallet,
            $saGamingConnector->passport($saGamingWallet))->toArray();
        $getLoginRecordResult = StationLoginRecord::getRecord([
            'wallet' => $saGamingWallet,
            'status' => ClassStationLoginRecord::STATUS_UN_CLICK
        ])->toArray();
        $getLoginRecordResult = $getLoginRecordResult[0];

        // Assert
        $this->assertEquals($getLoginRecordResult['id'], $loginRecord['id']);
        $this->assertEquals($getLoginRecordResult['account'], $loginRecord['account']);
        $this->assertEquals($getLoginRecordResult['station'], $loginRecord['station']);
        $this->assertEquals($getLoginRecordResult['status'], $loginRecord['status']);
        $this->assertEquals($getLoginRecordResult['method'], $loginRecord['method']);
        $this->assertEquals($getLoginRecordResult['web_url'], $loginRecord['web_url']);
        $this->assertEquals($getLoginRecordResult['mobile_url'], $loginRecord['mobile_url']);
        $this->assertEquals($getLoginRecordResult['params'], $loginRecord['params']);
    }

    /**
     * 測試 getRecord 當 status is clicked
     *
     * @throws \Exception
     */
    public function testGetRecordByStatus()
    {
        //Arrange
        $station = ['sa_gaming'];
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets($station);
        $wallets = $user->wallets($station)->keyBy('station');
        $loginRecord = [];
        foreach ($station as $gameStation) {
            $className = '\SuperPlatform\StationWallet\Connectors\\' . camel_case($gameStation) . 'Connector';
            $connector = new $className;
            $stationWallet = data_get($wallets, $gameStation);
            array_push($loginRecord, StationLoginRecord::create(
                $stationWallet,
                $connector->passport($stationWallet))->toArray());
        }
        $loginRecord = $loginRecord[0];
        $getLoginRecordResult = StationLoginRecord::getRecord([
            'wallet' => data_get($wallets, 'sa_gaming'),
            'status' => ClassStationLoginRecord::STATUS_UN_CLICK])
            ->toArray();
        $getLoginRecordResult = $getLoginRecordResult[0];

        // Assert
        $this->assertEquals($getLoginRecordResult['id'], $loginRecord['id']);
        $this->assertEquals($getLoginRecordResult['account'], $loginRecord['account']);
        $this->assertEquals($getLoginRecordResult['station'], $loginRecord['station']);
        $this->assertEquals($getLoginRecordResult['status'], $loginRecord['status']);
        $this->assertEquals($getLoginRecordResult['method'], $loginRecord['method']);
        $this->assertEquals($getLoginRecordResult['web_url'], $loginRecord['web_url']);
        $this->assertEquals($getLoginRecordResult['mobile_url'], $loginRecord['mobile_url']);
        $this->assertEquals($getLoginRecordResult['params'], $loginRecord['params']);
    }

    /**
     * 測試 getRecordById
     *
     * @throws \Exception
     */
    public function testGetRecordById()
    {
        //Arrange
        $station = 'sa_gaming';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets($station);
        // 沙龍
        $saGamingConnector = new SaGamingConnector();
        $saGamingWallet = $user->wallet($station);
        $loginRecord = StationLoginRecord::create($saGamingWallet, $saGamingConnector->passport($saGamingWallet))->toArray();
        $getLoginRecordResult = StationLoginRecord::getRecordById($loginRecord['id'])->toArray();

        // Assert
        $this->assertEquals($getLoginRecordResult['id'], $loginRecord['id']);
        $this->assertEquals($getLoginRecordResult['account'], $loginRecord['account']);
        $this->assertEquals($getLoginRecordResult['station'], $loginRecord['station']);
        $this->assertEquals($getLoginRecordResult['status'], $loginRecord['status']);
        $this->assertEquals($getLoginRecordResult['method'], $loginRecord['method']);
        $this->assertEquals($getLoginRecordResult['web_url'], $loginRecord['web_url']);
        $this->assertEquals($getLoginRecordResult['mobile_url'], $loginRecord['mobile_url']);
        $this->assertEquals($getLoginRecordResult['params'], $loginRecord['params']);
    }

    /**
     * 測試 setClicked
     *
     * @throws \Exception
     */
    public function testSetClicked()
    {
        //Arrange
        $station = 'sa_gaming';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets($station);
        // 沙龍
        $saGamingConnector = new SaGamingConnector();
        $saGamingWallet = $user->wallet($station);
        $loginRecord = StationLoginRecord::create($saGamingWallet, $saGamingConnector->passport($saGamingWallet));
        $getLoginRecordResult = StationLoginRecord::setClicked($loginRecord)->toArray();

        // Assert
        $this->assertEquals(ClassStationLoginRecord::STATUS_CLICKED, $getLoginRecordResult['status']);
    }

    /**
     * 測試 setAbort
     *
     * @throws \Exception
     */
    public function testSetAbort()
    {
        //Arrange
        $station = 'sa_gaming';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets($station);
        // 沙龍
        $saGamingConnector = new SaGamingConnector();
        $saGamingWallet = $user->wallet($station);
        $loginRecord = StationLoginRecord::create($saGamingWallet, $saGamingConnector->passport($saGamingWallet));
        $getLoginRecordResult = StationLoginRecord::setAbort($loginRecord)->toArray();
        $getLoginRecordResult = $getLoginRecordResult[0];

        // Assert
        $this->assertEquals(ClassStationLoginRecord::STATUS_ABORT, $getLoginRecordResult['status']);
    }

    /**
     * 測試 setFail
     *
     * @throws \Exception
     */
    public function testSetFail()
    {
        //Arrange
        $station = 'sa_gaming';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets($station);
        // 沙龍
        $saGamingConnector = new SaGamingConnector();
        $saGamingWallet = $user->wallet($station);
        $loginRecord = StationLoginRecord::create($saGamingWallet, $saGamingConnector->passport($saGamingWallet));
        $getLoginRecordResult = StationLoginRecord::setFail($loginRecord)->toArray();
        $getLoginRecordResult = $getLoginRecordResult[0];

        // Assert
        $this->assertEquals(ClassStationLoginRecord::STATUS_FAIL, $getLoginRecordResult['status']);
    }
}