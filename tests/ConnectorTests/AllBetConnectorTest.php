<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class AllBetConnectorTest extends BaseTestCase
{

    /**
     * 測試使用 connector 建立「歐博」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildAllBetGameAccount()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 建立「歐博」遊戲帳號');

        // Arrange
        $target = 'all_bet';

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
                'normal_handicaps' => $this->allBetNormalHandicaps,
                'vip_handicaps' => $this->allBetVIPHandicaps,
                'normal_hall_rebate' => 0,
            ]);
        } catch (\Exception $exception) {
            print_r($exception->getMessage());
            // 為了讓每次測試不要重複一直建立帳號，這邊接到回應帳號已存在，不另外拋出例外，讓測試繼續進行
            $this->assertTrue(strpos($exception->getMessage(), 'CLIENT_EXIST') !== false);
        }
    }

    /**
     * 測試使用 connector 增加「歐博」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddAllBetGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 增加「歐博」遊戲餘額');

        // Arrange
        $target = 'all_bet';
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
        $before = $connector->balance($wallet)['response']['balance'];
        $expect = $before + $accessPoint;
        $addingBalance = $connector->deposit($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response']['balance'];

        // Assert
        $this->assertEquals('OK', $addingBalance['error_code']);
        $this->assertEquals($expect, $actual);
    }


    /**
     * 測試使用 connector 取得「歐博」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetAllBetGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「歐博」遊戲餘額');

        // Arrange
        $target = 'all_bet';

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
        $this->assertArrayHasKey('balance', $response);
    }

    /**
     * 測試使用 connector 取得「歐博」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetAllBetGamePassport()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「歐博」遊戲通行證');

        // Arrange
        $target = 'all_bet';

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
        $this->assertEquals('OK', $response['error_code']);
        $this->assertArrayHasKey('gameLoginUrl', $response);
    }

    /**
     * 測試使用 connector 回收「歐博」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceAllBetGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 回收「歐博」遊戲餘額');

        // Arrange
        $target = 'all_bet';
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
        $before = $connector->balance($wallet)['response']['balance'];
        $expect = $before - $accessPoint;
        $addingBalance = $connector->withdraw($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response']['balance'];

        // Assert
        $this->assertEquals('OK', $addingBalance['error_code']);
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
        $this->console->writeln('測試使用 connector 登出「歐博」');

        // Arrange
        $target = 'all_bet';

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
