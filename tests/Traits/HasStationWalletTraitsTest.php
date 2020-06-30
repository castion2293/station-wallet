<?php

namespace SuperPlatform\StationWallet\Tests\Traits;

use SuperPlatform\StationWallet\Models\StationWallet;
use SuperPlatform\StationWallet\Tests\BaseTestCase;
use SuperPlatform\StationWallet\Tests\User;

/**
 * Class HasStationWalletTraitsTest
 *
 * @package SuperPlatform\StationWallet\Tests\Traits
 */
class HasStationWalletTraitsTest extends BaseTestCase
{
    /**
     * 測試前的初始動作
     */
    public function setUp()
    {
        parent::setUp();

        // 暫時忽略 StationWallet 白名單的檢查
        StationWallet::unguard();
    }

    /**
     * 測試 buildMasterWallet 建立主錢包
     *
     * @throws \Exception
     */
    public function testBuildMasterWallet()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試 buildMasterWallet 建立主錢包');

        // Arrange
        $user1 = new User();
        $user1->id = 'a123456789';
        $user1->password = 'aa123456789';
        $user1->mobile = '9901238884';
        $user1->save();

        $user2 = new User();
        $user2->id = 'a123456788';
        $user2->password = 'aa123456788';
        $user2->mobile = '9901238883';
        $user2->save();

        // Act
        $user1->buildMasterWallet();
        $user2Wallet = $user2->buildMasterWallet('freezing', 100);

