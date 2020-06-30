<?php


namespace SuperPlatform\StationWallet\Tests\Controllers;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Request;
use SuperPlatform\StationWallet\Http\Controllers\IncorrectScoreController;
use SuperPlatform\StationWallet\Http\Controllers\SlotFactoryController;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

class IncorrectScoreControllerTest extends BaseTestCase
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
    protected $incorrectScoreController;

    /**
     * 初始共用參數
     */
    public function setUp()
    {
        parent::setUp();

        $station = 'master';

        $this->client = new GuzzleClient();
        $this->submitUrl = 'https://super-platform.ngrok.io';

        // 創建使用者
        $this->user = new User();
        $this->user->id = $this->userId;
        $this->user->password = $this->userPassword;
        $this->user->mobile = $this->gamePlayerMobile;
        $this->user->save();

        // 建立錢包
        $this->wallet = $this->user->buildStationWallets($station)->get($station);
        $this->wallet->balance = 100;
        $this->wallet->activated_status = 'yes';
        $this->wallet->save();

        // 建立controller
        $this->incorrectScoreController = new IncorrectScoreController();
    }

    /**
     * 測試檢查會員反波膽錢包狀態是開通且有效
     */
    public function testIsWalletAvailable()
    {
        $this->incorrectScoreController->setMemberIncorrectScoreWallet($this->wallet->account);
        $result = $this->incorrectScoreController->isWalletAvailable();

        $this->assertTrue($result);
    }

    /**
     * test GetMemberBalance request
     */
    public function testGetMemberBalance()
    {
        // Arrange
        $request = new Request();
        $request->setMethod('POST');
        $request->request->add([
            'memberId' =>  $this->wallet->account,
            'signature' => '4c977ca213d84297ba7cfc6a81ab5502',
            'cmd' => 'GetBalance',
        ]);

        // Act
        $response = $this->incorrectScoreController->getMemberBalance($request);

        $responseData = data_get(json_decode($response->getContent()), 'data.balance');

        // Assert
        $this->assertEquals($this->wallet->balance, $responseData);

    }

    /**
     * test 建立注单/玩家扣款
     */
    public function testCreateBetLog()
    {
        // Arrange
        $request = new Request();
        $request->setMethod('POST');
        $request->request->add([
            "signature" => '4c977ca213d84297ba7cfc6a81ab5502',
            "memberId" => $this->wallet->account,
            "cmd" => "CreateBetLog",
            "betLogId" => "201801310000001",
            "isTrial" =>  false,
            "status" =>  0,
            "playType" => [
                "version" => 1,
                "desc" => "篮球台盘大小球 尼尔森巨人 vs 塔拉纳基山脉 小182.5@0.880  金額 100.00 15202725",
                "ticketId" => "15202725",
                "catId" => 16,
                "wagerTypeId" => 104,
                "league" => "MLB",
                "homeTeam" => "尼尔森巨人",
                "awayTeam" => "塔拉纳基山脉",
                "odds" => 0.88,
                "oddsdesc" => "香港盘"
            ],
              "result" => "",
              "note" => "香港盘",
              "currency" => "CNY",
              "betAmount" => 10,
              "validBetAmount" => 100,
              "betAt" => "2018-01-22 12:00:00",
              "odds" => 1.89,
              "ipAddress" => "127.0.0.1"
        ]);

        // Act
        $response = $this->incorrectScoreController->createBetLog($request);

        $responseData = data_get(json_decode($response->getContent()), 'ok');

        // Assert
        $this->assertEquals(true, $responseData);

    }

    /**
     * test 派彩入款
     */
    public function testDeposit()
    {
        // Arrange
        $request = new Request();
        $request->setMethod('POST');
        $request->request->add([
          "signature" => '4c977ca213d84297ba7cfc6a81ab5502',
          "cmd" => "deposit",
          "notifyId" => "20181011020001",
          "cashes" => [
          [
              "ticketNo" => "15202725",
              "memberId" => $this->wallet->account,
              "amount" => 54.05,
              "dtype"=> "DP",
            ],
            [
              "ticketNo" => "15202726",
              "memberId" => $this->wallet->account,
              "amount" => 64.05,
              "dtype" => "DP"
            ]
        ]
        ]);

        // Act
        $response = $this->incorrectScoreController->deposit($request);

        $responseData = data_get(json_decode($response->getContent()), 'ok');

        // Assert
        $this->assertEquals(true, $responseData);
    }
}