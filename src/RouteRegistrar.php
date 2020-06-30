<?php

namespace SuperPlatform\StationWallet;

use Illuminate\Contracts\Routing\Registrar as Router;
use Illuminate\Routing\Router as RoutingRouter;

class RouteRegistrar
{
    /**
     * The router implementation.
     *
     * @var \Illuminate\Contracts\Routing\Registrar
     */
    protected $router;

    /**
     * Create a new route registrar instance.
     *
     * @param \Illuminate\Contracts\Routing\Registrar $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Register routes for transient tokens, clients, and personal access tokens.
     *
     * @return void
     */
    public function all()
    {
        $this->router->group(
            [],
            function (RoutingRouter $router) {
                /**
                 * 跳轉資訊：API 資料格式
                 */
                $router->post(
                    '/play/{login_record_id}',
                    'StationWalletController@redirectInfo'
                )->name(config('station_wallet.login_redirecting.info'));
                /**
                 * 跳轉動作：直接跳轉到遊戲館（目前無法正常動作）
                 */
                $router->get(
                    '/play/{login_record_id}',
                    'StationWalletController@redirecting'
                )->name(config('station_wallet.login_redirecting.route_name'));
                /**
                 * 請求登入瑪雅時，瑪雅要回戳驗證使用路由
                 *  2.1 2.2 使用POST回调
                 *  2.3 2.4 使用GET回调的。
                 */
                // 2.1 checkLogin
                $router->post('/station/maya/CheckLogin', 'MayaController@checkLogin')->name('maya.checkLogin');
                // 2.2 getMemberLimitInfo
                $router->post(
                    '/station/maya/GetMemberLimitInfo',
                    'MayaController@getMemberLimitInfo'
                )->name('maya.getMemberLimitInfo');
                // 2.3 getMainBalance
                $router->get('/station/maya/GetMainBalance', 'MayaController@getMainBalance')->name(
                    'maya.getMainBalance'
                );
                // 2.4 gameFundTransfer
                $router->get(
                    '/station/maya/GameFundTransfer',
                    'MayaController@gameFundTransfer'
                )->name('maya.gameFundTransfer');

                /**
                 * SF電子 Login/Play/RewardBonus/GetBalance 回戳請求路由
                 */
                $router->post('/cmwallet', 'SlotFactoryController@action')->name('slot_factory.action');

                /**
                 * CMD體育 回戳確認token路由
                 */
                $router->get('/cmdsport/checktoken', 'CmdSportController@checkToken')->name('cmd_sport.checkToken');

                /**
                 * 反波膽 回戳請求路由
                 */
                $router->post('/Sports/GetMemberBalance', 'IncorrectScoreController@getMemberBalance')->name(
                    'incorrect_score.GetBalance'
                );
                $router->post('/Sports/CreateBetLog', 'IncorrectScoreController@createBetLog')->name(
                    'incorrect_score.CreateBetLog '
                );
                $router->post('/Sports/Deposit', 'IncorrectScoreController@deposit')->name('incorrect_score.Deposit');

                /**
                 * DG 回戳請求路由
                 */
                $router->group(
                    [
                        'prefix' => 'dream_game',
                    ],
                    function (RoutingRouter $router) {
                        // 獲取玩家餘額
                        $router->post('/user/getBalance/{agentName}', 'DreamGameController@getBalance')->name(
                            'dream_game.get_balance'
                        );

                        // 存取款接口
                        $router->post('/account/transfer/{agentName}', 'DreamGameController@transfer')->name(
                            'dream_game.transfer'
                        );

                        // 確認存取款結果接口
                        $router->post('/account/checkTransfer/{agentName}', 'DreamGameController@checkTransfer')->name(
                            'dream_game.check_transfer'
                        );

                        // 請求回滾轉帳事務
                        $router->post('/account/inform/{agentName}', 'DreamGameController@inform')->name(
                            'dream_game.inform'
                        );
                    }
                );

                /**
                 * All Bet 回戳請求路由
                 */
                $router->group(
                    [
                        'prefix' => 'all_bet',
                    ],
                    function (RoutingRouter $router) {
                        // 獲取玩家餘額
                        $router->get('/get_balance/{client}', 'AllBetController@getBalance')->name(
                            'all_bet.get_balance'
                        );

                        // 上下分
                        $router->post('/transfer', 'AllBetController@transfer')->name('all_bet.transfer');
                    }
                );

                /**
                 * Sa Gaming 回戳請求路由
                 */
                $router->group(
                    [
                        'prefix' => 'sa_gaming',
                    ],
                    function (RoutingRouter $router) {
                        // 獲取玩家餘額
                        $router->post('/GetUserBalance', 'SaGamingController@getUserBalance')->name(
                            'sa_gaming.get_user_balance'
                        );

                        // 下注
                        $router->post('/PlaceBet', 'SaGamingController@PlaceBet')->name('sa_gaming.place_bet');

                        // 派彩贏
                        $router->post('/PlayerWin', 'SaGamingController@PlayerWin')->name('sa_gaming.player_win');

                        // 派彩輸 不需要補點
                        $router->post('/PlayerLost', 'SaGamingController@PlayerLost')->name('sa_gaming.player_lost');

                        // 取消下注
                        $router->post('/PlaceBetCancel', 'SaGamingController@PlaceBetCancel')->name(
                            'sa_gaming.place_bet_cancel'
                        );
                    }
                );

                /**
                 * CQ9 回戳請求路由
                 */
                $router->group(
                    [
                        'prefix' => 'cq9_game',
                    ],
                    function (RoutingRouter $router) {
                        // 確認該帳號是否為貴司玩家
                        $router->get('player/check/{account}', 'Cq9GameController@checkPlayer')->name(
                            'cq9_game.check_player'
                        );

                        // 取得錢包餘額
                        $router->get('/transaction/balance/{account}', 'Cq9GameController@balance')->name(
                            'cq9_game.balance'
                        );

                        // 老虎機下注
                        $router->post('/transaction/game/bet', 'Cq9GameController@gameBet')->name('cq9_game.game_bet');

                        // 結束回合並統整該回合贏分
                        $router->post('/transaction/game/endround', 'Cq9GameController@endRound')->name(
                            'cq9_game.end_round'
                        );

                        // 牌桌及漁機遊戲，轉出一定額度金額至牌桌或漁機遊戲而調用
                        $router->post('transaction/game/rollout', 'Cq9GameController@rollout')->name(
                            'cq9_game.rollout'
                        );

                        // 牌桌/漁機一場遊戲結束，將金額轉入錢包
                        $router->post('/transaction/game/rollin', 'Cq9GameController@rollin')->name('cq9_game.rollin');

                        // 把玩家所有的錢領出，轉入漁機遊戲
                        $router->post('/transaction/game/takeall', 'Cq9GameController@takeAll')->name(
                            'cq9_game.takeall'
                        );

                        // 完成的訂單做扣款
                        $router->post('/transaction/game/debit', 'Cq9GameController@debit')->name('cq9_game.debit');

                        // 完成的訂單做補款
                        $router->post('/transaction/game/credit', 'Cq9GameController@credit')->name('cq9_game.credit');

                        // 遊戲紅利
                        $router->post('/transaction/game/bonus', 'Cq9GameController@bonus')->name('cq9_game.bonus');

                        // 活動派彩
                        $router->post('/transaction/user/payoff', 'Cq9GameController@payoff')->name('cq9_game.payoff');

                        // 押注退還
                        $router->post('/transaction/game/refund', 'Cq9GameController@refund')->name('cq9_game.refund');

                        // 查詢交易紀錄
                        $router->get('/transaction/record/{serialNo}', 'Cq9GameController@record')->name(
                            'cq9_game.record'
                        );
                    }
                );

                /**
                 * Super 體育 回戳請求路由
                 */
                $router->group(
                    [
                        'prefix' => 'super_sport',
                    ],
                    function (RoutingRouter $router) {
                        // 獲取玩家餘額
                        $router->post('/sport/balance', 'SuperSportController@balance')->name(
                            'super_sport.balance'
                        );

                        // 投注
                        $router->post('/sport/bet', 'SuperSportController@bet')->name(
                            'super_sport.bet'
                        );

                        // 錢包金額異動
                        $router->post('/sport/addOrDeposit', 'SuperSportController@addOrDeposit')->name(
                            'super_sport.add_or_deposit'
                        );
                    }
                );

                /**
                 * WM 真人 回戳請求路由
                 */
                $router->group(
                    [
                        'prefix' => 'wm_casino',
                    ],
                    function (RoutingRouter $router) {
                        // 由動作派發器決定行為
                        $router->post('/', 'WmCasinoController@actionDispatcher')->name('wm_casino.action-dispatcher');
                    }
                );

                /**
                 * AMEBA 回戳請求路由
                 */
                $router->group(
                    [
                        'prefix' => 'ameba',
                    ],
                    function (RoutingRouter $router) {
                        // 由動作派發器決定行為
                        $router->post('/', 'AmebaController@actionDispatcher')->name('ameba.action-dispatcher');
                    }
                );

                /**
                 * QT 電子 回戳請求路由
                 */
                $router->group(
                    [
                        'prefix' => 'q_tech',
                    ],
                    function (RoutingRouter $router) {
                        // 驗證 session
                        $router->get('/accounts/{playerId}/session', 'QTechController@verifySession')->name('q_tech.verify_session');

                        // 獲取餘額
                        $router->get('/accounts/{playerId}/balance', 'QTechController@balance')->name('q_tech.get_balance');

                        // 加扣點
                        $router->post('/transactions', 'QTechController@transactions')->name('q_tech.transaction');

                        // 回滾
                        $router->post('transactions/rollback', 'QTechController@rollback')->name('q_tech.rollback');
                    }
                );
            }
        );
    }
}
