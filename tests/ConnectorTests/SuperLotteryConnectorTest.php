<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class SuperLotteryConnectorTest extends BaseTestCase
{
    /**
     * 測試使用 connector 增加「Lottery」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddSuperLotteryGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 增加「super_lottery」遊戲餘額');

        // Arrange
        $target = 'super_lottery';

        // 連結器
        $connector = $this->makeConnector($target);
        $accessPoint = 1;

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);

        $before = $connector->balance($wallet)['response']['data']['point'];
        $expect = $before + $accessPoint;
        $addingBalance = $connector->deposit($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response']['data']['point'];

        // Assert
        $this->assertEquals(999, $addingBalance['code']);
        $this->assertEquals($expect, $actual);
    }

    /**
     * 測試使用 connector 建立「Lottery 101」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildSuperLotteryGameAccount()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 建立「super_lottery」遊戲帳號');

        // Arrange
        $target = 'super_lottery';

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
            $response = $connector->build($wallet);
        } catch (ApiCallerException $exception) {
            $this->assertEquals(909, $exception->response()['errorCode']);
        } catch (\Exception $exception) {
        }
    }

    /**
     * 測試使用 connector 增加「super_lottery」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetSuperLotteryGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 增加「super_lottery」遊戲餘額');

        // Arrange
        $target = 'super_lottery';

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
        $this->assertEquals(999, array_get($response, 'code'));
        $this->assertArrayHasKey('data', $response);
    }

    /**
     * 測試使用 connector 取得「super_lottery」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetSuperLotteryGamePassport()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「super_lottery」遊戲通行證');

        // Arrange
        $target = 'super_lottery';

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

        // Assert
        $this->assertArrayHasKey('method', $response);
        $this->assertEquals('post', $response['method']);
        $this->assertArrayHasKey('web_url', $response);
        $this->assertArrayHasKey('mobile_url', $response);
        $this->assertArrayHasKey('params', $response);
    }

    /**
     * 測試使用 connector 回收「super_lottery」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceSuperLotteryGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 回收「super_lottery」遊戲餘額');

        // Arrange
        $target = 'super_lottery';
        $accessPoint = 1;

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
        $before = $connector->balance($wallet)['response']['data']['point'];
        $expect = $before - $accessPoint;
        $addingBalance = $connector->withdraw($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response']['data']['point'];

        // Assert
        $this->assertEquals(999, $addingBalance['code']);
        $this->assertEquals($expect, $actual);
    }

    /**
     * 測試使用 connector 登出「super_lottery」(因遊戲館未提供登出api, 這裡為發通知到 chat)
     *
     * @throws \Exception
     */
    public function testLogout()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 登出「super_lottery」');

        // Arrange
        $target = 'super_lottery';

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

        $text = [
            "app_id" => "ID",
            "domain" => "營運站domain",
            "event_name" => "禁止登入",
            "username" => "demo",
            "chat_url" => "https://chat.homerun88.net:8888/webapi/entry.cgi?api=SYNO.Chat.External&method=incoming&version=2",
            "chat_token" => "FlYDYTDSbaCOdImjKtwZmwyOi2DfKzsCVx5h07tsN3Rz34WcSqfuhPRSyR1YESUJ",
        ];
        $response = $connector->logout($wallet, $text);

        // Assert
        $this->assertEquals(true, $response);
    }

}