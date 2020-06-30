<?php


namespace SuperPlatform\StationWallet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SuperPlatform\StationWallet\Events\SingleWalletExceptionEvent;
use SuperPlatform\StationWallet\Events\SingleWalletRecordEvent;
use SuperPlatform\StationWallet\Events\RefreshMemberBalanceEvent;
use SuperPlatform\StationWallet\Events\TicketConvertEvent;
use SuperPlatform\StationWallet\Models\StationWallet;
use Illuminate\Support\Facades\Log;

class IncorrectScoreController
{
    /**
     * 加密公鑰
     */
    protected $signature = '';

    /**
     * 派彩入款說明
     */
    public $singleWalletRemark = '';

    /**
     * 會員反波膽錢包
     * @var
     */
    public $memberIncorrectScoreWallet;

    public function __construct()
    {
        // 單一錢包密鑰
        $this->signature = config('station_wallet.stations.incorrect_score.build.singleWalletKey');
        $this->singleWalletRemark = config('station_wallet.stations.incorrect_score.singleWallet');
    }

    public function setMemberIncorrectScoreWallet(string $account = '')
    {
        $this->memberIncorrectScoreWallet = StationWallet::select(
            'id',
            'user_id',
            'account',
            'station',
            'status',
            'activated_status',
            'balance'
        )
            ->where('account', $account)
            ->where('station', 'master')
            ->first();

        return $this;
    }

    /**
     * 檢查會員反波膽錢包狀態是開通且有效或凍結
     *
     * @param string $account
     * @return bool
     */
    public function isWalletAvailable(): bool
    {
        $isStatusActive = (data_get($this->memberIncorrectScoreWallet, 'status') == 'active');
        $isStatusFreezing = (data_get($this->memberIncorrectScoreWallet, 'status') == 'freezing');
        $isInActivatedStatus = (data_get($this->memberIncorrectScoreWallet, 'activated_status') == 'yes');

        return $isStatusActive && $isInActivatedStatus || $isStatusFreezing;
    }

