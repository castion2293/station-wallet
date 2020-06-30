<?php


namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

class MgPokerConnectorTest extends BaseTestCase
{

    private $sTarget = 'mg_poker';

    /**
     * 測試使用 connector deposit增加「mg_poker」遊戲餘額
     * 
     * @throws \Exception
     */
    public function testAddBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 增加遊戲餘額',
            function ($connector, $wallet) {
                //=== ARRANGE ===
                $fAccessPoint = 4;

                // === ACTION ===
                $aResponseFormatData = $connector->deposit($wallet, $fAccessPoint);
                $response = $aResponseFormatData;
                dump($response);
                // dump(array_get($response, 'response'));

                // === ASSERT ===
                $this->assertEquals('0', array_get($response, 'response.code'));
                $this->assertArrayHasKey('balance', $response);
            }
        );
    }

    /**
     * 測試使用 connector build建立「mg_poker」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildGameAccount(): void
    {
        $this->tryConnect(
            '測試使用 connector 增加「mg_poker」遊戲帳號',
            function ($connector, $wallet) {
                //=== ARRANGE ===
                $fAccessPoint = 1;

                // === ACTION ===
                $aResponseFormatData = $connector->deposit($wallet, $fAccessPoint);
                $response = $aResponseFormatData;
                dump(array_get($response, 'response'));

                // === ASSERT ===
                $this->assertArrayHasKey('balance', $response);
            }
        );

    }

    /**
     * 測試使用 connector balance取得「mg_poker」遊戲餘額
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
                dump($response, 'response');
                // dump(array_get($response, 'response'));
                // Assert
                $this->assertEquals('0', array_get($response, 'response.code'));
                $this->assertArrayHasKey('balance', $response);
            }
        );
    }

    /**
     * 測試使用 connector passport取得「mg_poker」遊戲通行證
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
                dump($response);
                // dump(array_get($response, 'response'));

                // Assert
                $this->assertEquals('0', array_get($response, 'response.code'));
                $this->assertArrayHasKey('method', $response);
                $this->assertArrayHasKey('web_url', $response);
            }
        );
    }

    /**
     * 測試使用 connector withdraw回收「mg_poker」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceGameBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 回收遊戲餘額',
            function ($connector, $wallet) {
                $fbackPoint = 2;

                $aResponseFormatData = $connector->withdraw($wallet, $fbackPoint);
                $response = $aResponseFormatData;
                dump($response);
                // dump(array_get($response, 'response'));
                // Assert
                $this->assertEquals('0', array_get($response, 'response.code'));
                $this->assertArrayHasKey('balance', $response);
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
     * 測試使用 connector logout登出「mg_poker」
     *
     * @throws \Exception
     */
    public function testLogout(): void
    {
        $this->tryConnect(
            '測試使用 connector 登出「mg_poker」',
            function ($connector, $wallet) {
                $response = $connector->logout($wallet);
                // Assert
                $this->assertEquals(true, $response);
            }
        );
    }
}