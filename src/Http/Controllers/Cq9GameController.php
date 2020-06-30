<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\DB;
use SuperPlatform\StationWallet\Events\SingleWalletRecordEvent;
use SuperPlatform\StationWallet\Models\StationWallet;

class Cq9GameController
{
    protected static $bet = 'bet';
    protected static $endRound = 'endround';
    protected static $rollout = 'rollout';
    protected static $rollin = 'rollin';
    protected static $debit = 'debit';
    protected static $credit = 'credit';
    protected static $payoff = 'payoff';
    protected static $bonus = 'bonus';
    protected static $refund = 'refund';
    protected static $takeAll = 'takeall';

    /**
     * API 驗證碼
     *
     * @var string
     */
    protected $apiToken = '';

    /**
     * 代理商使用幣別
     *
     * @var string
     */
    protected $currency = '';

    protected $recordActions = [];

    public function __construct()
    {
        $this->apiToken = config('api_caller.cq9_game.config.api_token');
        $this->currency = config('api_caller.cq9_game.config.currency');

        $this->recordActions = [
            self::$bet => 'CQ9 老虎機下注',
            self::$endRound => 'CQ9 老虎機派彩',
            self::$rollout => 'CQ9 牌桌或漁機遊戲開始 轉出錢包',
            self::$rollin => 'CQ9 牌桌或漁機遊戲結束 轉入錢包',
            self::$debit => 'CQ9 訂單扣款',
            self::$credit => 'CQ9 訂單補款',
            self::$payoff => 'CQ9 活動派彩',
            self::$bonus => 'CQ9 遊戲紅利',
            self::$refund => 'CQ9 押注退還',
            self::$takeAll => 'CQ9 漁機遊戲 全部轉出',
        ];
    }

