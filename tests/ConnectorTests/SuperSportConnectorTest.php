<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\ApiCaller\Exceptions\ApiCallerException;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * @package SuperPlatform\StationWallet\Tests\ConnectorTests
 */
class SuperSportConnectorTest extends BaseTestCase
{
    /**
     * 測試使用 connector 增加「體育」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddSuperSportGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 增加「體育」遊戲餘額');

        // Arrange
        $target = 'super_sport';
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
        try {
            $before = $connector->balance($wallet)['response']['point'];
            $expect = $before + $accessPoint;
            $addingBalance = $connector->deposit($wallet, $accessPoint)['response'];
            $actual = $connector->balance($wallet)['response']['point'];
        } catch (ApiCallerException $exception) {
            var_dump($exception->response());
        } catch (\Exception $exception) {
        }

        // Assert
        $this->assertEquals(999, $addingBalance['code']);
        $this->assertEquals($expect, $actual);
    }

    /**
     * 測試使用 connector 建立「體育」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildSuperSportGameAccount()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 建立「體育」遊戲帳號');

        // Arrange
        $target = 'super_sport';

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
                'act' => config('station_wallet.stations.super_sport.build.act'),
                'password' => $this->userPassword,
                'nickname' => 'TESTER',
                'up_account' => config('api_caller.super_sport.config.up_account'),
                'up_password' => config('api_caller.super_sport.config.up_password'),
                'level' => '1',
                /* optional*/
                'copy_target' => config('station_wallet.stations.super_sport.build.copyAccount'),
            ]);

        } catch (ApiCallerException $exception) {
            $this->assertEquals(912, $exception->response()['code']);
            $this->assertEquals('帳號已存在', $exception->response()['msg']);
        } catch (\Exception $exception) {
            dump($exception->response());
            $this->assertEquals(912, $exception->response()['code']);
            $this->assertEquals('帳號已存在', $exception->response()['msg']);
        }
    }

    /**
     * 測試使用 connector 複製「體育」範例會員帳號限紅到舊會員的限紅
     *
     * @throws \Exception
     */
    public function testUpdateMemberProfileLimit()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 複製「體育」範例會員帳號限紅到舊會員的限紅');

        // Arrange
        $target = 'super_sport';

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
            $response = $connector->updateProfile($wallet);
        } catch (ApiCallerException $exception) {
            var_dump($exception->response());
        } catch (\Exception $exception) {
           //
        }
        $this->assertEquals('200', array_get($response, 'http_code'));
        $this->assertEquals('複製設定成功', array_get($response, 'response.msg'));
    }

    /**
     * 測試使用 connector 取得「體育」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetSuperSportGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「體育」遊戲餘額');

        // Arrange
        $target = 'super_sport';

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
            $response = $connector->balance($wallet);
        } catch (ApiCallerException $exception) {
            var_dump($exception->response());
        } catch (\Exception $exception) {
            //
        }

        //Assert
        $this->assertEquals(999, $response['response']['code']);
        $this->assertArrayHasKey('point', $response['response']);
    }

    /**
     * 測試使用 connector 取得「體育」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetSuperSportGamePassport()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 取得「體育」遊戲通行證');

        // Arrange
        $target = 'super_sport';

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
            $response = $connector->passport($wallet);
        } catch (ApiCallerException $exception) {
//            var_dump($exception->response());
        } catch (\Exception $exception) {
//            var_dump($exception->getMessage());
        }

        //Assert
        $this->assertEquals(999, $response['response']['code']);
        $this->assertArrayHasKey('web_url', $response);
    }

    /**
     * 測試使用 connector 回收「體育」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceSuperSportGameBalance()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 回收「體育」遊戲餘額');

        // Arrange
        $target = 'super_sport';
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
        try {
            $before = $connector->balance($wallet)['response']['point'];
            $expect = $before - $accessPoint;
            $addingBalance = $connector->withdraw($wallet, $accessPoint)['response'];
            $actual = $connector->balance($wallet)['response']['point'];
        } catch (ApiCallerException $exception) {
            var_dump($exception->response());
        } catch (\Exception $exception) {
            //
        }

        // Assert
        $this->assertEquals(999, $addingBalance['code']);
        $this->assertEquals($expect, $actual);
    }

    /**
     * 測試使用 connector 登出「體育」
     *
     * @throws \Exception
     */
    public function testSuperSportLogout()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試使用 connector 登出「體育」');

        // Arrange
        $target = 'super_sport';

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
        $response = $connector->logout($wallet);

        // Assert
        $this->assertEquals(true, $response);
    }
}