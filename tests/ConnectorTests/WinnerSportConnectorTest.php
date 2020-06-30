<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use Exception;
use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Connectors\Connector;
use SuperPlatform\StationWallet\Models\StationWallet;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

class WinnerSportConnectorTest extends BaseTestCase
{
    private $sTarget = 'winner_sport';

    /**
     * @throws Exception
     */
    public function testBuildGameAccount(): void
    {
        $this->tryConnect(
            '測試使用 connector 建立遊戲帳號',
            function (Connector $oConnector, StationWallet $oWallet): void {
                $aResponseFormatData = $oConnector->build($oWallet);
                var_dump($aResponseFormatData['response']);

                if (array_get($aResponseFormatData, 'response.code') === '002') $this->markTestSkipped('帳號重複，略過測試。');

                $this->assertEquals('001', array_get($aResponseFormatData, 'response.code'));
            }
        );
    }

    /**
     * @throws Exception
     */
    public function testGetBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲餘額',
            function (Connector $oConnector, StationWallet $oWallet): void {
                $aResponseFormatData = $oConnector->balance($oWallet);
                var_dump($aResponseFormatData['response']);

                $this->assertEquals('001', array_get($aResponseFormatData, 'response.code'));
            }
        );
    }

    /**
     * @throws Exception
     */
    public function testAddBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 增加遊戲餘額',
            function (Connector $oConnector, StationWallet $oWallet): void {
                $fAccessPoint = 100;

                $fBefore = $oConnector->balance($oWallet)['balance'];
                $fExpect = $fBefore + $fAccessPoint;
                $aResponseFormatData = $oConnector->deposit($oWallet, $fAccessPoint);
                var_dump($aResponseFormatData['response']);
                $fActual = $oConnector->balance($oWallet)['balance'];

                // Assert
                $this->assertEquals('001', array_get($aResponseFormatData, 'response.code'));
                $this->assertEquals($fExpect, $fActual);
            }
        );
    }

    /**
     * @throws Exception
     */
    public function testReduceGameBalance(): void
    {
        $this->tryConnect(
            '測試使用 connector 回收遊戲餘額',
            function (Connector $oConnector, StationWallet $oWallet): void {
                $fAccessPoint = 50;

                $fBefore = $oConnector->balance($oWallet)['balance'];
                $fExpect = $fBefore - $fAccessPoint;
                $aResponseFormatData = $oConnector->withdraw($oWallet, $fAccessPoint);
                var_dump($aResponseFormatData['response']);
                $fActual = $oConnector->balance($oWallet)['balance'];

                // Assert
                $this->assertEquals('001', array_get($aResponseFormatData, 'response.code'));
                $this->assertEquals($fExpect, $fActual);
            }
        );
    }

    /**
     * @throws Exception
     */
    public function testGetGamePassport(): void
    {
        $this->tryConnect(
            '測試使用 connector 取得遊戲通行證',
            function (Connector $oConnector, StationWallet $oWallet): void {
                $aResponseFormatData = $oConnector->passport($oWallet);
                var_dump($aResponseFormatData['response'], $aResponseFormatData['web_url'], $aResponseFormatData['mobile_url']);

                // Assert
                $this->assertEquals('001', array_get($aResponseFormatData, 'response.code'));
            }
        );
    }

    /**
     * @param string $sTestDescription
     * @param callable $cTestCase
     * @throws Exception
     */
    private function tryConnect(string $sTestDescription, callable $cTestCase): void
    {
        try {
            $this->console->writeln($this->sTarget . $sTestDescription);

            $oConnector = $this->makeConnector($this->sTarget);

            $oUser = new User();
            $oUser->id = $this->userId;
            $oUser->password = $this->userPassword;
            $oUser->mobile = $this->gamePlayerMobile;
            $oUser->save();

            $oWallet = $oUser->buildStationWallets()->get($this->sTarget);

            $cTestCase($oConnector, $oWallet);
        } catch (ApiCallerException $e) {
            $this->console->writeln($e->getMessage());
            $this->console->writeln($e->response());
            $this->fail('測試出錯，結束測試。');
        }
    }

    /**
     * 測試使用 connector 登出「贏家體育」(因遊戲館未提供登出api, 這裡為發通知到 chat)
     *
     * @throws \Exception
     */
    public function testLogout()
    {
        $this->tryConnect(
            '測試使用 connector 登出「贏家體育」',
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