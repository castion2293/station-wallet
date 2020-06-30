<?php


namespace SuperPlatform\StationWallet\Tests\ConnectorTests;
use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class UfaSportConnectorTest extends BaseTestCase
{
    private $sTarget = 'ufa_sport';

    /**
     * 測試使用 connector 增加「UFA 體育」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 增加遊戲餘額',
            function ($connector, $wallet) {
                $fAccessPoint = 1;
                // 進行存款
                $aResponseFormatData = $connector->deposit($wallet, $fAccessPoint);

                $response = $aResponseFormatData['response'];

                dump('存款後金額為'.$response['result']);

                // === ASSERT ===
                $this->assertEquals('0', array_get($response, 'errcode'));
            }
        );
    }

    /**
     * 測試使用 connector 建立「UFA 體育」遊戲帳號
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
                $response = $aResponseFormatData['response'];
//                dump($aResponseFormatData);

                //第一次註冊成功 則顯示errcode:0
                $this->assertEquals('1', array_get($response, 'errcode'));
            }
        );
    }

    /**
     * 測試使用 connector 取得「UFA」遊戲修改限額
     *
     * @throws \Exception
     */
    public function testUpdateLimit(): void
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲修改限額',
            function ($connector, $wallet) {

                $aResponseFormatData = $connector->updateLimit($wallet);
                $response = $aResponseFormatData['response'];

                // Assert
                $this->assertEquals('0', array_get($response, 'errcode'));
            }
        );
    }

    /**
     * 測試使用 connector 取得「UFA 體育」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲餘額',
            function ($connector, $wallet) {

                $aResponseFormatData = $connector->balance($wallet);
                $response = $aResponseFormatData['response'];

                dump('目前餘額為：'.$response['result']);

                // Assert
                $this->assertEquals('0', array_get($response, 'errcode'));

            }
        );
    }

    /**
     * 測試使用 connector 取得「UFA 體育」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetGamePassport(): void
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲通行證',
            function ($connector, $wallet) {
                $aResponseFormatData = $connector->passport($wallet);

                $response = $aResponseFormatData['response'];
                $host = array_get($response,'result.login.host');
                $params = array_get($response,'result.login.param');

                $web_url = $host. '?us='.$params['us']. '&k='. $params['k']. '&lang='. $params['lang']. '&accType='. $params['accType']. '&r='. $params['r'];
                dump('網頁版：'.$web_url);
                $mobile_url = 'http://sportmobi.time399.com/public/Validate.aspx?us='. $params['us']. '&k='.$params['k']. '&lang='. $params['lang']. '&accType='. $params['accType']. '&r='. $params['r'];
                dump('手機板：'.$mobile_url);
                // Assert
                $this->assertEquals('0', array_get($response, 'errcode'));
            }
        );
    }

    /**
     * 測試使用 connector 回收「UFA 體育」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceGameBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 回收遊戲餘額',
            function ($connector, $wallet) {
                $fbackPoint = 1;
                // 進行提款
                $aResponseFormatData = $connector->withdraw($wallet, $fbackPoint);
                $response = $aResponseFormatData['response'];

                dump('回收後金額為'.$response['result']);
                // Assert
                $this->assertEquals('0', array_get($response, 'errcode'));
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
     * 測試使用 connector 登出「UFA 體育」
     *
     * @throws \Exception
     */
    public function testLogout(): void
    {
        $this->tryConnect(
            '測試使用 connector 登出「UFA 體育」',
            function ($connector, $wallet) {
                // 進行登出
                $response = $connector->logout($wallet);

                // Assert
                $this->assertEquals(true, $response);
            }
        );
    }
}