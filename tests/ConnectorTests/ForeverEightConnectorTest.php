<?php


namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

class ForeverEightConnectorTest extends BaseTestCase
{
    private $sTarget = 'forever_eight';

    /**
     * 測試使用 connector 建立「AV 電子」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildGameAccount()
    {
        try {
            $this->tryConnect(
                '測試使用 connector 建立遊戲帳號',
                function ($connector, $wallet) {
                    $aResponseFormatData = $connector->build($wallet);
                    $response = $aResponseFormatData;

                    // Assert
                    $this->assertEquals('1', $response['response']['Status']);
                }
            );
        } catch (ApiCallerException $exception) {
            $this->assertEquals($exception->getMessage(), 'Api caller receive failure response, use `$exception->response()` get more details.');
        }
    }

    /**
     * 測試使用 connector 取得「AV 電子」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetBalance()
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲餘額',
            function ($connector, $wallet) {
                $aResponseFormatData = $connector->balance($wallet);
                $response = $aResponseFormatData;
                print_r(array_get($response, 'response.Data'));
                // Assert
                $this->assertEquals('1', $response['response']['Status']);
                $this->assertArrayHasKey('balance', $response);
            }
        );
    }

    /**
     * 測試使用 connector 增加「AV 電子」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddBalance()
    {
        $this->tryConnect(
            '測試使用 connector 增加遊戲餘額',
            function ($connector, $wallet) {
                //=== ARRANGE ===
                $fAccessPoint = 10;

                // === ACTION ===
                $aResponseFormatData = $connector->deposit($wallet, $fAccessPoint);
                $response = $aResponseFormatData;

                // === ASSERT ===
                $this->assertEquals('1', $response['response']['Status']);
            }
        );
    }

    /**
     * 測試使用 connector 回收「AV 電子」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceGameBalance()
    {
        $this->tryConnect(
            '測試使用 connector 回收遊戲餘額',
            function ($connector, $wallet) {
                $fbackPoint = 10;

                $aResponseFormatData = $connector->withdraw($wallet, $fbackPoint);
                $response = $aResponseFormatData;

                // Assert
                $this->assertEquals('1', $response['response']['Status']);
            }
        );
    }

    /**
     * 測試使用 connector 調整「AV 電子」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAdjustBalance()
    {
        $this->tryConnect(
            '測試使用 connector 調整「AV 電子」遊戲餘額',
            function ($connector, $wallet) {
                $finalBalance = 3;

                $aResponseFormatData = $connector->adjust($wallet, $finalBalance);
                $response = $aResponseFormatData;

                // Assert
                $this->assertEquals($finalBalance, $response);
            }
        );
    }

    /**
     * 測試使用 connector 取得「AV 電子」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetGamePassport()
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲通行證',
            function ($connector, $wallet) {
                $aResponseFormatData = $connector->passport($wallet, ['game_id' => '1022']);
                $response = $aResponseFormatData;

                // Assert
                $this->assertEquals('1', $response['response']['Status']);
                $this->assertArrayHasKey('web_url', $response);
                $this->assertArrayHasKey('mobile_url', $response);
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
     * 測試使用 connector 登出「AV 電子」
     *
     * @throws \Exception
     */
    public function testLogout()
    {
        // 顯示測試案例描述
        $this->console->writeln($this->sTarget . "測試使用 connector 登出「AV 電子」");
        // 連結器
        $connector = $this->makeConnector($this->sTarget);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        $text = [
            "app_id" => "ID",
            "domain" => "營運站domain",
            "event_name" => "禁止登入",
            "username" => "demo",
            "chat_url" => "https://chat.homerun88.net:8888/webapi/entry.cgi?api=SYNO.Chat.External&method=incoming&version=2",
            "chat_token" => "FlYDYTDSbaCOdImjKtwZmwyOi2DfKzsCVx5h07tsN3Rz34WcSqfuhPRSyR1YESUJ",
        ];

        // 抓到遊戲錢包
        $wallet = $user->buildStationWallets()->get($this->sTarget);
        $response = $connector->logout($wallet, $text);
        $this->assertEquals(true, $response);
    }
}
