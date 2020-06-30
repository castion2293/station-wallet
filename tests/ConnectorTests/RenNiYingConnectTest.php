<?php


namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class RenNiYingConnectTest extends BaseTestCase
{
    private $sTarget = 'ren_ni_ying';

    /**
     * 測試使用 connector 建立「任你贏」遊戲帳號
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
                    $this->assertArrayHasKey('data', $response['response']);
                }
            );
        } catch (ApiCallerException $exception) {
            $this->assertEquals($exception->getMessage(), 'Api caller receive failure response, use `$exception->response()` get more details.');
        }
    }

    /**
     * 測試使用 connector 取得「任你贏」遊戲餘額
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

                // Assert
                $this->assertEquals('200', array_get($response, 'http_code'));
                $this->assertArrayHasKey('balance', $response);
            }
        );
    }

    /**
     * 測試使用 connector 增加「任你贏」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddBalance()
    {
        $this->tryConnect(
            '測試使用 connector 增加遊戲餘額',
            function ($connector, $wallet) {
                //=== ARRANGE ===
                $fAccessPoint = 100;

                // === ACTION ===
                $aResponseFormatData = $connector->deposit($wallet, $fAccessPoint);
                $response = $aResponseFormatData;

                // === ASSERT ===
                $this->assertEquals('200', array_get($response, 'http_code'));
                $this->assertArrayHasKey('balance', $response);
            }
        );
    }

    /**
     * 測試使用 connector 回收「任你贏」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceGameBalance()
    {
        $this->tryConnect(
            '測試使用 connector 回收遊戲餘額',
            function ($connector, $wallet) {
                $fbackPoint = 1;

                $aResponseFormatData = $connector->withdraw($wallet, $fbackPoint);
                $response = $aResponseFormatData;

                // Assert
                $this->assertEquals('200', array_get($response, 'http_code'));
                $this->assertArrayHasKey('balance', $response);
            }
        );
    }

    /**
     * 測試使用 connector 調整「任你贏」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAdjustBalance()
    {
        $this->tryConnect(
            '測試使用 connector 調整「任你贏」遊戲餘額',
            function ($connector, $wallet) {
                $finalBalance = 5;

                $aResponseFormatData = $connector->adjust($wallet, $finalBalance);
                $response = $aResponseFormatData;

                // Assert
                if (is_array($response)) {
                    $this->assertEquals($finalBalance, array_get($response, 'balance'));
                } else {
                    $this->assertEquals($finalBalance, $response);
                }
            }
        );
    }

    /**
     * 測試使用 connector 取得「任你贏」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetGamePassport()
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲通行證',
            function ($connector, $wallet) {
                $aResponseFormatData = $connector->passport($wallet);
                $response = $aResponseFormatData;

                // Assert
                $this->assertEquals('200', array_get($response, 'http_code'));
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
     * 測試使用 connector 登出「任你贏」
     *
     * @throws \Exception
     */
    public function testLogout()
    {
        $this->tryConnect(
            '測試使用 connector 登出 「任你贏」',
            function ($connector, $wallet) {
                $response = $connector->logout($wallet);

                // Assert
                $this->assertEquals(true, $response);
            }
        );
    }
}