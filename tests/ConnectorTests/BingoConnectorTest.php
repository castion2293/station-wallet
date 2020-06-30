<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use Illuminate\Http\Response;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class BingoConnectorTest extends BaseTestCase
{
    /**
     * 測試使用 connector 增加「賓果」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddBingoGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 增加「賓果」遊戲餘額');

        // Arrange
        $target = 'bingo';
        $accessPoint = 10;

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        $before = $connector->balance($wallet)['response']['balance'];
        $expect = $before + $accessPoint;
        $addingBalance = $connector->deposit($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response']['balance'];

        // Assert
        $this->assertEquals('deposit', $addingBalance['action']);
        $this->assertEquals($expect, $actual);
    }

    /**
     * 測試使用 connector 建立「賓果」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildBingoGameAccount()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 建立「賓果」遊戲帳號');

        // Arrange
        $target = 'bingo';

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        try {
            $connector->build($wallet, [
                'password' => $this->userPassword,
                'password_again' => $this->userPassword,
                'name' => $user->id,
            ]);
        } catch (\Exception $exception) {
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->response()['code']);
        }
    }

    /**
     * 測試使用 connector 取得「賓果」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetBingoGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「賓果」遊戲餘額');

        // Arrange
        $target = 'bingo';

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        $response = $connector->balance($wallet)['response'];

        // Assert
        $this->assertArrayHasKey('balance', $response);
    }

    /**
     * 測試使用 connector 取得「賓果」限額
     *
     * @throws \Exception
     */
    public function testGetLimit()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「賓果」限額');

        // Arrange
        $target = 'bingo';

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        $response = $connector->betLimit($wallet);

        // Assert
        $this->assertEquals('200', array_get($response, 'http_code'));
    }

    /**
     * 測試使用 connector 取得「賓果」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetBingoGamePassport()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「賓果」遊戲通行證');

        // Arrange
        $target = 'bingo';

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        $response = $connector->passport($wallet)['response'];

        // Assert
        $this->assertArrayHasKey('play_url', $response);
        $this->assertArrayHasKey('mobile_url', $response);
    }

    /**
     * 測試使用 connector 回收「賓果」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceBingoGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 回收「賓果」遊戲餘額');

        // Arrange
        $target = 'bingo';
        $accessPoint = 10;

        // 連結器
        $connector = $this->makeConnector($target);

        // 創建使用者
        $user = new User();
        $user->id = $this->userId;
        $user->password = $this->userPassword;
        $user->mobile = $this->gamePlayerMobile;
        $user->save();

        // Act
        $wallet = $user->buildStationWallets()->get($target);
        $before = $connector->balance($wallet)['response']['balance'];
        $expect = $before - $accessPoint;
        $addingBalance = $connector->withdraw($wallet, $accessPoint)['response'];
        $actual = $connector->balance($wallet)['response']['balance'];

        // Assert
        $this->assertEquals('withdraw', $addingBalance['action']);
        $this->assertEquals($expect, $actual);
    }
}