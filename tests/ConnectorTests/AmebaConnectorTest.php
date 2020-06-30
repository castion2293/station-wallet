<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * Class AmebaConnectorTest
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class AmebaConnectorTest extends BaseTestCase
{
    private $sTarget = 'ameba';

    /**
     * @throws \Exception
     */
    public function testAddBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 增加遊戲餘額',
            function ($connector, $wallet) {
                $fAccessPoint = 100;

                $fBefore = $connector->balance($wallet)['balance'];
                $fExpect = $fBefore + $fAccessPoint;
                $aResponseFormatData = $connector->deposit($wallet, $fAccessPoint);
                var_dump($aResponseFormatData['response']);
                $actual = $connector->balance($wallet)['balance'];

                // Assert
                $this->assertEquals('OK', array_get($aResponseFormatData, 'response.error_code'));
                $this->assertEquals($fExpect, $actual);
            }
        );
    }

    /**
     * @throws \Exception
     */
    public function testBuildGameAccount(): void
    {
        $this->tryConnect(
            '測試使用 connector 建立遊戲帳號',
            function ($connector, $wallet) {
                try {
                    $aResponseFormatData = $connector->build($wallet);
                    var_dump($aResponseFormatData['response']);
                } catch (ApiCallerException $exception) {
                    var_dump($exception->response());
                    $this->assertEquals('PlayerAlreadyExists', $exception->response()['errorCode']);
                } catch (\Exception $exception) {
                    var_dump($exception->getMessage());
                }
            }
        );
    }

    /**
     * @throws \Exception
     */
    public function testGetBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲餘額',
            function ($connector, $wallet) {
                $aResponseFormatData = $connector->balance($wallet);
                var_dump($aResponseFormatData['response']);

                // Assert
                $this->assertEquals('OK', array_get($aResponseFormatData, 'response.error_code'));
            }
        );
    }

    /**
     * @throws \Exception
     */
    public function testGetGamePassport(): void
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲通行證',
            function ($connector, $wallet) {
                $aResponseFormatData = $connector->passport($wallet);
                var_dump($aResponseFormatData['response']);

                // Assert
                $this->assertEquals('OK', array_get($aResponseFormatData, 'response.error_code'));
            }
        );
    }

    /**
     * @throws \Exception
     */
    public function testReduceGameBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 回收遊戲餘額',
            function ($connector, $wallet) {
                $fAccessPoint = 50;

                $fBefore = $connector->balance($wallet)['balance'];
                $fExpect = $fBefore - $fAccessPoint;
                $aResponseFormatData = $connector->withdraw($wallet, $fAccessPoint);
                var_dump($aResponseFormatData['response']);
                $actual = $connector->balance($wallet)['balance'];

                // Assert
                $this->assertEquals('OK', array_get($aResponseFormatData, 'response.error_code'));
                $this->assertEquals($fExpect, $actual);
            }
        );
    }

    /**
     * @param string $sTestDescription
     * @param callable $cTestCase
     * @throws \Exception
     */
    private function tryConnect(string $sTestDescription, callable $cTestCase): void
    {
        // 顯示測試案例描述
        $this->console->writeln($this->sTarget . $sTestDescription);

        // 連結器
        $connector = $this->makeConnector($this->sTarget);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($this->sTarget);

        $cTestCase($connector, $wallet);
    }

    public function testLogout(): void
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 登出「AMEBA」');
        // Arrange
        $target = 'ameba';

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