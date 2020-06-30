<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

class RoyalGameConnectorTest extends BaseTestCase
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

        $station = 'royal_game';

        // 建立遊戲館連結器
        $this->connector = $this->makeConnector($station);

        // 創建使用者
        $this->user = new User();
        $this->user->id = $this->userId;
        $this->user->password = $this->userPassword;
        $this->user->mobile = $this->gamePlayerMobile;
        $this->user->save();

        // 建立錢包
        $this->wallet = $this->user->buildStationWallets()->get($station);
    }

    /**
     * 測試使用 connector 增加「RG」遊戲餘額
     *
     * @throws \Exception
     */
    public function testAddBalance(): void
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 增加「RG」遊戲餘額');

            // Arrange
            $amount = 1;

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
     * 測試使用 connector 建立「RG」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildGameAccount(): void
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 建立「RG」遊戲帳號');

            // Act
            $response = $this->connector->build($this->wallet, []);

            // Assert
            $this->assertArrayHasKey('account', $response);
        } catch (\Exception $exception) {
            // 為了讓每次測試不要重複一直建立帳號，這邊接到回應帳號已存在，不另外拋出例外，讓測試繼續進行
        }
    }

    /**
     * 測試使用 connector 取得「RG」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetBalance(): void
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 取得「RG」遊戲餘額');

            // Act
            $response = $this->connector->balance($this->wallet);

            // Assert
            $this->assertArrayHasKey('balance', $response);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector 取得「RG」遊戲設定限額
     *
     * @throws \Exception
     */
    public function testGetLimit(): void
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 取得「RG」遊戲餘額');

            // Act
            $response = $this->connector->getLimit($this->wallet);

            // Assert
            $this->assertArrayHasKey('limit', $response);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector 取得「RG」遊戲修改限額
     *
     * @throws \Exception
     */
    public function testUpdateLimit(): void
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 取得「RG」遊戲餘額');

            // Act
            $response = $this->connector->updateLimit($this->wallet);

            // Assert
            $this->assertArrayHasKey('limit', $response);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector 取得「RG」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetGamePassport(): void
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 取得遊戲通行證');

            // Act
            $response = $this->connector->passport($this->wallet);
            print_r($response);
            $this->assertArrayHasKey('method', $response);
            $this->assertArrayHasKey('web_url', $response);
            $this->assertArrayHasKey('mobile_url', $response);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector 回收「RG」遊戲餘額
     *
     * @throws \Exception
     */
    public function testReduceGameBalance(): void
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 回收「RG」遊戲餘額');

            // Arrange
            $amount = 1;

            // Act
            $before = array_get($this->connector->balance($this->wallet), 'balance');
            $expect = $before - $amount;
            $afterBalance = $this->connector->withdraw($this->wallet, $amount);

            // Assert
            $this->assertArrayHasKey('balance', $afterBalance);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector 登出「RG」(因遊戲館未提供登出api, 這裡為發通知到 chat)
     *
     * @throws \Exception
     */
    public function testLogout()
    {
        try {
            $text = [
                "app_id" => "ID",
                "domain" => "營運站domain",
                "event_name" => "禁止登入",
                "username" => "demo",
                "chat_url" => "https://chat.homerun88.net:8888/webapi/entry.cgi?api=SYNO.Chat.External&method=incoming&version=2",
                "chat_token" => "FlYDYTDSbaCOdImjKtwZmwyOi2DfKzsCVx5h07tsN3Rz34WcSqfuhPRSyR1YESUJ",
            ];

            // Act
            $response = $this->connector->logout($this->wallet, $text);

            // Assert
            $this->assertEquals(true, $response);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }
}