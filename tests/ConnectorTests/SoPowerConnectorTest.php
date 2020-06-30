<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class SoPowerConnectorTest extends BaseTestCase
{

    private $sTarget = 'so_power';

    /**
     * 測試使用 connector 增加「手中寶」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 增加遊戲餘額',
            function ($connector, $wallet) {
                //=== ARRANGE ===
                $fAccessPoint = 1;

                // === ACTION ===
                $aResponseFormatData = $connector->deposit($wallet, $fAccessPoint);
                $response = $aResponseFormatData;
                dump(array_get($response, 'response.data.credit'));

                // === ASSERT ===
                $this->assertEquals('OK', array_get($response, 'response.message'));
                $this->assertArrayHasKey('credit', array_get($response, 'response.data'));
            }
        );
    }

    /**
     * 測試使用 connector 建立「手中寶」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildGameAccount(): void
    {
        try {
            $this->tryConnect(
            /**
             * @param $connector
             * @param $wallet
             */
                '測試使用 connector 建立遊戲帳號',
                function ($connector, $wallet) {

                    $aResponseFormatData = $connector->build($wallet);
                    $response = $aResponseFormatData;
                    //第一次註冊成功 則顯示OK
                    $this->assertEquals('OK', array_get($response, 'response.message'));
                    //測試註冊，且註冊失敗，取得帳號重複 (DUPLICATE_USERNAME) 的錯誤訊息
                    $this->assertEquals(strtoupper("DUPLICATE_USERNAME"), array_get($response, 'response.message'));

                }
            );
        } catch (ApiCallerException $exception) {
            $this->assertEquals(strtoupper("DUPLICATE_USERNAME"), array_get($exception->response(), 'message'));
        }
    }

    /**
     * 測試使用 connector 取得「手中寶」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲餘額',
            function ($connector, $wallet) {

                $aResponseFormatData = $connector->balance($wallet);
                $response = $aResponseFormatData;
                dump(array_get($response, 'response.data.credit'));
                // Assert
                $this->assertEquals("OK", array_get($response, 'response.message'));
                $this->assertArrayHasKey('credit', array_get($response, 'response.data'));
            }
        );
    }

    /**
     * 測試使用 connector 取得「手中寶」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetGamePassport(): void
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲通行證',
            function ($connector, $wallet) {
                $aResponseFormatData = $connector->passport($wallet);
                $response = $aResponseFormatData;
                dump(array_get($response, 'response.data.token'));

                // Assert
                $this->assertEquals("OK", array_get($response, 'response.message'));
                $this->assertArrayHasKey('token', array_get($response, 'response.data'));
            }
        );
    }

    /**
     * 測試使用 connector 回收「手中寶」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceGameBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 回收遊戲餘額',
            function ($connector, $wallet) {
                $fbackPoint = -1;

                $aResponseFormatData = $connector->withdraw($wallet, $fbackPoint);
                $response = $aResponseFormatData;
                dump(array_get($response, 'response.data.credit'));
                // Assert
                $this->assertEquals('OK', array_get($response, 'response.message'));
                $this->assertArrayHasKey('credit', array_get($response, 'response.data'));
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
        // 抓到遊戲錢包
        $wallet = $user->buildStationWallets()->get($this->sTarget);

        $cTestCase($connector, $wallet);

    }

    /**
     * 測試使用 connector 登出「手中寶」(因遊戲館未提供登出api, 這裡為發通知到 chat)
     *
     * @throws \Exception
     */
    public function testLogout()
    {
        $this->tryConnect(
            '測試使用 connector 登出「手中寶」',
            function ($connector, $wallet) {
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
        );
    }
}