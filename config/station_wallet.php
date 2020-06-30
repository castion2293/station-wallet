<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 呼叫 API 時，每個遊戲站所需要的必填參數預設值
    |--------------------------------------------------------------------------
    | https (預設) : 沙龍、歐博、DG
    | http (不支援 https 的遊戲館) : super體育、賓果、super彩、瑪雅
    |--------------------------------------------------------------------------
    */
    'stations' => [
        'all_bet' => [
            'scheme' => 'https',
            'build' => [
                'agent' => env('ALL_BET_AGENT_ACCOUNT'),
                'normal_handicaps' => env('ALL_BET_NORMAL_HANDICAPS'),
                'vip_handicaps' => env('ALL_BET_VIP_HANDICAPS'),
                'normal_hall_rebate' => 0
            ],
            'passport' => [
                'language' => env('ALL_BET_LANGUAGE', 'zh_TW'),
            ],
        ],
        'bingo' => [
            'scheme' => 'http',
            'getLimit' => [
                //一般玩法：單、雙、平，可設定: bet_max(上限)，bet_min(下限)
                'normal_odd_even_draw' => env('BINGO_API_LIMIT_NORMAL_ODD_EVEN_DRAW', null),
                //一般玩法：大、小、合，可設定: bet_max(上限)，bet_min(下限)
                'normal_big_small_tie' => env('BINGO_API_LIMIT_NORMAL_BIG_SMALL_TIE', null),
                //超級玩法(特別號)：大、小，可設定: bet_max(上限)，bet_min(下限)
                'super_big_small' => env('BINGO_API_LIMIT_SUPER_BIG_SMALL', null),
                //超級玩法(特別號)：單、雙，可設定: bet_max(上限)，bet_min(下限)
                'super_odd_even' => env('BINGO_API_LIMIT_SUPER_ODD_EVEN', null),
                //超級玩法(特別號)：獨猜，可設定: bet_max(上限)，bet_min(下限)
                'super_guess' => env('BINGO_API_LIMIT_SUPER_GUESS', null),
                //星號，可設定: bet_max(上限)，bet_min(下限)
                'star' => env('BINGO_API_LIMIT_STAR', null),
                //五行，可設定: bet_max(上限)，bet_min(下限)
                'elements' => env('BINGO_API_LIMIT_ELEMENTS', null),
                //四季，可設定: bet_max(上限)，bet_min(下限)
                'seasons' => env('BINGO_API_LIMIT_SEASONS', null),
                //不出球，可設定: bet_max(上限)，bet_min(下限)
                'other_fanbodan' => env('BINGO_API_LIMIT_OTHER_FANBODAN', null),
            ]
        ],
        'sa_gaming' => [
            'scheme' => 'https',
            'build' => [],
            'passport' => [
                'language' => env('SA_GAMING_DEFAULT_LANGUAGE', 'zh_TW')
            ],
            'updateBetLimit' => [
                'Set1' => env('SA_GAMING_UPDATE_LIMIT_SET1', ''),
                'Set2' => env('SA_GAMING_UPDATE_LIMIT_SET2', ''),
                'Set3' => env('SA_GAMING_UPDATE_LIMIT_SET3', ''),
                'Set4' => env('SA_GAMING_UPDATE_LIMIT_SET4', ''),
                'Set5' => env('SA_GAMING_UPDATE_LIMIT_SET5', ''),
            ],
        ],
        'maya' => [
            'scheme' => 'http',
            'build' => [
                'VenderNo' => env('MAYA_API_PROPERTY_ID'),
                'GameConfigId' => env('MAYA_TEST_MEMBER_GMAE_CONFIG_ID'),
            ],
            'balance' => [
                'VenderNo' => env('MAYA_API_PROPERTY_ID'),
            ]
        ],
        'super_sport' => [
            'scheme' => 'http',
            'build' => [
                'up_account' => env('SUPER_SPORT_AGENT_ACCOUNT'),
                'up_password' => env('SUPER_SPORT_AGENT_PASSWORD'),
                'act' => env('SUPER_SPORT_COPY_ACCOUNT_ACT', 'add'),
                'copyAccount' => env('SUPER_SPORT_COPY_ACCOUNT_LIMIT'),
            ],
            'updateProfile' => [
                'updateAction' => env('SUPER_SPORT_UPDATE_PROFILE_ACTION'),
            ],
            'remark' => [
                'payout' => '派彩',
                'repay' => '重新派彩',
                'refund' => '取消注單',
                'refundCancel' => '恢復取消的注單',
            ]
        ],
        'ufa_sport' => [
            'scheme' => 'http',
            'build' => [],
            'passport' => [
                'lang' => env('UFA_SPORT_PASSPORT_LANG', 'TH-TH'),
                'accType' => env('UFA_SPORT_PASSPORT_HANDICAP', 'MY')
            ],
            'updateProfile' => [
                'max' => env('UFA_SPORT_UPDATE_LIMIT_SINGLE_MAX_AMOUNT', '20000'),
                'lim' => env('UFA_SPORT_UPDATE_LIMIT_SINGLE_GAME_MAX_AMOUNT', '50000'),
                'com' => env('UFA_SPORT_UPDATE_LIMIT_COMMISSION', '0'),
                'comtype' => env('UFA_SPORT_UPDATE_LIMIT_COMMISSION_TYPE', 'A')
            ]
        ],
        'dream_game' => [
            'scheme' => 'https',
            'build' => [
                'agent' => config("api_caller.dream_game.config.api_agent"),
                'language' => env('DREAM_GAME_DEFAULT_LOCALE', 'en'),
                'handicap' => env('DREAM_GAME_DEFAULT_HANDICAP', 'B'),
            ],
            'passport' => [
                'hideMobileAppLogo' => env('DREAM_GAME_HIDDEN_MOBILE_APP_LOGO'),
            ],
        ],
        'super_lottery' => [
            'scheme' => 'http',
            'build' => [
                'up_account' => env('SUPER_LOTTERY_AGENT_ACCOUNT'),
                'up_password' => env('SUPER_LOTTERY_AGENT_PASSWORD'),
                'act' => env('SUPER_LOTTERY_COPY_ACCOUNT_ACT', 'create'),
                'copyAccount' => env('SUPER_LOTTERY_COPY_ACCOUNT_LIMIT'),
            ],
            'updateProfile' => [
                'updateAction' => env('SUPER_LOTTERY_UPDATE_PROFILE_ACTION'),
            ]
        ],
//        'hong_chow' => [
//            'scheme' => 'https',
//            'build' => [
//                'backend_account' => env('HONG_CHOW_BACKEND_ACCOUNT'),
//                'backend_password' => env('HONG_CHOW_BACKEND_PASSWORD'),
//            ]
//        ],
        'ameba' => [
            'scheme' => 'http',
            'build' => [
                'backend_account' => env('AMEBA_BACKEND_ACCOUNT'),
                'backend_password' => env('AMEBA_BACKEND_PASSWORD'),
                'test_account' => env('AMEBA_TEST_ACCOUNT'),
                'test_password' => env('AMEBA_TEST_PASSWORD'),
                'language' => env('AMEBA_GAME_DEFAULT_LOCALE', 'zhTW'),
                'currency' => env('AMEBA_DEFAULT_CURRENCY', 'TWD'),
            ]
        ],
        'so_power' => [
            'scheme' => 'https',
            'build' => [],
            'passport' => [
                'lobby_lang' => env('SO_POWER_LOBBY_LANGUAGE', 'zh-TW')
            ]
        ],
        'real_time_gaming' => [
            'scheme' => 'https',
            'build' => [
                'test_account' => env('RTG_TEST_MEMBER_ACCOUNT'),
                'language' => env('RTG_DEFAULT_LANGUAGE', null),
                'locale' => env('RTG_DEFAULT_LOCALE', 'zh-CN')
            ],
            'passport' => [
                'language_lobby' => env('RTG_DEFAULT_LANGUAGE_LOBBY', 'CN')
            ],
        ],
        'royal_game' => [
            'scheme' => 'https',
            'build' => [
                'currency' => env('ROYAL_GAME_CURRENCY', 'NT'),
            ],
            'getLimit' => [
                'limit' => env('ROYAL_GAME_SETTING_LIMIT_LEVEL') ? array_filter(explode(',', env('ROYAL_GAME_SETTING_LIMIT_LEVEL'))) : [],
            ],
            'updateBetLimit' => [
                'update_bacc_limit' => env('ROYAL_GAME_UPDATE_BACC_LIMIT_LEVEL') ? array_filter(explode(',', env('ROYAL_GAME_UPDATE_BACC_LIMIT_LEVEL'))) : [],
                'update_InsuBacc_limit' => env('ROYAL_GAME_UPDATE_INSUBACC_LIMIT_LEVEL') ? array_filter(explode(',', env('ROYAL_GAME_UPDATE_INSUBACC_LIMIT_LEVEL'))) : [],
                'update_LunPan_limit' => env('ROYAL_GAME_UPDATE_LUNPAN_LIMIT_LEVEL') ? array_filter(explode(',', env('ROYAL_GAME_UPDATE_LUNPAN_LIMIT_LEVEL'))) : [],
                'update_ShziZi_limit' => env('ROYAL_GAME_UPDATE_SHZIZI_LIMIT_LEVEL') ? array_filter(explode(',', env('ROYAL_GAME_UPDATE_SHZIZI_LIMIT_LEVEL'))) : [],
                'update_FanTan_limit' => env('ROYAL_GAME_UPDATE_FANTAN_LIMIT_LEVEL') ? array_filter(explode(',', env('ROYAL_GAME_UPDATE_FANTAN_LIMIT_LEVEL'))) : [],
                'update_LongHu_limit' => env('ROYAL_GAME_UPDATE_LONGHU_LIMIT_LEVEL') ? array_filter(explode(',', env('ROYAL_GAME_UPDATE_LONGHU_LIMIT_LEVEL'))) : [],
            ],
            'passport' => [
                'lang' => env('ROYAL_GAME_LANG', 2),
            ],
        ],
        'ren_ni_ying' => [
            'scheme' => 'https',
            'build' => [
//                'parentAgentUserId' => env('REN_NI_YING_AGENT_ID'),
//                'balk' => env('REN_NI_YING_BALK'),
//                'parentProportion' => env('REN_NI_YING_PROPORTION'),
//                'maxProfit' => env('REN_NI_YING_MAXPROFIT'),
//                'keepRebateRate' => env('REN_NI_YING_KEEPREBATETATE')
            ],
        ],
        'winner_sport' => [
            'scheme' => 'http',
            'build' => [
                'api_url' => env('WINNER_SPORT_API_URL'),
                'api_key' => env('WINNER_SPORT_APY_KEY'),
                'token' => env('WINNER_SPORT_TOKEN'),
                'top_account' => env('WINNER_SPORT_TOP_ACCOUNT'),
                'login_path' => env('WINNER_SPORT_LOGIN_PATH'),
                'istest' => filter_var(env('WINNER_SPORT_ISTEST', 'no'), FILTER_VALIDATE_BOOLEAN),
            ]
        ],
        'q_tech' => [
            'scheme' => 'https',
        ],
        'wm_casino' => [
            'scheme' => 'https',
            'build' => [
                // 最大可贏
                'maxwin' => env('WM_CASINO_MEMBER_MAX_WIN'),
                // 最大可輸
                'maxlose' => env('WM_CASINO_MEMBER_MAX_LOSE'),
                // 會員退水是否歸零   0為: 不歸零 1為: 歸零
                'rakeback' => env('WM_CASINO_MEMBER_RAKE_BACK'),
            ],
            'passport' => [
                // 0 或 空值 为简体中文
                // 1 为英文
                // 2 为泰文
                // 3 为越文
                // 4 为日文
                // 5 为韩文
                // 6 为印度文
                // 7 为马来西亚文
                // 8 为印尼文
                // 9 为繁体中文
                // 10 为西文
                'language' => env('WM_CASINO_PASSPORT_LANG', '9'),
            ],
        ],
        'forever_eight' => [
            'scheme' => 'https',
            'build' => [
                // 人民币 CNY
                // 新台币 TWD
                'currency' => env('FOREVER_EIGHT_CURRENCY', 'TWD'),
            ],
            'passport' => [
                // 简体中文 – zh-cn
                // 繁体中文 – zh-tw
                // 英文 – en
                'language' => env('FOREVER_EIGHT_PASSPORT_LANG', 'zh-tw'),
            ],
        ],
        'slot_factory' => [
            'scheme' => 'https',
        ],
        'cmd_sport' => [
            'scheme' => 'http',
        ],
        'cock_fight' => [
            'scheme' => 'https',
        ],
        'vs_lottery' => [
            'scheme' => 'https',
            'build' => [
                'currency' => env('VS_LOTTERY_CURRENCY', 'VND'),
            ],
            'passport' => [
                'language' => env('VS_LOTTERY_LANG', 'zh-Hant'),
            ],
        ],
        'awc_sexy' => [
            'scheme' => 'https',
        ],
        'habanero' => [
            'scheme' => 'https',
        ],
        'kk_lottery' => [
            'scheme' => 'https',
        ],
        'incorrect_score' => [
            'scheme' => 'https',
            'build' => [
                'singleWalletKey' => env('INCORRECT_SCORE_SINGLE_WALLET_KEY'),
            ],
            'singleWallet' => [
                'DP' => '派彩入款',
                'RT' => '撤单返还',
                'RR' => '撤单',
                'RP' => '重新派彩扣回',
            ],
        ],
        'mg_poker' => [
            'scheme' => 'https',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 錢包帳號前綴的讀取
    |--------------------------------------------------------------------------
    | 若都沒設定，先給到 ZA 測試機專用 wdemo 代碼前綴
    */
    'wallet_account_prefix' => env('APP_ID', env('TEST_APP_ID', 'TT')),

    /*
    |--------------------------------------------------------------------------
    | 使用者呼叫建立主要錢包時的預設名稱
    |--------------------------------------------------------------------------
    | 當使用 $user->buildMasterWallet() 時，預設遊戲站名（station）會代入此預設字串
    */
    'master_wallet_name' => 'master',

    /*
    |--------------------------------------------------------------------------
    | 取得遊戲站登入連結之後，本機跳轉到遊戲端之前，為中間夾一層連結（視圖）的行為定義其相關變數
    |--------------------------------------------------------------------------
    | route_name    登入前，夾心連結的統一連結路由名稱
    | web_title     登入前，夾心連結的使用的視圖頁標題 <title>{{ web_title }}</title>
    */
    'login_redirecting' => [
        'route_name' => 'station.wallet.redirect-login',
        'info' => 'station.wallet.redirect-info',
        'web_title' => 'Redirecting ...',
    ],

    /*
    |--------------------------------------------------------------------------
    | 是否要載入 ExceptionHelper.php 的輔助方法
    |--------------------------------------------------------------------------
    | 若設為 true, StationWalletServiceProvider 會在 boot() 時 include 這個輔助方法
    */
    'enable_exception_helper' => true,

    /*
    |--------------------------------------------------------------------------
    | Migration 的外鍵約束.
    |--------------------------------------------------------------------------
    | 設定 station_wallets Model 的 account 關連，資料表建立後的關聯會是：
    |
    |     FOREIGN KEY `station_wallets`.`account` REFERENCES `user`.`id`
    */
    'station_wallets_foreign_key' => [
        'table_name' => 'user',
        'column_name' => 'id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hashids 使用的 key-length，在此設定可避免程式直接讀取 env 的設定
    |--------------------------------------------------------------------------
    | HASH_ID_SALT 可使用 APP_KEY
    */
    'hashids' => [
        'salt' => env('HASH_ID_SALT', env('APP_KEY', 'station_wallets_hash_9527')),
        'length' => env('HASH_ID_LENGTH', 12),
    ],
];
