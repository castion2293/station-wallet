<?php

// namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

// use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
// use SuperPlatform\StationWallet\Tests\BaseTestCase;
// use SuperPlatform\StationWallet\Tests\User;

// class HongChowConnectorTest extends BaseTestCase
// {
//     private $sTarget = 'hong_chow';

//     /**
//      * @throws \Exception
//      */
//     public function testAddGameBalance(): void
//     {
//         $this->tryConnect(
//             '測試使用 connector 增加遊戲餘額',
//             function ($connector, $wallet) {
//                 $fAccessPoint = 100;

//                 $fBefore = array_get($connector->balance($wallet), 'response.data.balance');
//                 $fExpect = $fBefore + $fAccessPoint;
//                 $aResponseFormatData = $connector->deposit($wallet, $fAccessPoint);
//                 var_dump($aResponseFormatData['response']);
//                 $fActual = array_get($connector->balance($wallet), 'response.data.balance');

//                 // Assert
//                 $this->assertEquals(0, array_get($aResponseFormatData, 'response.code'));
//                 $this->assertEquals($fExpect, $fActual);
//             }
//         );
//     }

//     /**
//      * @throws \Exception
//      */
//     public function testBuildGameAccount(): void
//     {
//         $this->tryConnect(
//             '測試使用 connector 建立遊戲帳號',
//             function ($connector, $wallet) {
//                 $aResponseFormatData = $connector->build($wallet);
//                 var_dump($aResponseFormatData['response']);

//                 // Assert
//                 $this->assertEquals(0, array_get($aResponseFormatData, 'response.code'));
//             }
//         );
//     }

//     /**
//      * @throws \Exception
//      */
//     public function testGetGameBalance(): void
//     {
//         $this->tryConnect(
//             '測試使用 connector 取得遊戲餘額',
//             function ($connector, $wallet) {
//                 $aResponseFormatData = $connector->balance($wallet);
//                 var_dump($aResponseFormatData['response']);

//                 // Assert
//                 $this->assertEquals(0, array_get($aResponseFormatData, 'response.code'));
//             }
//         );
//     }

//     /**
//      * @throws \Exception
//      */
//     public function testGetGamePassport(): void
//     {
//         $this->tryConnect(
//             '測試使用 connector 取得遊戲通行證',
//             function ($connector, $wallet) {
//                 $aResponseFormatData = $connector->passport($wallet);
//                 var_dump($aResponseFormatData['response']);

//                 // Assert
//                 $this->assertEquals(0, array_get($aResponseFormatData, 'response.code'));
//                 $this->assertArrayHasKey('method', $aResponseFormatData);
//                 $this->assertEquals('redirect', array_get($aResponseFormatData, 'method'));
//                 $this->assertArrayHasKey('web_url', $aResponseFormatData);
//                 $this->assertArrayHasKey('mobile_url', $aResponseFormatData);
//                 $this->assertArrayHasKey('params', $aResponseFormatData);
//             }
//         );
//     }

//     /**
//      * @throws \Exception
//      */
//     public function testReduceGameBalance(): void
//     {
//         $this->tryConnect(
//             '測試使用 connector 回收遊戲餘額',
//             function ($connector, $wallet) {
//                 $fAccessPoint = 50;

//                 $fBefore = array_get($connector->balance($wallet), 'response.data.balance');
//                 $fExpect = $fBefore - $fAccessPoint;
//                 $aResponseFormatData = $connector->withdraw($wallet, $fAccessPoint);
//                 var_dump($aResponseFormatData['response']);
//                 $fActual = array_get($connector->balance($wallet), 'response.data.balance');

//                 // Assert
//                 $this->assertEquals(0, array_get($aResponseFormatData, 'response.code'));
//                 $this->assertEquals($fExpect, $fActual);
//             }
//         );
//     }

//     /**
//      * @param string $sTestDescription
//      * @param callable $cTestCase
//      * @throws \Exception
//      */
//     private function tryConnect(string $sTestDescription, callable $cTestCase): void
//     {
//         // 顯示測試案例描述
//         $this->console->writeln($this->sTarget . $sTestDescription);

//         // 連結器
//         $connector = $this->makeConnector($this->sTarget);

//         // 創建使用者
//         $user = new User();
//         $user->id = $this->userId;
//         $user->password = $this->userPassword;
//         $user->mobile = $this->gamePlayerMobile;
//         $user->save();

//         // Act
//         $wallet = $user->buildStationWallets()->get($this->sTarget);

//         $cTestCase($connector, $wallet);
//     }
// }