    /**
     * 確認該帳號是否為貴司玩家
     *
     * @param Request $request
     * @param $account
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function checkPlayer(Request $request, $account)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => false,
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 成功回傳訊息
        $responseData = [
            'data' => true,
            'status' => [
                'code' => '0',
                'message' => 'Success',
                'datetime' => now()->toRfc3339String(),
            ],
        ];

        return response()->json($responseData, 200);
    }

    /**
     * 取得錢包餘額
     *
     * @param Request $request
     * @param $account
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function balance(Request $request, $account)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => $statusCode,
                    'message' => array_get($wallet, 'message'),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 成功回傳訊息
        $responseData = [
            'data' => [
                'balance' => array_get($wallet, 'master_wallet.balance'),
                'currency' => $this->currency
            ],
            'status' => [
                'code' => $statusCode,
                'message' => array_get($wallet, 'message'),
                'datetime' => now()->toRfc3339String(),
            ],
        ];

        return response()->json($responseData, 200);
    }

    /**
     * 老虎機下注
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function gameBet(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查參數是否有缺
        $missParams = $this->missingParameters(
            array_keys($request->all()),
            [
                'account',
                'eventTime',
                'gamehall',
                'gamecode',
                'roundid',
                'amount',
                'mtcode'
            ]
        );

        if (!empty($missParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => '缺少參數: ' . implode(',', $missParams),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查時間格式
        $dateTimeParamsArray = array_intersect_key(
            $request->all(),
            [
                'eventTime' => 0,
            ]
        );
        $notRFC3339DataTimeParams = $this->checkRFC3339DateTimeFormat($dateTimeParamsArray);
        if (!empty($notRFC3339DataTimeParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1004',
                    'message' => '不符合時間格式的參數: ' . implode(',', array_keys($notRFC3339DataTimeParams)),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        $account = $request->input('account');
        $amount = floatval($request->input('amount'));
        $serialNo = $request->input('mtcode');
        $roundId = $request->input('roundid');

        // 檢查amount 不得為負數
        if ($amount < 0) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'amount 為負數',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查交易紀錄，如果有重複紀錄就回傳錯誤，避免重複扣款或派彩
        if ($this->isRepeatedSerialNo($serialNo)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '2009',
                    'message' => 'MTCode重複',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => $statusCode,
                    'message' => array_get($wallet, 'message'),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            // 檢查餘額不足
            if ($this->checkBalanceNotEnough($masterWallet->balance, $amount)) {
                $responseData = [
                    'data' => null,
                    'status' => [
                        'code' => '1005',
                        'message' => '餘額不足',
                        'datetime' => now()->toRfc3339String(),
                    ],
                ];

                return response()->json($responseData, 200);
            }

            $masterWallet->balance -= $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => -$amount,
                        'remark' => array_get($this->recordActions, self::$bet),
                        'serial_no' => $serialNo
                    ]
                )
            );

            // 存CQ9 mtcode_round_id table
            DB::table('cq9_mtcode_round_ids')
                ->insert(
                    [
                        'mt_code' => $serialNo,
                        'round_id' => $roundId,
                        'action' => self::$bet,
                    ]
                );

            DB::commit();

            // 成功回傳訊息
            $responseData = [
                'data' => [
                    'balance' => $masterWallet->balance,
                    'currency' => $this->currency
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 結束回合並統整該回合贏分
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function endRound(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查參數是否有缺
        $missParams = $this->missingParameters(
            array_keys($request->all()),
            [
                'account',
                'createTime',
                'gamehall',
                'gamecode',
                'roundid',
                'data'
            ]
        );

        // 注單詳細資料
        $data = json_decode($request->input('data'), true);

        if (!empty($missParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => '缺少參數: ' . implode(',', $missParams),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查時間格式
        $dateTimeParamsArray = array_intersect_key(
            $request->all(),
            [
                'createTime' => 0,
            ]
        );
        $notRFC3339DataTimeParams = $this->checkRFC3339DateTimeFormat($dateTimeParamsArray);
        if (!empty($notRFC3339DataTimeParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1004',
                    'message' => '不符合時間格式的參數: ' . implode(',', array_keys($notRFC3339DataTimeParams)),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查data內的時間格式
        $dataDateTimeParamsArray = collect($data)->pluck('eventtime')->toArray();
        $dataNotRFC3339DataTimeParams = $this->checkRFC3339DateTimeFormat($dataDateTimeParamsArray);
        if (!empty($dataNotRFC3339DataTimeParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1004',
                    'message' => '不符合時間格式的參數: eventtime',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }


        $account = $request->input('account');
        $roundId = $request->input('roundid');

        // 檢查amount 不得為負數
        $amounts = collect($data)
            ->pluck('amount')
            ->filter(
                function ($amount) {
                    return $amount < 0;
                }
            )
            ->toArray();

        if (!empty($amounts)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'amount 為負數',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查交易紀錄，如果有重複紀錄就回傳錯誤，避免重複扣款或派彩
        $serialNos = collect($data)->pluck('mtcode')->toArray();
        if ($this->isRepeatedSerialNo($serialNos)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '2009',
                    'message' => 'MTCode重複',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => $statusCode,
                    'message' => array_get($wallet, 'message'),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            foreach ($data as $item) {
                $amount = array_get($item, 'amount');
                $serialNo = array_get($item, 'mtcode');

                $masterWallet->balance += $amount;
                $masterWallet->save();

                // 寫入錢包紀錄
                event(
                    new SingleWalletRecordEvent(
                        [
                            'wallet_id' => $masterWallet->id,
                            'amount' => $amount,
                            'remark' => array_get($this->recordActions, self::$endRound),
                            'serial_no' => $serialNo
                        ]
                    )
                );

                // 存CQ9 mtcode_round_id table
                DB::table('cq9_mtcode_round_ids')
                    ->insert(
                        [
                            'mt_code' => $serialNo,
                            'round_id' => $roundId,
                            'action' => self::$endRound,
                        ]
                    );
            }

            DB::commit();

            // 成功回傳訊息
            $responseData = [
                'data' => [
                    'balance' => $masterWallet->balance,
                    'currency' => $this->currency
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 牌桌及漁機遊戲，轉出一定額度金額至牌桌或漁機遊戲而調用
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function rollout(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,


                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查參數是否有缺
        $missParams = $this->missingParameters(
            array_keys($request->all()),
            [
                'account',
                'amount',
                'eventTime',
                'gamehall',
                'gamecode',
                'roundid',
                'mtcode'
            ]
        );

        if (!empty($missParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => '缺少參數: ' . implode(',', $missParams),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查時間格式
        $dateTimeParamsArray = array_intersect_key(
            $request->all(),
            [
                'eventTime' => 0,
            ]
        );
        $notRFC3339DataTimeParams = $this->checkRFC3339DateTimeFormat($dateTimeParamsArray);
        if (!empty($notRFC3339DataTimeParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1004',
                    'message' => '不符合時間格式的參數: ' . implode(',', array_keys($notRFC3339DataTimeParams)),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        $account = $request->input('account');
        $amount = floatval($request->input('amount'));
        $serialNo = $request->input('mtcode');
        $roundId = $request->input('roundid');

        // 檢查amount 不得為負數
        if ($amount < 0) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'amount 為負數',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查交易紀錄，如果有重複紀錄就回傳錯誤，避免重複扣款或派彩
        if ($this->isRepeatedSerialNo($serialNo)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '2009',
                    'message' => 'MTCode重複',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => $statusCode,
                    'message' => array_get($wallet, 'message'),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            // 檢查餘額不足
            if ($this->checkBalanceNotEnough($masterWallet->balance, $amount)) {
                $responseData = [
                    'data' => null,
                    'status' => [
                        'code' => '1005',
                        'message' => '餘額不足',
                        'datetime' => now()->toRfc3339String(),
                    ],
                ];

                return response()->json($responseData, 200);
            }

            $masterWallet->balance -= $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => -$amount,
                        'remark' => array_get($this->recordActions, self::$rollout),
                        'serial_no' => $serialNo
                    ]
                )
            );

            // 存CQ9 mtcode_round_id table
            DB::table('cq9_mtcode_round_ids')
                ->insert(
                    [
                        'mt_code' => $serialNo,
                        'round_id' => $roundId,
                        'action' => self::$rollout
                    ]
                );

            DB::commit();

            // 成功回傳訊息
            $responseData = [
                'data' => [
                    'balance' => $masterWallet->balance,
                    'currency' => $this->currency
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 把玩家所有的錢領出，轉入漁機遊戲
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function takeAll(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查參數是否有缺
        $missParams = $this->missingParameters(
            array_keys($request->all()),
            [
                'account',
                'eventTime',
                'gamehall',
                'gamecode',
                'roundid',
                'mtcode'
            ]
        );

        if (!empty($missParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => '缺少參數: ' . implode(',', $missParams),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查時間格式
        $dateTimeParamsArray = array_intersect_key(
            $request->all(),
            [
                'eventTime' => 0,
            ]
        );
        $notRFC3339DataTimeParams = $this->checkRFC3339DateTimeFormat($dateTimeParamsArray);
        if (!empty($notRFC3339DataTimeParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1004',
                    'message' => '不符合時間格式的參數: ' . implode(',', array_keys($notRFC3339DataTimeParams)),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        $account = $request->input('account');
        $serialNo = $request->input('mtcode');
        $roundId = $request->input('roundid');

        // 檢查交易紀錄，如果有重複紀錄就回傳錯誤，避免重複扣款或派彩
        if ($this->isRepeatedSerialNo($serialNo)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '2009',
                    'message' => 'MTCode重複',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => $statusCode,
                    'message' => array_get($wallet, 'message'),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            // 檢查餘額不足
            if ($masterWallet->balance == 0) {
                $responseData = [
                    'data' => null,
                    'status' => [
                        'code' => '1005',
                        'message' => '餘額不足',
                        'datetime' => now()->toRfc3339String(),
                    ],
                ];

                return response()->json($responseData, 200);
            }

            // 要轉出的金額
            $amount = $masterWallet->balance;

            $masterWallet->balance -= $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => -$amount,
                        'remark' => array_get($this->recordActions, self::$takeAll),
                        'serial_no' => $serialNo
                    ]
                )
            );

            // 存CQ9 mtcode_round_id table
            DB::table('cq9_mtcode_round_ids')
                ->insert(
                    [
                        'mt_code' => $serialNo,
                        'round_id' => $roundId,
                        'action' => self::$takeAll,
                    ]
                );

            DB::commit();

            // 成功回傳訊息
            $responseData = [
                'data' => [
                    'amount' => $amount,
                    'balance' => $masterWallet->balance,
                    'currency' => $this->currency
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 牌桌/漁機一場遊戲結束，將金額轉入錢包
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function rollin(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查參數是否有缺
        $missParams = $this->missingParameters(
            array_keys($request->all()),
            [
                'account',
                'eventTime',
                'gamehall',
                'gamecode',
                'roundid',
                'validbet',
                'bet',
                'win',
                'amount',
                'mtcode',
                'createTime',
                'rake',
                'gametype',
            ]
        );

        if (!empty($missParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => '缺少參數: ' . implode(',', $missParams),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查時間格式
        $dateTimeParamsArray = array_intersect_key(
            $request->all(),
            [
                'eventTime' => 0,
                'createTime' => 0,
            ]
        );
        $notRFC3339DataTimeParams = $this->checkRFC3339DateTimeFormat($dateTimeParamsArray);
        if (!empty($notRFC3339DataTimeParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1004',
                    'message' => '不符合時間格式的參數: ' . implode(',', array_keys($notRFC3339DataTimeParams)),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        $account = $request->input('account');
        $amount = floatval($request->input('amount'));
        $serialNo = $request->input('mtcode');
        $roundId = $request->input('roundid');

        // 檢查amount 不得為負數
        if ($amount < 0) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'amount 為負數',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查交易紀錄，如果有重複紀錄就回傳錯誤，避免重複扣款或派彩
        if ($this->isRepeatedSerialNo($serialNo)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '2009',
                    'message' => 'MTCode重複',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => $statusCode,
                    'message' => array_get($wallet, 'message'),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            $masterWallet->balance += $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => $amount,
                        'remark' => array_get($this->recordActions, self::$rollin),
                        'serial_no' => $serialNo
                    ]
                )
            );

            // 存CQ9 mtcode_round_id table
            DB::table('cq9_mtcode_round_ids')
                ->insert(
                    [
                        'mt_code' => $serialNo,
                        'round_id' => $roundId,
                        'action' => self::$rollin,
                    ]
                );

            DB::commit();

            // 成功回傳訊息
            $responseData = [
                'data' => [
                    'balance' => $masterWallet->balance,
                    'currency' => $this->currency
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 完成的訂單做扣款
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function debit(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查參數是否有缺
        $missParams = $this->missingParameters(
            array_keys($request->all()),
            [
                'account',
                'eventTime',
                'gamehall',
                'gamecode',
                'roundid',
                'amount',
                'mtcode'
            ]
        );

        if (!empty($missParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => '缺少參數: ' . implode(',', $missParams),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查時間格式
        $dateTimeParamsArray = array_intersect_key(
            $request->all(),
            [
                'eventTime' => 0,
            ]
        );
        $notRFC3339DataTimeParams = $this->checkRFC3339DateTimeFormat($dateTimeParamsArray);
        if (!empty($notRFC3339DataTimeParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1004',
                    'message' => '不符合時間格式的參數: ' . implode(',', array_keys($notRFC3339DataTimeParams)),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        $account = $request->input('account');
        $amount = floatval($request->input('amount'));
        $serialNo = $request->input('mtcode');
        $roundId = $request->input('roundid');

        // 檢查amount 不得為負數
        if ($amount < 0) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'amount 為負數',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查交易紀錄，如果有重複紀錄就回傳錯誤，避免重複扣款或派彩
        if ($this->isRepeatedSerialNo($serialNo)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '2009',
                    'message' => 'MTCode重複',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => $statusCode,
                    'message' => array_get($wallet, 'message'),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            // 檢查餘額不足
            if ($this->checkBalanceNotEnough($masterWallet->balance, $amount)) {
                $responseData = [
                    'data' => null,
                    'status' => [
                        'code' => '1005',
                        'message' => '餘額不足',
                        'datetime' => now()->toRfc3339String(),
                    ],
                ];

                return response()->json($responseData, 200);
            }

            $masterWallet->balance -= $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => -$amount,
                        'remark' => array_get($this->recordActions, self::$debit),
                        'serial_no' => $serialNo
                    ]
                )
            );

            // 存CQ9 mtcode_round_id table
            DB::table('cq9_mtcode_round_ids')
                ->insert(
                    [
                        'mt_code' => $serialNo,
                        'round_id' => $roundId,
                        'action' => self::$debit,
                    ]
                );

            DB::commit();

            // 成功回傳訊息
            $responseData = [
                'data' => [
                    'balance' => $masterWallet->balance,
                    'currency' => $this->currency
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 完成的訂單做補款
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function credit(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查參數是否有缺
        $missParams = $this->missingParameters(
            array_keys($request->all()),
            [
                'account',
                'eventTime',
                'gamehall',
                'gamecode',
                'roundid',
                'amount',
                'mtcode'
            ]
        );

        if (!empty($missParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => '缺少參數: ' . implode(',', $missParams),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查時間格式
        $dateTimeParamsArray = array_intersect_key(
            $request->all(),
            [
                'eventTime' => 0,
            ]
        );
        $notRFC3339DataTimeParams = $this->checkRFC3339DateTimeFormat($dateTimeParamsArray);
        if (!empty($notRFC3339DataTimeParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1004',
                    'message' => '不符合時間格式的參數: ' . implode(',', array_keys($notRFC3339DataTimeParams)),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        $account = $request->input('account');
        $amount = floatval($request->input('amount'));
        $serialNo = $request->input('mtcode');
        $roundId = $request->input('roundid');

        // 檢查amount 不得為負數
        if ($amount < 0) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'amount 為負數',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查交易紀錄，如果有重複紀錄就回傳錯誤，避免重複扣款或派彩
        if ($this->isRepeatedSerialNo($serialNo)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '2009',
                    'message' => 'MTCode重複',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => $statusCode,
                    'message' => array_get($wallet, 'message'),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            $masterWallet->balance += $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => $amount,
                        'remark' => array_get($this->recordActions, self::$credit),
                        'serial_no' => $serialNo
                    ]
                )
            );

            // 存CQ9 mtcode_round_id table
            DB::table('cq9_mtcode_round_ids')
                ->insert(
                    [
                        'mt_code' => $serialNo,
                        'round_id' => $roundId,
                        'action' => self::$credit,
                    ]
                );

            DB::commit();

            // 成功回傳訊息
            $responseData = [
                'data' => [
                    'balance' => $masterWallet->balance,
                    'currency' => $this->currency
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 遊戲紅利
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function bonus(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查參數是否有缺
        $missParams = $this->missingParameters(
            array_keys($request->all()),
            [
                'account',
                'eventTime',
                'gamehall',
                'gamecode',
                'roundid',
                'amount',
                'mtcode'
            ]
        );

        if (!empty($missParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => '缺少參數: ' . implode(',', $missParams),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查時間格式
        $dateTimeParamsArray = array_intersect_key(
            $request->all(),
            [
                'eventTime' => 0,
            ]
        );
        $notRFC3339DataTimeParams = $this->checkRFC3339DateTimeFormat($dateTimeParamsArray);
        if (!empty($notRFC3339DataTimeParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1004',
                    'message' => '不符合時間格式的參數: ' . implode(',', array_keys($notRFC3339DataTimeParams)),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        $account = $request->input('account');
        $amount = floatval($request->input('amount'));
        $serialNo = $request->input('mtcode');
        $roundId = $request->input('roundid');

        // 檢查amount 不得為負數
        if ($amount < 0) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'amount 為負數',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查交易紀錄，如果有重複紀錄就回傳錯誤，避免重複扣款或派彩
        if ($this->isRepeatedSerialNo($serialNo)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '2009',
                    'message' => 'MTCode重複',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => $statusCode,
                    'message' => array_get($wallet, 'message'),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            $masterWallet->balance += $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => $amount,
                        'remark' => array_get($this->recordActions, self::$bonus),
                        'serial_no' => $serialNo
                    ]
                )
            );

            // 存CQ9 mtcode_round_id table
            DB::table('cq9_mtcode_round_ids')
                ->insert(
                    [
                        'mt_code' => $serialNo,
                        'round_id' => $roundId,
                        'action' => self::$bonus
                    ]
                );

            DB::commit();

            // 成功回傳訊息
            $responseData = [
                'data' => [
                    'balance' => $masterWallet->balance,
                    'currency' => $this->currency
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 活動派彩
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function payoff(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查參數是否有缺
        $missParams = $this->missingParameters(
            array_keys($request->all()),
            [
                'account',
                'eventTime',
                'amount',
                'mtcode'
            ]
        );

        if (!empty($missParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => '缺少參數: ' . implode(',', $missParams),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查時間格式
        $dateTimeParamsArray = array_intersect_key(
            $request->all(),
            [
                'eventTime' => 0,
            ]
        );
        $notRFC3339DataTimeParams = $this->checkRFC3339DateTimeFormat($dateTimeParamsArray);
        if (!empty($notRFC3339DataTimeParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1004',
                    'message' => '不符合時間格式的參數: ' . implode(',', array_keys($notRFC3339DataTimeParams)),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        $account = $request->input('account');
        $amount = floatval($request->input('amount'));
        $serialNo = $request->input('mtcode');

        // 檢查amount 不得為負數
        if ($amount < 0) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'amount 為負數',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查交易紀錄，如果有重複紀錄就回傳錯誤，避免重複扣款或派彩
        if ($this->isRepeatedSerialNo($serialNo)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '2009',
                    'message' => 'MTCode重複',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 找玩家主錢包
        $wallet = $this->findMasterWallet($account);

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => $statusCode,
                    'message' => array_get($wallet, 'message'),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            $masterWallet->balance += $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => $amount,
                        'remark' => array_get($this->recordActions, self::$payoff),
                        'serial_no' => $serialNo
                    ]
                )
            );

            // 存CQ9 mtcode_round_id table
            DB::table('cq9_mtcode_round_ids')
                ->insert(
                    [
                        'mt_code' => $serialNo,
                        'round_id' => '',
                        'action' => self::$payoff
                    ]
                );

            DB::commit();

            // 成功回傳訊息
            $responseData = [
                'data' => [
                    'balance' => $masterWallet->balance,
                    'currency' => $this->currency
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 押注退還
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function refund(Request $request)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 檢查參數是否有缺
        $missParams = $this->missingParameters(
            array_keys($request->all()),
            [
                'mtcode'
            ]
        );

        if (!empty($missParams)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => '缺少參數: ' . implode(',', $missParams),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        $serialNo = $request->input('mtcode');

        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN', 'reference_id', 'from_change_balance', 'remark', 'wallet_account')
            ->where('SN', $serialNo)
            ->get();

        // 沒有交易紀錄 直接回傳錯誤
        if ($record->isEmpty()) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1014',
                    'message' => '未查詢到交易紀錄',
                    'datetime' => now()->toRfc3339String(),
                ]
            ];

            return response()->json($responseData, 200);
        }

        // 已經有押注退還紀錄 回傳錯誤
        $recordMoreThanOne = $record->count() >= 2;
        $containRefundRecord = $record->contains(
            function ($item) {
                $remark = data_get($item, 'remark');
                return array_search($remark, $this->recordActions) === self::$refund;
            }
        );
        if ($recordMoreThanOne && $containRefundRecord) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1015',
                    'message' => "{$serialNo}已經被refund",
                    'datetime' => now()->toRfc3339String(),
                ]
            ];

            return response()->json($responseData, 200);
        }

        $beforeRecord = $record->first();

        // 找玩家主錢包
        $wallet = $this->findMasterWallet(data_get($beforeRecord, 'wallet_account'));

        // 如果status code 狀態不是 '0'就回傳錯誤訊息
        $statusCode = array_get($wallet, 'status_code');
        if ($statusCode !== '0') {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => $statusCode,
                    'message' => array_get($wallet, 'message'),
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        try {
            DB::beginTransaction();

            $masterWallet = StationWallet::lockForUpdate()
                ->find(array_get($wallet, 'master_wallet.id'));

            $amount = abs(data_get($beforeRecord, 'from_change_balance'));

            $masterWallet->balance += $amount;
            $masterWallet->save();

            // 寫入錢包紀錄
            event(
                new SingleWalletRecordEvent(
                    [
                        'wallet_id' => $masterWallet->id,
                        'amount' => $amount,
                        'remark' => array_get($this->recordActions, self::$refund),
                        'serial_no' => $serialNo
                    ]
                )
            );

            DB::commit();

            // 成功回傳訊息
            $responseData = [
                'data' => [
                    'balance' => $masterWallet->balance,
                    'currency' => $this->currency
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 查詢交易紀錄
     *
     * @param Request $request
     * @param string $serialNo
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function record(Request $request, string $serialNo)
    {
        // 檢查回戳API TOKEN憑證
        if ($this->isWrongApiToken($request->header('wtoken'))) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1003',
                    'message' => 'wtoken錯誤',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 先確認是否有同局的交易紀錄
        $mtcode = DB::table('cq9_mtcode_round_ids')
            ->select('mt_code', 'round_id', 'action')
            ->where('mt_code', $serialNo)
            ->first();

        // 沒有交易紀錄 直接回傳 null
        if (empty($mtcode)) {
            $responseData = [
                'data' => null,
                'status' => [
                    'code' => '1014',
                    'message' => 'record not found',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        if (empty(data_get($mtcode, 'round_id'))) {
            // 尋找單一交易紀錄
            $records = DB::table('station_wallet_trade_records')
                ->select(
                    'id',
                    'trade_status',
                    'from_before_balance',
                    'from_after_balance',
                    'to_before_balance',
                    'to_after_balance',
                    'updated_at',
                    'wallet_account',
                    'trade_amount',
                    'remark',
                    'SN'
                )
                ->where('SN', $serialNo)
                ->get();
        } else {
            // 尋找同局的交易紀錄
            $roundIds = DB::table('cq9_mtcode_round_ids')
                ->where('round_id', data_get($mtcode, 'round_id'))
                ->where('action', data_get($mtcode, 'action'));

            $records = DB::table('station_wallet_trade_records')
                ->joinSub(
                    $roundIds,
                    'cq9_mtcode_round_ids',
                    function ($join) {
                        $join->on('station_wallet_trade_records.SN', '=', 'cq9_mtcode_round_ids.mt_code');
                    }
                )
                ->select(
                    'id',
                    'trade_status',
                    'from_before_balance',
                    'from_after_balance',
                    'to_before_balance',
                    'to_after_balance',
                    'station_wallet_trade_records.updated_at',
                    'wallet_account',
                    'trade_amount',
                    'remark',
                    'SN'
                )
                ->get();
        }

        // 檢查如果是bet跟refund同時存在就只需要回傳refund
        $hasBetAndRefundRecord = ($records->count() === 2) && empty(
            array_diff(
                $records->pluck('remark')->toArray(),
                [
                    array_get($this->recordActions, self::$refund),
                    array_get($this->recordActions, self::$bet)
                ]
            )
        );
        if ($hasBetAndRefundRecord) {
            $records = $records->filter(function ($record) {
                return data_get($record, 'remark') === array_get($this->recordActions, self::$refund);
            });
        }

        // 檢查狀態
        $allSuccessStatus = $records->every(
            function ($record) {
                return data_get($record, 'trade_status') === 'success';
            }
        );

        // 組回傳參數
        $id = data_get($records->first(), 'id');
        $action = data_get($records->first(), 'remark');
        $responseAction = array_search($action, $this->recordActions);
        $account = data_get($records->first(), 'wallet_account');
        $updateAt = data_get($records->first(), 'update_at');

        $events = [];
        foreach ($records as $record) {
            array_push($events, [
                'mtcode' => data_get($record, 'SN'),
                'amount' => floatval(data_get($record, 'trade_amount')),
                'eventtime' => Carbon::parse(data_get($record, 'updated_at'))->toRfc3339String()
            ]);
        }

        $balanceAction = (data_get($records->first(), 'from_before_balance')) ? 'subtract' : 'add';
        $before = ($balanceAction === 'add') ? $records->min('to_before_balance') : $records->max(
            'from_before_balance'
        );
        $after = ($balanceAction === 'add') ? $records->max('to_after_balance') : $records->min('from_after_balance');

        $status = 'success';

        if ($hasBetAndRefundRecord) {
            $responseAction = 'bet';
            $status = 'refund';
        }

        // 交易紀錄狀態失敗，回傳失敗訊息
        if (!$allSuccessStatus) {
            $responseData = [
                'data' => [
                    '_id' => $id,
                    'action' => $responseAction,
                    'target' => [
                        'account' => $account
                    ],
                    'status' => [
                        'createtime' => Carbon::parse($updateAt)->toRfc3339String(),
                        'endtime' => Carbon::parse($updateAt)->toRfc3339String(),
                        'status' => 'failed',
                        'message' => '交易失敗'
                    ],
                    'before' => 0,
                    'balance' => 0,
                    'currency' => $this->currency,
                    'event' => $events
                ],
                'status' => [
                    'code' => '0',
                    'message' => 'Success',
                    'datetime' => now()->toRfc3339String(),
                ],
            ];

            return response()->json($responseData, 200);
        }

        // 交易紀錄狀態成功，回傳成功訊息
        $responseData = [
            'data' => [
                '_id' => $id,
                'action' => $responseAction,
                'target' => [
                    'account' => $account
                ],
                'status' => [
                    'createtime' => Carbon::parse($updateAt)->toRfc3339String(),
                    'endtime' => Carbon::parse($updateAt)->toRfc3339String(),
                    'status' => $status,
                    'message' => 'success'
                ],
                'before' => floatval($before),
                'balance' => floatval($after),
                'currency' => $this->currency,
                'event' => $events
            ],
            'status' => [
                'code' => '0',
                'message' => 'Success',
                'datetime' => now()->toRfc3339String(),
            ],
        ];

        return response()->json($responseData, 200);
    }

    /**
     * 檢查回戳API TOKEN憑證
     *
     * @param $token
     * @return bool
     * @throws \Exception
     */
    private function isWrongApiToken($token)
    {
        if ($token !== $this->apiToken) {
            return true;
        }

        return false;
    }

