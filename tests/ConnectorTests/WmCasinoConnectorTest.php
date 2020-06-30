<?php


namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

class WmCasinoConnectorTest extends BaseTestCase
{

    private $sTarget = 'wm_casino';

    /**
     * 測試使用 connector 增加「WM真人」遊戲餘額
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
                dump(array_get($response, 'response'));

                // === ASSERT ===
                $this->assertEquals('0', array_get($response, 'response.errorCode'));
                $this->assertArrayHasKey('cash', array_get($response, 'response.result'));
            }
        );
    }

    /**
     * 測試使用 connector 建立「WM真人」遊戲帳號
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
                    dump($response);
                    //第一次註冊成功 則顯示OK
                    $this->assertEquals('0', array_get($response, 'response.errorCode'));
                    //測試註冊，且註冊失敗，取得帳號重複 (DUPLICATE_USERNAME) 的錯誤訊息
                    $this->assertEquals(strtoupper("104"), array_get($response, 'response.errorCode'));

                }
            );
        } catch (ApiCallerException $exception) {
            $this->assertEquals(strtoupper("新增会员资料错误,此帐号已被使用!!"), array_get($exception->response(), 'errorMessage'));
        }
    }

    /**
     * 測試使用 connector 取得「WM真人」遊戲餘額
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
                dump(array_get($response, 'response'));
                // Assert
                $this->assertEquals('0', array_get($response, 'response.errorCode'));
                $this->assertArrayHasKey('result', array_get($response, 'response'));
            }
        );
    }

    /**
     * 測試使用 connector 取得「WM真人」遊戲通行證
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
                dump(array_get($response, 'response'));

                // Assert
                $this->assertEquals('0', array_get($response, 'response.errorCode'));
                $this->assertArrayHasKey('result', array_get($response, 'response'));
            }
        );
    }

    /**
     * 測試使用 connector 回收「WM真人」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceGameBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 回收遊戲餘額',
            function ($connector, $wallet) {
                $fbackPoint = 1;

                $aResponseFormatData = $connector->withdraw($wallet, $fbackPoint);
                $response = $aResponseFormatData;
                dump(array_get($response, 'response'));
                // Assert
                $this->assertEquals('0', array_get($response, 'response.errorCode'));
                $this->assertArrayHasKey('result', array_get($response, 'response'));
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
     * 測試使用 connector 登出「WM真人」
     *
     * @throws \Exception
     */
    public function testLogout(): void
    {
        $this->tryConnect(
            '測試使用 connector 登出「WM真人」',
            function ($connector, $wallet) {
                $response = $connector->logout($wallet);
                // Assert
                $this->assertEquals(true, $response);
            }
        );
    }
}