    /**
     * 取得单一钱包余额
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMemberBalance(Request $request)
    {
        $params = $request->all();

        // 檢查密鑰 (密鑰不正確則直接回傳錯誤)
        if ($this->signature !== array_get($params, 'signature')) {
            // 發送錯誤訊息事件
            event(
                new SingleWalletExceptionEvent(
                    [
                        'station' => 'incorrect_score',
                        'ticket_id' => array_get(array_first($tickets), 'ticketNo'),
                        'error_message' => array_get($params, 'signature') . ' Invalid Argument for signature'
                    ]
                )
            );

            return response()->json(
                [
                    "ok" => false,
                    "data" => [
                        "errorCode" => 10003,
                        "errorMessage" => 'Member {' . array_get($params, 'signature') . '} not found.'
                    ]
                ],
                200
            );
        }

        // 檢查會員主錢包是否存在
        $this->setMemberIncorrectScoreWallet(array_get($params, 'memberId'));

        if (!$this->isWalletAvailable()) {
            return response()->json(
                [
                    "ok" => false,
                    "data" => [
                        "errorCode" => 10003,
                        "errorMessage" => 'Member {' . array_get($params, 'memberId') . '} not found.'
                    ]
                ],
                200
            );
        }

        $response = response()->json(
            [
                "ok" => true,
                "data" => [
                    "balance" => $this->memberIncorrectScoreWallet->balance,
                ]
            ],
            200
        );
        return $response;
    }

    /**
     * 建立注单/玩家扣款
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function createBetLog(Request $request)
    {
        $params = $request->all();
        // 取得注單ID
        $ticketId = array_get($params, 'betLogId');
        // 檢查密鑰 (密鑰不正確則直接回傳錯誤)
        if ($this->signature !== array_get($params, 'signature')) {
            return response()->json(
                [
                    "ok" => false,
                    "data" => [
                        "errorCode" => 10007,
                        "errorMessage" => '{' . array_get($params, 'signature') . '} Invalid Argument'
                    ]
                ],
                200
            );
        }
        // 檢查會員主錢包是否存在
        $this->setMemberIncorrectScoreWallet(array_get($params, 'memberId'));

        // 判斷主錢包是否為「凍結」狀態
        $freezing = (data_get($this->memberIncorrectScoreWallet, 'status') == 'freezing');

        // 若以上成真則直接拋出例外並return false
        if ($freezing) {
            // 發送錯誤訊息事件
            event(
                new SingleWalletExceptionEvent(
                    [
                        'station' => 'incorrect_score',
                        'ticket_id' => $ticketId,
                        'error_message' => '会员停押中'
                    ]
                )
            );

            return response()->json(
                [
                    "ok" => false,
                    "data" => [
                        "errorCode" => 10009,
                        "errorMessage" => '会员停押中'
                    ]
                ],
                200
            );
        }

        if (!$this->isWalletAvailable()) {
            // 發送錯誤訊息事件
            event(
                new SingleWalletExceptionEvent(
                    [
                        'station' => 'incorrect_score',
                        'ticket_id' => $ticketId,
                        'error_message' => '{' . array_get($params, 'memberId') . '} Invalid Argument'
                    ]
                )
            );

            return response()->json(
                [
                    "ok" => false,
                    "data" => [
                        "errorCode" => 10007,
                        "errorMessage" => '{' . array_get($params, 'memberId') . '} Invalid Argument'
                    ]
                ],
                200
            );
        }

        // 檢查餘額是否 > 投注金額
        if (!$this->isSufficientBalance($params)) {
            // 發送錯誤訊息事件
            event(
                new SingleWalletExceptionEvent(
                    [
                        'station' => 'incorrect_score',
                        'ticket_id' => $ticketId,
                        'error_message' => 'Insufficient Balance'
                    ]
                )
            );

            return response()->json(
                [
                    "ok" => false,
                    "data" => [
                        "errorCode" => 10008,
                        "errorMessage" => 'Insufficient Balance'
                    ]
                ],
                200
            );
        }


        try {
            DB::beginTransaction();
            $wallet = StationWallet::lockForUpdate()
                ->find($this->memberIncorrectScoreWallet->id);

            $betAmount = array_get($params, 'betAmount');
            $winAmount = array_get($params, 'winLossAmount');
            $winLoseAmount = $winAmount - $betAmount;

            $wallet->balance += $winLoseAmount;
            $wallet->save();

            // 寫入錢包紀錄
            event(new SingleWalletRecordEvent(
                [
                    'wallet_id' => $wallet->id,
                    'amount' => $winLoseAmount,
                    'remark' => '反波胆下注' . $betAmount,
                    'serial_no' => '',
                    'ticket_id' => $ticketId,
                    'dtype' => ''
                ]
            ));

            // 發送推播事件讓會員端同步錢包
            event(
                new RefreshMemberBalanceEvent(
                    [
                        'user_id' => $wallet->user_id,
                        'balance' => $winLoseAmount,
                    ]
                )
            );

            DB::commit();

            $response = response()->json(
                [
                    "ok" => true,
                ],
                200
            );
            return $response;
        } catch (\Exception $exception) {
            DB::rollBack();

            // 發送錯誤訊息事件
            event(
                new SingleWalletExceptionEvent(
                    [
                        'station' => 'incorrect_score',
                        'ticket_id' => $ticketId,
                        'error_message' => $exception->getMessage(),
                    ]
                )
            );

            throw $exception;
        }
    }

    /**
     * 派彩入款
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */

    public function deposit(Request $request)
    {
        $params = $request->all();
        $serialNo = array_get($params, 'notifyId');
        $tickets = array_get($params, 'Ticket') ?? [];

        // 檢查密鑰 (密鑰不正確則直接回傳錯誤)
        if ($this->signature !== array_get($params, 'signature')) {

            // 發送錯誤訊息事件
            event(
                new SingleWalletExceptionEvent(
                    [
                        'station' => 'incorrect_score',
                        'ticket_id' => array_get(array_first($tickets), 'ticketNo'),
                        'error_message' => array_get($params, 'signature') . ' Invalid Argument for signature'
                    ]
                )
            );

            return response()->json(
                [
                    "ok" => false,
                    "data" => [
                        "errorCode" => 10007,
                        "errorMessage" => '{' . array_get($params, 'signature') . '} Invalid Argument'
                    ]
                ],
                200
            );
        }

        // 尋找交易紀錄
        $records = DB::table('station_wallet_trade_records')
            ->select('SN')
            ->where('SN', $serialNo)
            ->get()
            ->toArray();

        if (!empty($records)) {

            // 發送錯誤訊息事件
            event(
                new SingleWalletExceptionEvent(
                    [
                        'station' => 'incorrect_score',
                        'ticket_id' => array_get(array_first($tickets), 'ticketNo'),
                        'error_message' =>  '#' . $serialNo . ' notifyId repeated'
                    ]
                )
            );

            return response()->json(
                [
                    "ok" => false,
                    "data" => [
                        "errorCode" => 10007,
                        "errorMessage" => 'notifyId repeated'
                    ]
                ],
                200
            );
        }

        // 取得注單資訊
        $cashes = array_get($params, 'cashes');
        if (!is_null($cashes)) {
            try {
                // 交易模式必須放在foreach之前，否則其中一個會員失敗就會掉點
                DB::beginTransaction();
                foreach ($cashes as $cash) {
                    $ticketId = array_get($cash, 'ticketNo');
                    // 檢查會員主錢包是否存在
                    $this->setMemberIncorrectScoreWallet(array_get($cash, 'memberId'));
                    if (!$this->isWalletAvailable()) {
                        return response()->json(
                            [
                                "ok" => false,
                                "data" => [
                                    "errorCode" => 10007,
                                    "errorMessage" => '{' . array_get($cash, 'memberId') . '} Invalid Argument'
                                ]
                            ],
                            200
                        );
                    }
                    // 檢查餘額是否 > 投注金額 避免重新派彩沒有錢可以扣
                    if (!$this->isSufficientBalance($params)) {
                        return response()->json(
                            [
                                "ok" => false,
                                "data" => [
                                    "errorCode" => 10008,
                                    "errorMessage" => 'Insufficient Balance'
                                ]
                            ],
                            200
                        );
                    }
                    $wallet = StationWallet::lockForUpdate()
                        ->find($this->memberIncorrectScoreWallet->id);

                    // 入款金額
                    $amount = array_get($cash, 'amount');

                    $wallet->balance += $amount;
                    $wallet->save();

                    // 派彩方式
                    $dType = array_get($cash, 'dtype');
                    if (empty($dType)) {
                        Log::channel('member-wallet-api')->info(json_encode($params, 64 | 128 | 256));
                    }
                    $amountType = array_get($this->singleWalletRemark, $dType);

                    // 寫入錢包紀錄
                    event(new SingleWalletRecordEvent(
                        [
                            'wallet_id' => $wallet->id,
                            'amount' => $amount,
                            'remark' => '反波胆' . $amountType . $amount,
                            'serial_no' => $serialNo,
                            'ticket_id' => $ticketId,
                            'dtype' => $dType
                        ]
                    ));

                    // 注單轉換事件
                    event(
                        new TicketConvertEvent(
                            [
                                'station' => 'incorrect_score',
                                'tickets' => $tickets
                            ]
                        )
                    );

                    // 發送推播事件讓會員端同步錢包
                    event(
                        new RefreshMemberBalanceEvent(
                            [
                                'user_id' => $wallet->user_id,
                                'balance' => $amount,
                            ]
                        )
                    );
                }
                DB::commit();
                $response = response()->json(
                    [
                        "ok" => true,
                    ],
                    200
                );
                return $response;
            } catch (\Exception $exception) {
                DB::rollBack();

                // 發送錯誤訊息事件
                event(
                    new SingleWalletExceptionEvent(
                        [
                            'station' => 'incorrect_score',
                            'ticket_id' => array_get(array_first($tickets), 'ticketNo'),
                            'error_message' =>  $exception->getMessage(),
                        ]
                    )
                );

                throw $exception;
            }
        }
    }

    /**
     * @param array $params
     * @return void
     */
    public function isSufficientBalance(array $params): bool
    {
        return $this->memberIncorrectScoreWallet->balance >= array_get($params, 'betAmount');
    }

}
