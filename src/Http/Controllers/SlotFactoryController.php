<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SuperPlatform\StationWallet\Events\SingleWalletRecordEvent;
use SuperPlatform\StationWallet\Models\StationWallet;

class SlotFactoryController
{
    /**
     * 加密公鑰
     */
    protected $secretKey = '';

    /**
     * Response Data
     * @var array
     */
    public $responseData = [];

    /**
     * Response Header
     * @var array
     */
    public $header = [];

    /**
     * 會員SF電子錢包
     * @var
     */
    public $memberSlotFactoryWallet;

    protected $sfMultiWalletSites = [
        'al', //TGM
        'aw', // galaxy casino
        'ax', // v88
        'ab', // stage
        'aa', // develop
        'ag', // UPG
    ];

    public function __construct()
    {
        $this->secretKey = config('api_caller.slot_factory.config.secret_key');
    }

    public function action(Request $request)
    {
        $action = $request->input('Action');

        if (!method_exists($this, $action)) {
            throw new \Exception('method not existed');
        }

        $this->setMemberSlotFactorWallet($request->input('AccountID'));
        if (!$this->isWalletAvailable()) {
            throw new \Exception('invalid SF wallet');
        }

        $action = camel_case($action);
        $this->$action($request->all());

        $response = response()
            ->json($this->responseData, 200)
            ->withHeaders($this->header);

        return $response;
    }

    public function setMemberSlotFactorWallet(string $account = '')
    {
        $walletName = 'master';

        if (in_array(config('app.app_id'), $this->sfMultiWalletSites)) {
            $walletName = 'slot_factory';
        }

        $this->memberSlotFactoryWallet = StationWallet::select('id', 'account', 'station', 'status', 'activated_status', 'balance')
            ->where('account', $account)
            ->where('station', $walletName)
            ->first();
    }

    /**
     * 檢查會員SF電子錢包狀態是開通且有效
     *
     * @param string $account
     * @return bool
     */
    public function isWalletAvailable(): bool
    {
        $isStatusActive = (data_get($this->memberSlotFactoryWallet, 'status') == 'active');
        $isInActivatedStatus = (data_get($this->memberSlotFactoryWallet, 'activated_status') == 'yes');

        return $isStatusActive && $isInActivatedStatus;
    }

    /**
     * Login Request
     *
     * @param array $params
     */
    public function login(array $params = [])
    {
        $this->responseData = [
            'StatusCode' => 0,
            'StatusDescription' => 'success',
            'SessionID' => array_get($params, 'SessionID'),
            'AccountID' => array_get($params, 'AccountID'),
            'PlayerIP' => array_get($params, 'PlayerIP'),
            'Timestamp' => now()->timestamp,
            'FirstName' => array_get($params, 'AccountID'),
            'LastName' => array_get($params, 'AccountID'),
            'DateOfBirth' => '1990-01-01',
            'CountryID' => config('api_caller.slot_factory.config.country'),
            'CurrencyID' => config('api_caller.slot_factory.config.currency'),
            'Balance' => strval($this->memberSlotFactoryWallet->balance * 100),
            'AuthToken' => array_get($params, 'AuthToken')
        ];

        $data = json_encode($this->responseData);

        $this->header = [
            'HMAC' => $this->hashHmac($data),
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($data)
        ];
    }

    /**
     * Play Request
     *
     * @param array $params
     */
    public function play($params = [])
    {
        try {
            DB::beginTransaction();

            $wallet = StationWallet::lockForUpdate()
                ->find($this->memberSlotFactoryWallet->id);

            $betAmount = array_get($params, 'BetAmount') / 100;
            $winAmount = array_get($params, 'WinAmount') / 100;
            $winLoseAmount = $winAmount - $betAmount;

            $wallet->balance += $winLoseAmount;
            $wallet->save();

            $this->responseData = [
                'StatusCode' => 0,
                'StatusDescription' => 'success',
                'SessionID' => array_get($params, 'SessionID'),
                'AccountID' => array_get($params, 'AccountID'),
                'PlayerIP' => array_get($params, 'PlayerIP'),
                'Timestamp' => now()->timestamp,
                'RoundID' => array_get($params, 'RoundID'),
                'Balance' => strval($wallet->balance * 100),
            ];

            $data = json_encode($this->responseData);

            $this->header = [
                'HMAC' => $this->hashHmac($data),
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($data)
            ];

            // 寫入錢包紀錄
            event(new SingleWalletRecordEvent(
                [
                    'wallet_id' => $wallet->id,
                    'amount' => $winLoseAmount,
                    'remark' => 'SF電子 SPIN'
                ]
            ));

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * Reward Bonus Request
     *
     * @param array $params
     */
    public function rewardBonus($params = [])
    {
        try {
            DB::beginTransaction();

            $wallet = StationWallet::lockForUpdate()
                ->find($this->memberSlotFactoryWallet->id);

            $winLoseAmount = array_get($params, 'WinAmount') / 100;

            $wallet->balance += $winLoseAmount;
            $wallet->save();

            $this->responseData = [
                'StatusCode' => 0,
                'StatusDescription' => 'success',
                'SessionID' => array_get($params, 'SessionID'),
                'AccountID' => array_get($params, 'AccountID'),
                'PlayerIP' => array_get($params, 'PlayerIP'),
                'Timestamp' => now()->timestamp,
                'RoundID' => array_get($params, 'RoundID'),
                'Balance' => strval($wallet->balance * 100),
            ];

            $data = json_encode($this->responseData);

            $this->header = [
                'HMAC' => $this->hashHmac($data),
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($data)
            ];

            // 寫入錢包紀錄
            event(new SingleWalletRecordEvent(
                [
                    'wallet_id' => $wallet->id,
                    'amount' => $winLoseAmount,
                    'remark' => 'SF電子 免費遊戲 SPIN'
                ]
            ));

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * Get Balance Request
     *
     * @param array $params
     */
    public function getBalance($params = [])
    {
        $this->responseData = [
            'StatusCode' => 0,
            'StatusDescription' => 'success',
            'SessionID' => array_get($params, 'SessionID'),
            'AccountID' => array_get($params, 'AccountID'),
            'PlayerIP' => array_get($params, 'PlayerIP'),
            'Timestamp' => now()->timestamp,
            'Balance' => strval($this->memberSlotFactoryWallet->balance * 100),
        ];

        $data = json_encode($this->responseData);

        $this->header = [
            'HMAC' => $this->hashHmac($data),
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($data)
        ];
    }

    /**
     * hash Hmac 加密
     *
     * @param string $data
     * @return string
     */
    private function hashHmac(string $data): string
    {
        return base64_encode(hash_hmac('sha256', $data, $this->secretKey, true));
    }
}