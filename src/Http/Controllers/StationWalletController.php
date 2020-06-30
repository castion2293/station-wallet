<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use SuperPlatform\StationWallet\StationLoginRecord;

class StationWalletController
{
    /**
     * 視圖內容為表單，填入 passport（Model StationLoginRecord 記錄中有 passport 數據）資料，載入視圖時會自動提交表單
     *
     * @param $loginRecordId
     * @param Request $request
     * @return Factory|View
     */
    public function redirecting($loginRecordId, Request $request)
    {
        $record = StationLoginRecord::setClicked($loginRecordId)->toArray();

        if ($request->has('mobile')) {
            $station = $record['station'] . 'Mobile';
            $mobileData = $this->$station($record);

            return view('station_wallet::redirecting', [
                'method' => array_get($mobileData, 'method'),
                'web_url' => array_get($mobileData, 'web_url'),
                'params' => array_get($mobileData, 'params'),
            ]);
        }

        return view('station_wallet::redirecting', [
            'method' => array_get($record, 'method'),
            'web_url' => array_get($record, 'web_url'),
//            'mobile_url' => array_get($record, 'mobile_url'),
            'params' => array_get($record, 'params') ?? [],
        ]);
    }

    /**
     * 取得登入遊戲館所需資訊，回傳資料為 json
     *
     * @param $loginRecordId
     * @return Factory|View
     */
    public function redirectInfo($loginRecordId)
    {
        $record = StationLoginRecord::setClicked($loginRecordId)->toArray();
        return response()
            ->json([
                'method' => array_get($record, 'method'),
                'web_url' => array_get($record, 'web_url'),
                'mobile_url' => array_get($record, 'mobile_url'),
                'params' => array_get($record, 'params'),
            ], Response::HTTP_OK, [], JSON_UNESCAPED_SLASHES);
    }

    /**
     * 組賓果手機版本所需要的參數
     *
     * @param $record
     * @return array
     */
    protected function bingoMobile($record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * 組沙龍手機版本所需要的參數
     *
     * @param $record
     * @return array
     */
    protected function sa_gamingMobile($record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'web_url');
        $data['params'] = [
            'username' => array_get($record, 'params.username'),
            'token' => array_get($record, 'params.token'),
            'lobby' => array_get($record, 'params.lobby'),
            'lang' => array_get($record, 'params.lang'),
            'returnurl' => array_get($record, 'params.returnurl'),
            'mobile' => 'true'
        ];

        return $data;
    }

    /**
     * 組歐博手機版本所需要的參數
     *
     * @param $record
     * @return array
     */
    protected function all_betMobile($record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'web_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * 組體育手機版本所需要的參數
     *
     * @param $record
     * @return array
     */
    protected function super_sportMobile($record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'web_url');
        $data['params'] = [
            'h5web' => true
        ];

        return $data;
    }

    /**
     * 組瑪雅手機版本所需要的參數
     *
     * @param $record
     * @return array
     */
    protected function mayaMobile($record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'web_url') . '&EntryType=1';
        $data['params'] = array_get($record, 'params');

        return $data;
    }

    /**
     * 組 Dream Game 手機版本所需要的參數
     *
     * @param $record
     * @return array
     */
    protected function dream_gameMobile($record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * 組彩球手機版本所需要的參數
     *
     * @param $record
     * @return array
     */
    protected function super_lotteryMobile($record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'web_url');
        $data['params'] = [
            'PostData' => array_get($record, 'params.PostData')
        ];

        return $data;
    }

    /**
     * 皇朝 手機版本路由指向
     *
     * @param $record
     * @return array
     */
    protected function hong_chowMobile($record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = [
            'token' => array_get($record, 'params.token'),
        ];

        return $data;
    }

    /**
     * AMEBA 手機版本路由指向
     *
     * @param array $record
     * @return array
     */
    protected function amebaMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * so power
     *
     * @param array $record
     * @return array
     */
    protected function so_powerMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = [
            'token' => array_get($record, 'params.token'),
        ];

        return $data;
    }

    /**
     * RTG
     *
     * @param array $record
     * @return array
     */
    protected function real_time_gamingMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = [
            'token' => array_get($record, 'params.token'),
        ];

        return $data;
    }

    /**
     * 皇家 手機版本路由指向
     *
     * @param array $record
     * @return array
     */
    protected function royal_gameMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * 任你贏 手機版本路由指向
     *
     * @param array $record
     * @return array
     */
    protected function ren_ni_yingMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * UFA體育 手機版本路由指向
     *
     * @param array $record
     * @return array
     */
    protected function ufa_sportMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * CQ9
     *
     * @param array $record
     * @return array
     */
    protected function cq9_gameMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * 贏家體育
     *
     * @param array $record
     * @return array
     */
    protected function winner_sportMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * 9K彩票
     *
     * @param array $record
     * @return array
     */
    protected function nine_k_lotteryMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * 9K彩票(自開彩)
     *
     * @param array $record
     * @return array
     */
    protected function nine_k_lottery_2Mobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * QT電子
     *
     * @param array $record
     * @return array
     */
    protected function q_techMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * WM真人
     *
     * @param array $record
     * @return array
     */
    protected function wm_casinoMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * 人人棋牌
     *
     * @param array $record
     * @return array
     */
    protected function bobo_pokerMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }
    /**
     * AV 電子
     *
     * @param array $record
     * @return array
     */
    protected function forever_eightMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * SF 電子
     *
     * @param array $record
     * @return array
     */
    protected function slot_factoryMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * S128 鬥雞
     *
     * @param array $record
     * @return array
     */
    protected function cock_fightMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }
    /**
     * 賓果牛牛
     *
     * @param array $record
     * @return array
     */
    protected function bingo_bullMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }
    /**
     * CMD體育
     *
     * @param array $record
     * @return array
     */
    protected function cmd_sportMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }
    /**
     * vs_lottery 越南彩
     *
     * @param array $record
     * @return array
     */
    protected function vs_lotteryMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * amc_sexy 性感百家
     *
     * @param array $record
     * @return array
     */
    protected function awc_sexyMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * Habanero HB電子
     *
     * @param array $record
     * @return array
     */
    protected function habaneroMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }

    /**
     * kk_lottery KK彩票
     *
     * @param array $record
     * @return array
     */
    protected function kk_lotteryMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }
    /**
     * incorrect_score 反波膽
     *
     * @param array $record
     * @return array
     */
    protected function incorrect_scoreMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];
        return $data;
    }
    /**
     * MG棋牌
     *
     * @param array $record
     * @return array
     */
    protected function mg_pokerMobile(array $record): array
    {
        $data['method'] = array_get($record, 'method');
        $data['web_url'] = array_get($record, 'mobile_url');
        $data['params'] = array_get($record, 'params') ?? [];

        return $data;
    }
}