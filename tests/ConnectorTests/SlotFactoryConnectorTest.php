<?php

namespace SuperPlatform\StationWallet\Tests\ConnectorTests;

use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

class SlotFactoryConnectorTest extends BaseTestCase
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

        $station = 'slot_factory';

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
     * 測試使用 connector 建立「SF電子」遊戲帳號
     *
     * @throws \Exception
     */
    public function testBuildSlotFactoryGameAccount()
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 增加「SF電子」遊戲帳號');

            // Act
            $response = $this->connector->build($this->wallet, []);

            // Assert
            $this->assertArrayHasKey('account', $response);
        } catch (\Exception $exception) {
            // 為了讓每次測試不要重複一直建立帳號，這邊接到回應帳號已存在，不另外拋出例外，讓測試繼續進行
            $this->assertTrue(strpos($exception->getMessage(), 'ACCOUNT_ALREADY_EXIST') !== false);
        }
    }

    /**
     * 測試使用 connector 取得「SF電子」遊戲餘額
     *
     * @throws \Exception
     */
    public function testGetSlotFactoryGameBalance()
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 取得「SF電子」遊戲餘額');

            // Act
            $response = $this->connector->balance($this->wallet);

            // Assert
            $this->assertArrayHasKey('balance', $response);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * 測試使用 connector 取得「SF電子」遊戲通行證
     *
     * @throws \Exception
     */
    public function testGetGamePassport()
    {
        try {
            // 顯示測試案例描述
            $this->console->writeln('測試使用 connector 取得遊戲通行證');

            // Act
            $response = $this->connector->passport($this->wallet, ['game_id' => 'DoubleWinAgent']);
            print_r($response);
            $this->assertArrayHasKey('method', $response);
            $this->assertArrayHasKey('web_url', $response);
            $this->assertArrayHasKey('mobile_url', $response);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }
}