    /**
     * 檢查交易紀錄，如果有重複紀錄就回傳錯誤，避免重複扣款或派彩
     *
     * @param string|array $serialNo
     * @return bool
     */
    private function isRepeatedSerialNo($serialNo)
    {
        // 尋找交易紀錄
        $record = DB::table('station_wallet_trade_records')
            ->select('SN')
            ->when(
                is_array($serialNo),
                function ($query) use ($serialNo) {
                    return $query->whereIn('SN', $serialNo);
                },
                function ($query) use ($serialNo) {
                    return $query->where('SN', $serialNo);
                }
            )
            ->first();

        if (!empty($record)) {
            return true;
        }

        return false;
    }

    /**
     * 檢查餘額不足
     *
     * @param $balance
     * @param $amount
     * @return bool
     */
    private function checkBalanceNotEnough($balance, $amount)
    {
        return $balance < abs($amount);
    }

    /**
     * 檢查參數是否有缺
     *
     * @param array $params
     * @param array $matchParams
     * @return array
     */
    private function missingParameters(array $params, array $matchParams): array
    {
        return array_diff($matchParams, $params);
    }

    /**
     * 檢查時間格式
     *
     * @param array $dateTimes
     * @return array
     */
    private function checkRFC3339DateTimeFormat(array $dateTimes): array
    {
        return collect($dateTimes)->filter(
            function ($dateTime) {
                return !preg_match(
                    '/^([0-9]+)-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])[Tt]([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9]|60)(\.[0-9]+)?(([Zz])|([\+|\-]([01][0-9]|2[0-3]):[0-5][0-9]))$/',
                    $dateTime
                );
            }
        )->toArray();
    }

    /**
     * 找玩家主錢包
     *
     * @param string $account
     * @return array
     */
    private function findMasterWallet(string $account = ''): array
    {
        $masterWallet = StationWallet::select('id', 'account', 'station', 'status', 'activated_status', 'balance')
            ->where('account', $account)
            ->where('station', 'master')
            ->first();

        if (empty($masterWallet)) {
            return [
                'master_wallet' => null,
                'status_code' => '1006',
                'message' => '查無玩家',
            ];
        }

        if (data_get($masterWallet, 'activated_status') !== 'yes') {
            return [
                'master_wallet' => null,
                'status_code' => '1006',
                'message' => '玩家帳號已被鎖',
            ];
        }

        if (data_get($masterWallet, 'status') === 'freezing') {
            return [
                'master_wallet' => null,
                'status_code' => '1006',
                'message' => '玩家帳號已被鎖',
            ];
        }

        return [
            'master_wallet' => optional($masterWallet)->toArray(),
            'status_code' => '0',
            'message' => 'Success',
        ];
    }
}