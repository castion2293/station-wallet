<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class SaGamingConnectorTest extends BaseTestCase
{
    /**
     * 測試使用 connector 增加「沙龍」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddSaGamingGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 增加「沙龍」遊戲餘額');

        // Arrange
        $target = 'sa_gaming';
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
        $before = $connector->balance($wallet)['response']['Balance'];
        $expect = $before + $accessPoint;
        $addingBalance = $connector->deposit($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response']['Balance'];

        // Assert
        $this->assertEquals(0, $addingBalance['ErrorMsgId']);
        $this->assertEquals($expect, $actual);
    }

    /**
     * 測試使用 connector 建立「沙龍」遊戲帳號
     *
     * 備註：沙龍建立重複帳號不會噴例外
     *
     * @throws \Exception
     */
    public function testBuildSaGamingGameAccount()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 建立「沙龍」遊戲帳號');

        // Arrange
        $target = 'sa_gaming';

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
        $this->assertArrayHasKey('account', $response);
    }

    /**
     * 測試使用 connector 取得「沙龍」遊戲餘額
     *
     * 備註：沙龍建立重複帳號不會噴例外
     *
     * @throws \Exception
     */
    public function testGetSaGamingGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「沙龍」遊戲餘額');

        // Arrange
        $target = 'sa_gaming';

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
        $response = $connector->balance($wallet);

        //Assert
        $this->assertEquals('Success', $response['response']['ErrorMsg']);
        $this->assertArrayHasKey('Balance', $response['response']);
    }

    /**
     * 測試使用 connector 取得「沙龍」遊戲通行證
     *
     * 備註：沙龍建立重複帳號不會噴例外
     *
     * @throws \Exception
     */
    public function testGetSaGamingGamePassport()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「沙龍」遊戲通行證');

        // Arrange
        $target = 'sa_gaming';

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

        //Assert
        $this->assertEquals('Success', $response['response']['ErrorMsg']);
        $this->assertArrayHasKey('web_url', $response);
    }

    /**
     * 測試使用 connector 回收「沙龍」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceSaGamingGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 回收「沙龍」遊戲餘額');

        // Arrange
        $target = 'sa_gaming';
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
        $before = $connector->balance($wallet)['response']['Balance'];
        $expect = $before - $accessPoint;
        $addingBalance = $connector->withdraw($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response']['Balance'];

        // Assert
        $this->assertEquals(0, $addingBalance['ErrorMsgId']);
        $this->assertEquals($expect, $actual);
    }

    /**
     * 測試使用 connector 登出「歐博」
     *
     * @throws \Exception
     */
    public function testAllBetGameLogout()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 登出「沙龍」');

        // Arrange
        $target = 'sa_gaming';

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

        // Assert
        $this->assertEquals(true, $response);
    }
}