<?php

namespace SuperPlatform\StationWallet\Tests;

use Illuminate\Support\Facades\Event;
use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Connectors\AllBetConnector;
use SuperPlatform\StationWallet\Connectors\SaGamingConnector;
use SuperPlatform\StationWallet\Events\SyncDone;
use SuperPlatform\StationWallet\Exceptions\TransferFailureException;
use SuperPlatform\StationWallet\Facades\StationWallet;

class StationWalletTest extends BaseTestCase
{
    /**
     * Set-up 等同建構式
     */
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * 測試透過本機錢包資料建立遊戲站帳號
     *
     * 測試方法：build
     * @throws \Exception
     */
    public function test_測試透過本機錢包資料建立遊戲站帳號()
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
        $result = StationWallet::build($user->wallet($station));

        // Assert
        $this->assertEquals($user->getStationWalletAccount(), $result['account']);
    }

    /**
     * 測試透過本機錢包資料取得遊戲站錢包餘額
     *
     * todo 若帳號餘額與遊戲站端有同步，需測試系統端記錄的餘額是否與遊戲端相同
     *
     * 測試方法：balance
     * @throws \Exception
     */
    public function test_測試透過本機錢包資料取得遊戲站錢包餘額()
    {
        //Arrange
        $station = 'all_bet';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallets = $user->buildStationWallets($station);
        // $systemBalance = array_get($wallets->get($station)->toArray(), 'balance'); // 系統餘額
        $balance = StationWallet::balance($user->wallet($station));

        // Assert
        $this->assertNotNull($balance);
    }

    /**
     * 測試透過本機錢包資料進行點數「增加」
     *
     * 測試方法：deposit、balance
     * @throws \Exception
     */
    public function test_測試透過本機SA錢包資料進行點數_增加()
    {
        // === ARRANGE ===
        $station = 'sa_gaming';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        $saGamingWallet = $user->buildStationWallets($station);

        // 將沙龍遠端目前餘額，回寫至新建的本地沙龍錢包
        $s = StationWallet::balance($user->wallet($station));

        // === ACTION ===
        // 模擬贏得 100
        $connector = new SaGamingConnector();
        $connector->deposit($user->wallet($station), 100);
        // 進行充值 50 點
        $depositResult = StationWallet::deposit($user->wallet($station), 50);

        // === ASSERT ===
        $this->assertEquals(100, $depositResult['balance']['sync']['variation']);
        $this->assertEquals(50, $depositResult['balance']['action']['variation']);
    }

    /**
     * 測試透過本機錢包資料進行點數「增加」
     *
     * 測試方法：deposit、balance
     * @throws \Exception
     */
    public function test_測試透過本機Master錢包資料進行點數_增加()
    {
        //Arrange
        $station = config('station_wallet.master_wallet_name');
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets($station);
        $expectedBalance = StationWallet::balance($user->wallet($station)) + 1;
        $depositResult = StationWallet::deposit($user->wallet($station), 1);

        // Assert
        $this->assertEquals($expectedBalance, $depositResult['balance']['action']['after']);
        $this->assertEquals($expectedBalance, $depositResult['wallet']->balance);
    }

    /**
     * 測試透過本機錢包資料進行點數「回收」
     *
     * 測試方法：withdraw、balance
     * @throws \Exception
     */
    public function test_測試透過本機錢包資料進行點數_回收()
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
        $expectedBalance = StationWallet::balance($user->wallet($station)) - 1;
        $withdrawResult = StationWallet::withdraw($user->wallet($station), 1);

        // Assert
        $this->assertEquals($expectedBalance, $withdrawResult['balance']['action']['after']);
        $this->assertEquals($expectedBalance, $withdrawResult['wallet']->balance);
    }

    /**
     * 測試透過本機錢包資料進行點數「調整」
     *
     * 測試方法：adjust、balance
     * @throws \Exception
     */
    public function test_測試透過本機錢包資料進行點數_調整()
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
        $expectedBalance = StationWallet::balance($user->wallet($station)) + 1;
        $adjustResult = StationWallet::adjust($user->wallet($station), $expectedBalance);

        // Assert
        $this->assertEquals($expectedBalance, $adjustResult['balance']['action']['after']);
        $this->assertEquals($expectedBalance, $adjustResult['wallet']->balance);
    }

    /**
     * 測試同步遊戲站帳號餘額到本機錢包資料
     *
     * 測試方法：sync
     * @throws \Exception
     */
    public function test_測試同步遊戲站帳號餘額到本機錢包資料()
    {
        //Arrange
        $station = 'sa_gaming';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        try {
            $user->buildStationWallets($station);
            $wallet = $user->wallet($station);
            $beforeBalance = array_get($wallet, 'balance');

            Event::fake();
            $result = StationWallet::sync($wallet);
            Event::assertDispatched(SyncDone::class, function ($evt) use ($wallet) {
                echo "{$wallet->station}: {$evt->beforeBalance} -> {$evt->afterBalance}";
                return $evt->wallet === $wallet;
            });

        } catch (ApiCallerException $exc) {
            print_r($exc->response());
        } catch (\Exception $exc) {
            echo $exc->getMessage();
        }

        // Assert
        $this->assertNotEquals($beforeBalance, $result->balance);

    }

    /**
     * 測試傳入本機錢包資料設為啟用或停用
     *
     * 測試方法：active、freezing
     * @throws \Exception
     */
    public function test_測試傳入本機錢包資料設為啟用或停用()
    {
        //Arrange
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets();
        $activeResults = StationWallet::active($user->wallets())->keyBy('station')->toArray();
        $freezingResults = StationWallet::freezing($user->wallets())->keyBy('station')->toArray();

        // Assert
        $this->assertEquals('active', $activeResults['all_bet']['status']);
        $this->assertEquals('active', $activeResults['bingo']['status']);
//        $this->assertEquals('active', $activeResults['holdem']['status']);
        $this->assertEquals('active', $activeResults['sa_gaming']['status']);
        $this->assertEquals('freezing', $freezingResults['all_bet']['status']);
        $this->assertEquals('freezing', $freezingResults['bingo']['status']);
//        $this->assertEquals('freezing', $freezingResults['holdem']['status']);
        $this->assertEquals('freezing', $freezingResults['sa_gaming']['status']);
        $this->assertEquals('freezing', $freezingResults['maya']['status']);
//        $this->assertEquals('freezing', $freezingResults['nihtan']['status']);
    }

    /**
     * 測試「遊戲錢包」轉點「主錢包」但主錢包噴屎了
     *
     * 測試方法：transfer
     * @throws \Exception
     */
    public function test_測試「遊戲錢包」轉點「主錢包」但主錢包噴屎了()
    {
        // ===
        //   ARRANGE
        // ===
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // A: 建立會員主錢包
        $user->buildMasterWallet();
        $masterWallet = $user->masterWallet();
        $masterWallet->balance = rand(100, 500);
        $masterWallet->save();

        // B: 開通「沙龍錢包」
        $user->buildStationWallets('sa_gaming');
        $saGamingWallet = $user->wallet('sa_gaming');
        $saGamingConnector = new SaGamingConnector();
        $saGamingConnector->build($saGamingWallet);
        $saGamingWallet->balance = $saGamingConnector->deposit($saGamingWallet, 50)['balance'];
        $saGamingWallet->save();

        // ===
        //   ACTION
        // ===
        $beforeMasterBalance = $masterWallet->balance;
        $beforeSaGamingBalance = $saGamingConnector->balance($user->wallet('sa_gaming'))['balance'];

        // --- MOCK ---
        $this->initMock(\SuperPlatform\StationWallet\Models\StationWallet::class)
            ->makePartial()
            ->shouldReceive('save')
            ->andThrowExceptions([new \Exception('噴屎')]);

        try {
            // 隨機決定轉點數量
            $amount = rand(3, 9);
            // 從「沙龍」轉至「主錢包」
            StationWallet::transfer($saGamingWallet, $masterWallet, $amount);
        } catch (\Exception $exc) {
            // ===
            //   ASSERT
            // ===
            $afterMasterBalance = $masterWallet->balance;
            $afterSaGamingBalance = $saGamingConnector->balance($user->wallet('sa_gaming'))['balance'];

            $this->assertEquals($beforeMasterBalance, $afterMasterBalance);
            $this->assertEquals($beforeSaGamingBalance, $afterSaGamingBalance);
            throw $exc;
        }
    }

    /**
     * 測試 將主錢包點數 轉到 B 站 但目標錢包噴屎了
     *
     * 測試方法：transfer
     * @throws \Exception
     */
    public function test_測試「主錢包」轉點「遊戲錢包」但目標錢包噴屎了()
    {
        // ===
        //   ARRANGE
        // ===
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // A: 建立會員主錢包
        $user->buildMasterWallet();
        $masterWallet = $user->masterWallet();
        $masterWallet->balance = rand(100, 500);
        $masterWallet->save();

        // B: 開通「沙龍錢包」
        $user->buildStationWallets('sa_gaming');
        $saGamingWallet = $user->wallet('sa_gaming');
        $saGamingConnector = new SaGamingConnector();
        $saGamingConnector->build($saGamingWallet);
        $saGamingWallet->balance = $saGamingConnector->deposit($saGamingWallet, 50)['balance'];
        $saGamingWallet->save();

        // ===
        //   ACTION
        // ===
        $beforeMasterBalance = $masterWallet->balance;
        $beforeSaGamingBalance = $saGamingConnector->balance($user->wallet('sa_gaming'))['balance'];

        // --- MOCK ---
        $this->initMock(SaGamingConnector::class)
            ->makePartial()
            ->shouldReceive('deposit')
            ->andThrowExceptions([new \Exception('噴屎')]);

        $this->expectException(TransferFailureException::class);

        try {
            // 隨機決定轉點數量
            $amount = rand(3, 9);

            // 從「主錢包」轉至「沙龍」
            StationWallet::transfer($masterWallet, $saGamingWallet, $amount);
        } catch (TransferFailureException $exc) {
            // ===
            //   ASSERT
            // ===
            $afterMasterBalance = $masterWallet->balance;
            $afterSaGamingBalance = $saGamingConnector->balance($user->wallet('sa_gaming'))['balance'];

            $this->assertEquals($beforeMasterBalance, $afterMasterBalance);
            $this->assertEquals($beforeSaGamingBalance, $afterSaGamingBalance);
            throw $exc;
        }
    }

    /**
     * 測試 將錢包的 A 站點數 轉到 B 站
     *
     * 測試方法：transfer
     * @throws \Exception
     */
    public function test_測試「遊戲錢包」轉點「遊戲錢包」()
    {
        // ===
        //   ARRANGE
        // ===
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // A: 建立會員主錢包
        $user->buildMasterWallet();
        $masterWallet = $user->masterWallet();
        $masterWallet->balance = rand(100, 500);
        $masterWallet->save();

        // B: 開通「歐博錢包」並充值
        $user->buildStationWallets('all_bet');
        $allBetWallet = $user->wallet('all_bet');
        $allBetConnector = new allBetConnector();
        $allBetWallet->balance = $allBetConnector->deposit($allBetWallet, 50)['balance'];
        $allBetWallet->save();

        // C: 開通「沙龍錢包」
        $user->buildStationWallets('sa_gaming');
        $saGamingWallet = $user->wallet('sa_gaming');
        $saGamingConnector = new SaGamingConnector();
        $saGamingConnector->build($saGamingWallet);
        $saGamingWallet->balance = $saGamingConnector->deposit($saGamingWallet, 50)['balance'];
        $saGamingWallet->save();

        // ===
        //   ACTION
        // ===
        $beforeAllBetBalance = $allBetConnector->balance($allBetWallet)['balance'];
        $beforeSaGamingBalance = $saGamingConnector->balance($saGamingWallet)['balance'];

        // 從「歐博」轉至「沙龍」
        $amount = 10;
        $transferResult = StationWallet::transfer($allBetWallet, $saGamingWallet, $amount);

        // ===
        //   ASSERT
        // ===
        $this->assertEquals(
            $beforeAllBetBalance - $amount,
            $transferResult['from']['balance']['action']['after']
        );
        $this->assertEquals(
            -$amount,
            $transferResult['from']['balance']['action']['variation']
        );
        $this->assertEquals(
            $beforeSaGamingBalance + $amount,
            $transferResult['to']['balance']['action']['after']
        );
        $this->assertEquals(
            $amount,
            $transferResult['to']['balance']['action']['variation']
        );
    }

    /**
     * 測試 將錢包的 A 站點數 轉到 B 站 但目標錢包噴屎了
     *
     * 測試方法：transfer
     * @throws \Exception
     */
    public function test_測試「遊戲錢包」轉點「遊戲錢包」但目標錢包噴屎了()
    {
        // ===
        //   ARRANGE
        // ===
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // A: 建立會員主錢包
        $user->buildMasterWallet();
        $masterWallet = $user->masterWallet();
        $masterWallet->balance = rand(100, 500);
        $masterWallet->save();

        // B: 開通「歐博錢包」並充值
        $user->buildStationWallets('all_bet');
        $allBetWallet = $user->wallet('all_bet');
        $allBetConnector = new allBetConnector();
        $allBetWallet->balance = $allBetConnector->deposit($allBetWallet, 50)['balance'];
        $allBetWallet->save();

        // C: 開通「沙龍錢包」
        $user->buildStationWallets('sa_gaming');
        $saGamingWallet = $user->wallet('sa_gaming');
        $saGamingConnector = new SaGamingConnector();
        $saGamingConnector->build($saGamingWallet);
        $saGamingWallet->balance = $saGamingConnector->deposit($saGamingWallet, 50)['balance'];
        $saGamingWallet->save();

        // ===
        //   ACTION
        // ===
        $beforeAllBetBalance = $allBetConnector->balance($user->wallet('all_bet'))['balance'];
        $beforeSaGamingBalance = $saGamingConnector->balance($user->wallet('sa_gaming'))['balance'];

        // --- MOCK ---
        $this->initMock(SaGamingConnector::class)
            ->makePartial()
            ->shouldReceive('deposit')
            ->andThrowExceptions([new \Exception('噴屎')]);

        // 從「歐博」轉至「沙龍」
        $amount = rand(3, 9);

        $this->expectException(TransferFailureException::class);

        try {
            StationWallet::transfer($allBetWallet, $saGamingWallet, $amount);
        } catch (TransferFailureException $exc) {
            // ===
            //   ASSERT
            // ===
            $afterAllBetBalance = $allBetConnector->balance($user->wallet('all_bet'))['balance'];
            $afterSaGamingBalance = $saGamingConnector->balance($user->wallet('sa_gaming'))['balance'];

            $this->assertEquals($beforeAllBetBalance, $afterAllBetBalance);
            $this->assertEquals($beforeSaGamingBalance, $afterSaGamingBalance);
            throw $exc;
        }
    }

    /**
     * 測試 將錢包的 A 站點數 轉到 B 站 但來源錢包挫賽了
     *
     * 測試方法：transfer
     * @throws \Exception
     */
    public function test_測試「遊戲錢包」轉點「遊戲錢包」但來源錢包挫賽了()
    {
        // ===
        //   ARRANGE
        // ===
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // A: 建立會員主錢包
        $user->buildMasterWallet();
        $masterWallet = $user->masterWallet();
        $masterWallet->balance = rand(100, 500);
        $masterWallet->save();

        // B: 開通「歐博錢包」並充值
        $user->buildStationWallets('all_bet');
        $allBetWallet = $user->wallet('all_bet');
        $allBetConnector = new allBetConnector();
        $allBetWallet->balance = $allBetConnector->deposit($allBetWallet, 50)['balance'];
        $allBetWallet->save();

        // C: 開通「沙龍錢包」
        $user->buildStationWallets('sa_gaming');
        $saGamingWallet = $user->wallet('sa_gaming');
        $saGamingConnector = new SaGamingConnector();
        $saGamingConnector->build($saGamingWallet);
        $saGamingWallet->balance = $saGamingConnector->deposit($saGamingWallet, 50)['balance'];
        $saGamingWallet->save();

        // ===
        //   ACTION
        // ===
        $beforeAllBetBalance = $allBetConnector->balance($user->wallet('all_bet'))['balance'];
        $beforeSaGamingBalance = $saGamingConnector->balance($user->wallet('sa_gaming'))['balance'];

        // --- MOCK ---
        $this->initMock(AllBetConnector::class)
            ->makePartial()
            ->shouldReceive('withdraw')
            ->andThrowExceptions([new \Exception('挫賽')]);

        // 從「歐博」轉至「沙龍」
        $amount = rand(3, 9);

        $this->expectException(TransferFailureException::class);

        try {
            StationWallet::transfer($allBetWallet, $saGamingWallet, $amount);
        } catch (TransferFailureException $exc) {
            // ===
            //   ASSERT
            // ===
            $afterAllBetBalance = $allBetConnector->balance($user->wallet('all_bet'))['balance'];
            $afterSaGamingBalance = $saGamingConnector->balance($user->wallet('sa_gaming'))['balance'];

            $this->assertEquals($beforeAllBetBalance, $afterAllBetBalance);
            $this->assertEquals($beforeSaGamingBalance, $afterSaGamingBalance);

            throw $exc;
        }

    }

    /**
     * 測試 getWallet
     * @throws \Exception
     */
    public function test_get_wallet()
    {
        //Arrange
        $station = 'sa_gaming';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets();
        $saGamingWallet = $user->wallet($station);
        $getSaGamingWallet = StationWallet::getWallet($saGamingWallet->id);

        // Assert
        $this->assertEquals($saGamingWallet, $getSaGamingWallet);
    }

    /**
     * 測試 getWalletsByStation
     * @throws \Exception
     */
    public function test_get_wallets_by_station()
    {
        //Arrange
        $station = 'sa_gaming';
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $user->buildStationWallets();
        $saGamingWallet = $user->wallet($station);
        $getSaGamingWallet = StationWallet::getWalletsByStation($station);
        $expectedGetSaGamingWallet = $getSaGamingWallet->shift();

        // Assert
        $this->assertEquals($expectedGetSaGamingWallet, $saGamingWallet);
    }

    /**
     * 測試取得歐博限紅
     * @throws \Exception
     */
    public function test_get_all_bet_query_handicap()
    {
        $allBetConnector = new AllBetConnector;
        $response = $allBetConnector->getQueryHandicap();

        // Assert
        // $this->console->writeln(json_encode($response, 64 | 128 | 256));
        $this->assertTrue(!empty($response));
    }
}