        // Assert
        $this->expectException('SuperPlatform\StationWallet\Exceptions\StationWalletExistsException');
        $user1Wallet = $user1->buildMasterWallet();
        $this->assertEquals(0, $user1Wallet['balance']);
        $this->assertEquals('active', $user1Wallet['status']);
        $this->assertEquals(100, $user2Wallet['balance']);
        $this->assertEquals('freezing', $user2Wallet['status']);
    }

    /**
     * 測試 buildStationWallets 建立遊戲館錢包
     */
    public function testBuildStationWallets()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試 buildStationWallets 建立遊戲館錢包');

        // Arrange
        // Users
        $users = [];
        $userAmount = 5;
        for ($i = 0; $i < $userAmount; $i++) {
            $user = new User();
            $user->id = 'a12345678' . $i;
            $user->password = 'aa12345678' . $i;
            $user->mobile = '990123888' . $i;
            $user->save();
            array_push($users, $user);
        }

        // Act
        // 建立錢包
        $wallets = [];
        foreach ($users as $user) {
            array_push($wallets, $user->buildStationWallets());
        }
        $user1 = $users[0];
        $user2 = $users[1];
        $user3 = $users[2];
        $user4 = $users[3];
        $user5 = $users[4];

        // Assert
        // 五位使用者
        // user 1
        $userWallets = collect($user1->wallets())->keyBy('station')->toArray();
        $this->assertEquals('active', $userWallets['all_bet']['status']);
        $this->assertEquals('active', $userWallets['bingo']['status']);
        $this->assertEquals('active', $userWallets['maya']['status']);
        $this->assertEquals('active', $userWallets['sa_gaming']['status']);
        $this->assertEquals('active', $userWallets['super_sport']['status']);
        // user 2
        $userWallets = collect($user2->wallets())->keyBy('station')->toArray();
        $this->assertEquals('active', $userWallets['all_bet']['status']);
        $this->assertEquals('active', $userWallets['bingo']['status']);
        $this->assertEquals('active', $userWallets['maya']['status']);
        $this->assertEquals('active', $userWallets['sa_gaming']['status']);
        $this->assertEquals('active', $userWallets['super_sport']['status']);
        // user 3
        $userWallets = collect($user3->wallets())->keyBy('station')->toArray();
        $this->assertEquals('active', $userWallets['all_bet']['status']);
        $this->assertEquals('active', $userWallets['bingo']['status']);
        $this->assertEquals('active', $userWallets['maya']['status']);
        $this->assertEquals('active', $userWallets['sa_gaming']['status']);
        $this->assertEquals('active', $userWallets['super_sport']['status']);
        // user 4
        $userWallets = collect($user4->wallets())->keyBy('station')->toArray();
        $this->assertEquals('active', $userWallets['all_bet']['status']);
        $this->assertEquals('active', $userWallets['bingo']['status']);
        $this->assertEquals('active', $userWallets['maya']['status']);
        $this->assertEquals('active', $userWallets['sa_gaming']['status']);
        $this->assertEquals('active', $userWallets['super_sport']['status']);
        // user 5
        $userWallets = collect($user5->wallets())->keyBy('station')->toArray();
        $this->assertEquals('active', $userWallets['all_bet']['status']);
        $this->assertEquals('active', $userWallets['bingo']['status']);
        $this->assertEquals('active', $userWallets['maya']['status']);
        $this->assertEquals('active', $userWallets['sa_gaming']['status']);
        $this->assertEquals('active', $userWallets['super_sport']['status']);

    }

    /**
     * 測試 buildStationWallets 建立所有遊戲站錢包資料，並預設部分錢包凍結停用
     */
    public function test_測試透過使用者_elquent_建立_所有_遊戲站錢包資料_並預設部分錢包凍結停用()
    {
        // 顯示測試案例描述
        $this->console->writeln('測試 buildStationWallets 建立所有遊戲站錢包資料，並預設部分錢包凍結停用');

        // Arrange
        // Users
        $users = [];
        $userAmount = 5;
        for ($i = 0; $i < $userAmount; $i++) {
            $user = new User();
            $user->id = 'a12345678' . $i;
            $user->password = 'aa12345678' . $i;
            $user->mobile = '990123888' . $i;
            $user->save();
            array_push($users, $user);
        }
        // 預設狀態
        $stations = [
            'all_bet',
            'bingo',
            'maya',
            'sa_gaming',
            'super_sport',
        ];
        $status = [
            'all_bet' => 'freezing',
            'bingo' => 'freezing',
            'maya' => 'freezing',
            'sa_gaming' => 'active',
            'super_sport' => 'active',
        ];

        // Act
        // 建立錢包
        $wallets = [];
        foreach ($users as $user) {
            array_push($wallets, $user->buildStationWallets($stations, $status));
        }
        $user1 = $users[0];
        $user2 = $users[1];
        $user3 = $users[2];
        $user4 = $users[3];
        $user5 = $users[4];

        // Assert
        $this->assertEquals($userAmount, count($wallets));
        // 五位使用者
        // user 1
        $userWallets = collect($user1->wallets())->keyBy('station')->toArray();
        $this->assertEquals('freezing', $userWallets['all_bet']['status']);
        $this->assertEquals('freezing', $userWallets['bingo']['status']);
        $this->assertEquals('freezing', $userWallets['maya']['status']);
        $this->assertEquals('active', $userWallets['sa_gaming']['status']);
        $this->assertEquals('active', $userWallets['super_sport']['status']);
        // user 2
        $userWallets = collect($user2->wallets())->keyBy('station')->toArray();
        $this->assertEquals('freezing', $userWallets['all_bet']['status']);
        $this->assertEquals('freezing', $userWallets['bingo']['status']);
        $this->assertEquals('freezing', $userWallets['maya']['status']);
        $this->assertEquals('active', $userWallets['sa_gaming']['status']);
        $this->assertEquals('active', $userWallets['super_sport']['status']);
        // user 3
        $userWallets = collect($user3->wallets())->keyBy('station')->toArray();
        $this->assertEquals('freezing', $userWallets['all_bet']['status']);
        $this->assertEquals('freezing', $userWallets['bingo']['status']);
        $this->assertEquals('freezing', $userWallets['maya']['status']);
        $this->assertEquals('active', $userWallets['sa_gaming']['status']);
        $this->assertEquals('active', $userWallets['super_sport']['status']);
        // user 4
        $userWallets = collect($user4->wallets())->keyBy('station')->toArray();
        $this->assertEquals('freezing', $userWallets['all_bet']['status']);
        $this->assertEquals('freezing', $userWallets['bingo']['status']);
        $this->assertEquals('freezing', $userWallets['maya']['status']);
        $this->assertEquals('active', $userWallets['sa_gaming']['status']);
        $this->assertEquals('active', $userWallets['super_sport']['status']);
        // user 5
        $userWallets = collect($user5->wallets())->keyBy('station')->toArray();
        $this->assertEquals('freezing', $userWallets['all_bet']['status']);
        $this->assertEquals('freezing', $userWallets['bingo']['status']);
        $this->assertEquals('freezing', $userWallets['maya']['status']);
        $this->assertEquals('active', $userWallets['sa_gaming']['status']);
        $this->assertEquals('active', $userWallets['super_sport']['status']);
    }
}