<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class Cq9GameConnectorTest extends BaseTestCase
{
    /**
     * 測試使用 connector 增加「cq9」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddCq9GameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 增加「cq9」遊戲餘額');

        // Arrange
        $target = 'cq9_game';
        $accessPoint = 10;

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        $before = $connector->balance($wallet)['response']['data']['balance'];
        $expect = $before + $accessPoint;
        $addingBalance = $connector->deposit($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response'];

        // Assert
        $this->assertEquals("0", $addingBalance['status']['code']);
        $this->assertEquals($expect, array_get($actual, 'data.balance'));
    }

    /**
     * 測試使用 connector 建立「cq9」遊戲帳號
     * 若cq9已存在重複的會員,則會噴例外
     *
     * @throws \Exception
     */
    public function testBuildCq9GameAccount()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 建立「cq9」遊戲帳號');

        // Arrange
        $target = 'cq9_game';

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        $response = $connector->build($wallet);
        //Assert
        $this->assertEquals('Success', $response['status']['message']);
    }

    /**
     * 測試使用 connector 檢查「cq9」遊戲帳號是否存在
     *
     * @throws \Exception
     */
    public function testCheckAccountExistCq9Game()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 檢查「cq9」遊戲帳號是否已存在');

        // Arrange
        $target = 'cq9_game';

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);

        $response = $connector->checkAccountExist($wallet);

        //Assert
        $this->assertEquals(true, $response['data']);
    }




    /**
     * 測試使用 connector 取得「cq9」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetCq9GameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「cq9」遊戲餘額');

        // Arrange
        $target = 'cq9_game';

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        $response = $connector->balance($wallet)['response'];

        // Assert
        $this->assertArrayHasKey('balance', $response['data']);
    }

    /**
     * 測試使用 connector 回收「cq9」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceCq9GameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 回收「cq9」遊戲餘額');

        // Arrange
        $target = 'cq9_game';
        $accessPoint = 10;

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        $before = $connector->balance($wallet)['response']['data']['balance'];
        $expect = $before - $accessPoint;
        $reduceBalance = $connector->withdraw($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response'];

        // Assert
        $this->assertEquals("0", $reduceBalance['status']['code']);
        $this->assertEquals($expect, array_get($actual, 'data.balance'));
    }

    /**
     * 測試使用 connector 取得「cq9」遊戲通行證
     *
     *
     * @throws \Exception
     */
    public function testGetCq9GamePassport()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「cq9」遊戲通行證');

        // Arrange
        $target = 'cq9_game';

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        $response = $connector->passport($wallet);
        print_r($response);
        //Assert
        $this->assertEquals('Success', $response['status']['message']);
        $this->assertArrayHasKey('web_url', $response);
    }

    /**
     * 測試使用 connector 取得「cq9」遊戲通行證
     *
     *
     * @throws \Exception
     */
    public function testGetCq9GamePassportByGameId()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「cq9」遊戲通行證');

        // Arrange
        $target = 'cq9_game';

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        $response = $connector->passport($wallet, ['game_id' => 'AT01']);
        print_r($response);
        //Assert
        $this->assertEquals('Success', $response['status']['message']);
        $this->assertArrayHasKey('web_url', $response);
    }

    /**
     * 測試使用 connector 取得「cq9」遊戲通行證
     *
     *
     * @throws \Exception
     */

    public function testLogout()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 將「cq9」帳號登出');

        // Arrange
        $target = 'cq9_game';

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);

        $response = $connector->logout($wallet);

        //Assert
        $this->assertEquals(true, $response);
    }
}