<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class DreamGameConnectorTest extends BaseTestCase
{
    /**
     * 測試使用 connector 增加「DG」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddDreamGameGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 增加「DG」遊戲餘額');

        // Arrange
        $target = 'dream_game';
        $accessPoint = 1000;

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
        $before = $connector->balance($wallet)['response']['member']['balance'];
        $expect = $before + $accessPoint;
        $addingBalance = $connector->deposit($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response']['member']['balance'];

        // Assert
        $this->assertEquals(0, $addingBalance['codeId']);
        $this->assertEquals($expect, $actual);
    }

    /**
     * 測試使用 connector 建立「DG」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildDreamGameGameAccount()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 建立「DG」遊戲帳號');

        // Arrange
        $target = 'dream_game';

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
            $this->assertEquals(116, $exception->response()['errorCode']);
        } catch (\Exception $exception) {
        }
    }

    /**
     * 測試使用 connector 增加「DG」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetDreamGameGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 增加「DG」遊戲餘額');

        // Arrange
        $target = 'dream_game';

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
        $this->assertEquals(0, $response['codeId']);
        $this->assertArrayHasKey('member', $response);
        $this->assertArrayHasKey('balance', $response['member']);
    }

    /**
     * 測試使用 connector 取得「DG」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetDreamGameGamePassport()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「DG」遊戲通行證');

        // Arrange
        $target = 'dream_game';

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
        $this->assertArrayHasKey('codeId', $response['response']);
        $this->assertEquals(0, $response['response']['codeId']);
        $this->assertArrayHasKey('token', $response['response']);
        $this->assertArrayHasKey('method', $response);
        $this->assertEquals('redirect', $response['method']);
        $this->assertArrayHasKey('web_url', $response);
        $this->assertArrayHasKey('mobile_url', $response);
    }

    /**
     * 測試使用 connector 回收「DG」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceDreamGameGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 回收「DG」遊戲餘額');

        // Arrange
        $target = 'dream_game';
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
        $before = $connector->balance($wallet)['response']['member']['balance'];
        $expect = $before - $accessPoint;
        $addingBalance = $connector->withdraw($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response']['member']['balance'];

        // Assert
        $this->assertEquals(0, $addingBalance['codeId']);
        $this->assertEquals($expect, $actual);
    }

    /**
     * 測試使用 connector 登出「DG」(因遊戲館未提供登出api, 這裡為發通知到 chat)
     *
     * @throws \Exception
     */
    public function testLogout(): void
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 登出「DG」(因遊戲館未提供登出api, 這裡為發通知到 chat)');
        // Arrange
        $target = 'dream_game';

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
