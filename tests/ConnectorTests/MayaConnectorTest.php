<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class MayaConnectorTest extends BaseTestCase
{

    /**
     * 測試使用 connector 增加「瑪雅」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddMayaGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 增加「瑪雅」遊戲餘額');

        // Arrange
        $target = 'maya';
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
        $before = $connector->balance($wallet)['response']['MemberBalanceList'][0]['Balance'];
        $expect = $before + $accessPoint;
        $actual = $connector->deposit($wallet, $accessPoint)['response']['AfterBalance'];

        // Assert
        $this->assertEquals($expect, $actual);
    }

    /**
     * 測試使用 connector 建立「瑪雅」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildMayaGameAccount()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 建立「瑪雅」遊戲帳號');

        // Arrange
        $target = 'maya';

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
        try {
            $connector->build($wallet, [
                'VenderNo' => config("api_caller.maya.config.property_id"),
                'SiteNo' => config("api_caller.maya.config.test_site_no"),
                'CurrencyNo' => 'TWD',
            ]);
        } catch (ApiCallerException $exception) {
            $this->assertEquals(11028, $exception->response()['ErrorCode']);
        } catch (\Exception $exception) {
        }
    }

    /**
     * 測試使用 connector 取得「瑪雅」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetMayaGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「瑪雅」遊戲餘額');

        // Arrange
        $target = 'maya';

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
        $this->assertArrayHasKey('MemberBalanceList', $response);
        $this->assertArrayHasKey('Balance', $response['MemberBalanceList'][0]);
    }

    /**
     * 測試使用 connector 取得「瑪雅」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetMayaGamePassport()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「瑪雅」遊戲通行證');

        // Arrange
        $target = 'maya';

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
        $response = $connector->passport($wallet)['response'];

        // Assert
        $this->assertArrayHasKey('ErrorCode', $response);
        $this->assertArrayHasKey('InGameUrl', $response);
    }

    /**
     * 測試使用 connector 回收「瑪雅」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceMayaGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 回收「瑪雅」遊戲餘額');

        // Arrange
        $target = 'maya';
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
        $before = $connector->balance($wallet)['response']['MemberBalanceList'][0]['Balance'];
        $expect = $before - $accessPoint;
        $actual = $connector->withdraw($wallet, $accessPoint)['response']['AfterBalance'];

        // Assert
        $this->assertEquals($expect, $actual);
    }

    /**
     * 測試使用 connector 登出「瑪雅」帳號
     *
     * @throws \Exception
     */
    public function testLogout()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 登出「瑪雅」帳號');

        // Arrange
        $target = 'maya';

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