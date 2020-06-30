<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

class IncorrectScoreConnectorTest extends BaseTestCase
{
    /**
     * 遊戲館連結器
     *
     * @var
     */
    protected $connector;

    /**
     *  會員使用者
     *
     * @var
     */
    protected $user;

    /**
     * 遊戲錢包
     *
     * @var
     */
    protected $wallet;

    /**
     * 初始共用參數
     */
    public function setUp()
    {
        parent::setUp();

        $station = 'incorrect_score';

        // 建立遊戲館連結器
        $this->connector = $this->makeConnector($station);

        // 創建使用者
        $this->user = new User();
        $this->user->id = $this->userId;
        $this->user->password = $this->userPassword;
        $this->user->mobile = $this->gamePlayerMobile;
        $this->user->save();

        // 建立錢包
        $this->wallet = $this->user->buildStationWallets()->where('station', $station)->first();
    }

    /**
     * 測試使用 connector 建立「反波膽」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildIncorrectScoreGameAccount()
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 建立「反波膽」遊戲帳號');

            // Act
            $response = $this->connector->build($this->wallet, []);

            // Assert
            $this->assertArrayHasKey('account', $response);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector 取得「反波膽」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetIncorrectScoreBalance()
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 取得「反波膽」遊戲餘額');

            // Act
            $response = $this->connector->balance($this->wallet);
            dump($response);
            // Assert
            $this->assertArrayHasKey('balance', $response);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector 增加「反波膽」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddIncorrectScoreBalance()
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 增加「反波膽」遊戲餘額');

            // Arrange
            $amount = 100;

            // Act
            $before = array_get($this->connector->balance($this->wallet), 'balance');
            $expect = $before + $amount;
            $afterBalance = $this->connector->deposit($this->wallet, $amount);

            // Assert
            $this->assertEquals($expect, array_get($afterBalance, 'balance'));
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector 回收「反波膽」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceIncorrectScoreBalance()
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 回收「反波膽」遊戲餘額');

            // Arrange
            $amount = 100;

            // Act
            $before = array_get($this->connector->balance($this->wallet), 'balance');
            $expect = $before - $amount;
            $afterBalance = $this->connector->withdraw($this->wallet, $amount);

            // Assert
            $this->assertEquals($expect, array_get($afterBalance, 'balance'));
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector 調整「反波膽」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAdjustBalance()
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 調整「反波膽」遊戲餘額');

            $finalBalance = 8000;

            // Act
            $response = $this->connector->adjust($this->wallet, $finalBalance);

            // Assert
            if (is_array($response)) {
                $this->assertEquals($finalBalance, array_get($response, 'balance'));
            } else {
                $this->assertEquals($finalBalance, $response);
            }

        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector 取得「反波膽」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetGamePassport()
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 取得遊戲通行證');

            // Act
            $response = $this->connector->passport($this->wallet);
            dump($response);
            $this->assertArrayHasKey('method', $response);
            $this->assertArrayHasKey('web_url', $response);
            $this->assertArrayHasKey('mobile_url', $response);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector「反波膽」取得會員在線狀態介面
     *
     * @throws \Exception
     */
    public function testGetUserOnline()
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 取得會員在線狀態介面');

            // Act
            $response = $this->connector->getUserOnline($this->wallet);
            $this->assertArrayHasKey('UserStatus', array_get($response, 'response.result'));
        } catch (\Exception $exception) {
            throw $exception;
        }
    }
}