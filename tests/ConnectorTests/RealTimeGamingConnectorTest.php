<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

class RealTimeGamingConnectorTest extends BaseTestCase
{
    private $sTarget = 'real_time_gaming';

    /**
     * 測試使用 connector 增加「RTG」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 增加遊戲餘額',
            function ($connector, $wallet) {
                $fAccessPoint = 1000;
                // 進行存款
                $aResponseFormatData = $connector->deposit($wallet, $fAccessPoint);
                // 取得存款後遊戲餘額
                $fActual = $connector->balance($wallet)['balance'];

                dump('存款後金額： '.$fActual);

                // === ASSERT ===
                $this->assertEquals('OK', array_get($aResponseFormatData, 'response.errorMessage'));
                $this->assertEquals('False', array_get($aResponseFormatData, 'response.errorCode'));


            }
        );
    }

    /**
     * 測試使用 connector 建立「RTG」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildGameAccount(): void
    {
        $this->tryConnect(
        /**
         * @param $connector
         * @param $wallet
         */
            '測試使用 connector 建立遊戲帳號',
            function ($connector, $wallet) {
                $aResponseFormatData = $connector->build($wallet);

                dump($aResponseFormatData);

                //第一次註冊成功 則顯示OK
                $this->assertEquals('201', array_get($aResponseFormatData, 'response.http_code'));
            }
        );
    }

    /**
     * 測試使用 connector 取得「RTG」遊戲餘額
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

                dump(array_get($response,'balance'));
                // Assert
                $this->assertArrayHasKey('balance', $response);

            }
        );
    }

    /**
     * 測試使用 connector 取得「RTG」遊戲通行證
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

                dump(array_get($response, 'response.instantPlayUrl'));
                // Assert
                $this->assertEquals("200", array_get($response, 'http_code'));
            }
        );
    }

    /**
     * 測試使用 connector 取得「RTG」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetGamePassportByGameId(): void
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲通行證',
            function ($connector, $wallet) {
                $aResponseFormatData = $connector->passport($wallet, ['game_id' => '2162689']);
                $response = $aResponseFormatData;

                dump(array_get($response, 'response.instantPlayUrl'));
                // Assert
                $this->assertEquals("200", array_get($response, 'http_code'));
            }
        );
    }

    /**
     * 測試使用 connector 回收「RTG」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceGameBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 回收遊戲餘額',
            function ($connector, $wallet) {
                $fbackPoint = 50;
                // 進行提款
                $aResponseFormatData = $connector->withdraw($wallet, $fbackPoint);
                // 查詢提款後的餘額
                $fActual = $connector->balance($wallet)['balance'];
                dump('提款後金額： '.$fActual);
                // Assert
                $this->assertEquals('OK', array_get($aResponseFormatData, 'response.errorMessage'));
                $this->assertEquals('False', array_get($aResponseFormatData, 'response.errorCode'));
                $this->assertArrayHasKey('balance', $aResponseFormatData);
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
     * 測試使用 connector 登出「RTG」(因遊戲館未提供登出api, 這裡為發通知到 chat)
     *
     * @throws \Exception
     */
    public function testLogout()
    {
        $this->tryConnect(
            '測試使用 connector 登出「RTG」',
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