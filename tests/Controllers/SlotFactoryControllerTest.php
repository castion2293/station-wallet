<?php

namespace SuperPlatform\StationWallet\Tests\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use SuperPlatform\StationWallet\Http\Controllers\SlotFactoryController;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;
use swoole_process;

class SlotFactoryControllerTest extends BaseTestCase
{
    /**
     * @var GuzzleClient
     */
    protected $client;

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
     * @var string
     */
    protected $submitUrl = '';

    /**
     * @var
     */
    protected $slotFactoryController;

    /**
     * 初始共用參數
     */
    public function setUp()
    {
        parent::setUp();

        $station = 'slot_factory';

        $this->client = new GuzzleClient();
        $this->submitUrl = 'https://laravel-sandbox.ap.ngrok.io/cmwallet';

        // 創建使用者
        $this->user = new User();
        $this->user->id = $this->userId;
        $this->user->password = $this->userPassword;
        $this->user->mobile = $this->gamePlayerMobile;
        $this->user->save();

        // 建立錢包
        $this->wallet = $this->user->buildStationWallets()->get($station);
        $this->wallet->balance = 100;
        $this->wallet->activated_status = 'yes';
        $this->wallet->save();

        // 建立controller
        $this->slotFactoryController = new SlotFactoryController();
    }

    /**
     * 測試檢查會員SF電子錢包狀態是開通且有效
     */
    public function testIsWalletAvailable()
    {
        $this->slotFactoryController->setMemberSlotFactorWallet($this->wallet->account);
        $result = $this->slotFactoryController->isWalletAvailable();

        $this->assertTrue($result);
    }

    /**
     * test login request
     */
    public function testLoginRequest()
    {
        // Arrange
        $params = [
            'SessionID' => 'sessionId',
            'AccountID' => $this->wallet->account,
            'GameName' => 'DoubleWinAgent',
            'AuthToken' => md5('DoubleWinAgent' . $this->wallet->account),
            'Action' => 'Login',
            'PlayerIP' => '45.76.208.158',
            'Timestamp' => now()->timestamp
        ];

        // Act
        $this->slotFactoryController->setMemberSlotFactorWallet($this->wallet->account);
        $this->slotFactoryController->login($params);

        $header = $this->slotFactoryController->header;
        $data = $this->slotFactoryController->responseData;

        // Assert
        $this->assertArrayHasKey('HMAC', $header);
        $this->assertArrayHasKey('AuthToken', $data);
        $this->assertEquals($this->wallet->balance, array_get($data, 'Balance') / 100);
    }

    /**
     * test play request
     */
    public function testPlayRequest()
    {
        // Arrange
        $betAmount = 9;
        $winAmount = 15;
        $winLoseAmount = $winAmount - $betAmount;
        $beforeBalance = $this->wallet->balance;

        $params = [
            'SessionID' => 'sessionId',
            'AccountID' => $this->wallet->account,
            'GameName' => 'DoubleWinAgent',
            'AuthToken' => md5('DoubleWinAgent' . $this->wallet->account),
            'Action' => 'Play',
            'PlayerIP' => '45.76.208.158',
            'Timestamp' => now()->timestamp,
            'SpinID' => 'caa7e69c-d873-4c92-860a-a3d10e7a9f88',
            'RoundID' => 'caa7e69c-d873-4c92-860a-a3d10e7a9f88',
            'TransactionID' => 'cbaeefbe-1bfd-4d67-9c51-d159ef7091db',
            'WinAmount' => strval($winAmount * 100),
            'BetAmount' => strval($betAmount * 100),
            'GambleGames' => false,
            'RoundEnd' => true,
        ];

        // Act
        $this->slotFactoryController->setMemberSlotFactorWallet($this->wallet->account);
        $this->slotFactoryController->play($params);

        $header = $this->slotFactoryController->header;
        $data = $this->slotFactoryController->responseData;

        // Assert
        $this->assertArrayHasKey('HMAC', $header);
        $this->assertArrayHasKey('Balance', $data);
        $this->assertEquals($beforeBalance + $winLoseAmount, array_get($data, 'Balance') / 100);
    }

    public function testRewardBonus()
    {
        // Arrange
        $winAmount = 40;
        $beforeBalance = $this->wallet->balance;

        $params = [
            'SessionID' => 'sessionId',
            'AccountID' => $this->wallet->account,
            'GameName' => 'DoubleWinAgent',
            'AuthToken' => md5('DoubleWinAgent' . $this->wallet->account),
            'Action' => 'RewardBonus',
            'PlayerIP' => '45.76.208.158',
            'Timestamp' => now()->timestamp,
            'SpinID' => '00000000-0000-0000-0000-000000000000',
            'RoundID' => '00000000-0000-0000-0000-000000000000',
            'TransactionID' => '6160e856-b1d6-4ce7-84b5-d2fd2dac97cb',
            'WinAmount' => strval($winAmount * 100),
            'FreeGames' => true,
            'FreeGameRemaining' => 10,
            'FreeGamePlayed' => 2,
            'Type' => 'Free_Game',
            'RoundEnd' => true,
        ];

        // Act
        $this->slotFactoryController->setMemberSlotFactorWallet($this->wallet->account);
        $this->slotFactoryController->rewardBonus($params);

        $header = $this->slotFactoryController->header;
        $data = $this->slotFactoryController->responseData;

        // Assert
        $this->assertArrayHasKey('HMAC', $header);
        $this->assertArrayHasKey('Balance', $data);
        $this->assertEquals($beforeBalance + $winAmount, array_get($data, 'Balance') / 100);
    }
}