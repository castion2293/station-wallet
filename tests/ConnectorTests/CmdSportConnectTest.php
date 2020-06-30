<?php


namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class CmdSportConnectTest extends BaseTestCase
{
    private $sTarget = 'cmd_sport';

    /**
     * 測試使用 connector 建立「CMD體育」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildGameAccount()
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

                    // Assert
                    $this->assertEquals('0', array_get($response, 'response.Code'));
                    $this->assertNotSame([], array_get($response, 'response.Data'));
                }
            );
        } catch (ApiCallerException $exception) {
            $this->assertEquals($exception->getMessage(), 'Api caller receive failure response, use `$exception->response()` get more details.');
        }
    }

    /**
     * 測試使用 connector 取得「CMD體育」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetBalance()
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲餘額',
            function ($connector, $wallet) {
                $aResponseFormatData = $connector->balance($wallet);
                dump($aResponseFormatData);
                $response = array_get($aResponseFormatData, "response");

                // Assert
                $this->assertEquals('200', array_get($aResponseFormatData, 'http_code'));
                $this->assertEquals('0', array_get($response, 'Code'));
                $this->assertArrayHasKey('BetAmount', array_get($response, "Data.0"));
            }
        );
    }

    /**
     * 測試使用 connector 增加「CMD體育」遊戲餘額
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
                dump($aResponseFormatData);
                $response = array_get($aResponseFormatData, 'response');

                // === ASSERT ===
                $this->assertEquals('200', array_get($aResponseFormatData, 'http_code'));
                $this->assertEquals('0', array_get($response, 'Code'));
            }
        );
    }

    /**
     * 測試使用 connector 回收「CMD體育」遊戲餘額
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
                dump($aResponseFormatData);
                $response = array_get($aResponseFormatData, 'response');

                // === ASSERT ===
                $this->assertEquals('200', array_get($aResponseFormatData, 'http_code'));
                $this->assertEquals('0', array_get($response, 'Code'));
            }
        );
    }

    /**
     * 測試使用 connector 調整「CMD體育」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAdjustBalance()
    {
        $this->tryConnect(
            '測試使用 connector 調整「CMD體育」遊戲餘額',
            function ($connector, $wallet) {
                $finalBalance = 500;

                $aResponseFormatData = $connector->adjust($wallet, $finalBalance);

                $response = array_get($aResponseFormatData, "response");

                // Assert
                if (array_has($response, "Data.0")) {
                    $this->assertEquals($finalBalance, array_get($response, 'Data.0.BetAmount'));
                } else {
                    $this->assertEquals($finalBalance, array_get($response, 'Data.BetAmount'));

                }
            }
        );
    }

    /**
     * 測試使用 connector 取得「CMD體育」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetGamePassport()
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲通行證',
            function ($connector, $wallet) {
                $aResponseFormatData = $connector->passport($wallet);
                dump($aResponseFormatData);
                // Assert
                $this->assertArrayHasKey('web_url', $aResponseFormatData);
                $this->assertArrayHasKey('mobile_url', $aResponseFormatData);
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
     * 測試使用 connector 登出「CMD體育」
     *
     * @throws \Exception
     */
    public function testLogout()
    {
        $this->tryConnect(
            '測試使用 connector 登出 「CMD體育」',
            function ($connector, $wallet) {
                $response = $connector->logout($wallet);

                // Assert
                $this->assertEquals(true, $response);
            }
        );
    }
}