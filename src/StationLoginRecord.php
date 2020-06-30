<?php

namespace SuperPlatform\StationWallet;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Runner\Exception;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use SuperPlatform\StationWallet\Models\StationLoginRecord as LoginRecord;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;

/**
 * 本機錢包登入遊戲站記錄資料
 */
class StationLoginRecord
{
    /**
     * 記錄點擊狀態：
     *   unclick    未使用的登入連結
     *   clicked    已使用的登入連結
     *   abort      多次請求登入產生連結，較新請求登入成功後，被作廢的舊連結
     *   fail       遊戲端回應失敗的登入連結
     */
    const STATUS_UN_CLICK = 'unclick';
    const STATUS_CLICKED = 'clicked';
    const STATUS_ABORT = 'abort';
    const STATUS_FAIL = 'fail';

    /**
     * 寫入記錄
     *
     * @param Wallet $wallet
     * @param array $passport
     * @return LoginRecord
     */
    public function create(Wallet $wallet, array $passport)
    {
        try {
            $loginRecord = new LoginRecord();
            $loginRecord->user_id = $wallet->user_id;
            $loginRecord->account = $wallet->account;
            $loginRecord->station = $wallet->station;
            $loginRecord->status = self::STATUS_UN_CLICK;
            $loginRecord->method = array_get($passport, 'method');
            $loginRecord->web_url = array_get($passport, 'web_url');
            $loginRecord->mobile_url = array_get($passport, 'mobile_url');
            $loginRecord->params = array_get($passport, 'params');
            $loginRecord->save();
            return $loginRecord;
        } catch (Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                $wallet->station,
                'createLoginRecordData',
                $passport
            ));
            throw $exception;
        }
    }

    /**
     * 透過本機錢包取得記錄
     *
     * @param array $options
     * @return LoginRecord
     */
    public static function getRecord(array $options = [], $from = null, $to = null)
    {
        try {
            $status = (is_string(data_get($options, 'status')))
                ? collect(data_get($options, 'status'))
                : data_get($options, 'status');

            $loginRecord = LoginRecord::whereIn('status', $status);

            if (filled($from) or filled($to)) {
                $loginRecord->whereBetween('clicked_at', [$from, $to]);
            }

            if (array_has($options, 'wallet')) {
                $wallet = (data_get($options, 'wallet') instanceof Wallet)
                    ? data_get($options, 'wallet')
                    : Wallet::where('id', '=', data_get($options, 'wallet'))->first();
                $loginRecord = $loginRecord->where('station', '=', $wallet->station)
                    ->where('account', '=', $wallet->account);
            }

            return $loginRecord->get();
        } catch (Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                'getRecord',
                '透過本機錢包取得記錄',
                $options
            ));
            throw $exception;
        }
    }

    /**
     * 透過本機錢包取得記錄
     *
     * @param string|integer $id
     * @return LoginRecord
     */
    public static function getRecordById($id)
    {
        try {
            return LoginRecord::where('id', '=', $id)
                ->firstOrFail();
        } catch (Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                'getRecordById',
                '透過本機錢包取得記錄',
                ['id' => $id]
            ));
            throw $exception;
        }
    }

    /**
     * 透過本機錢包取得記錄
     *
     * @param string|integer $id
     * @return LoginRecord
     */
    public static function getRecordByUserId($userId)
    {
        try {
            return LoginRecord::where('user_id', '=', $userId)
                ->where('status', 'clicked')
                ->get();
        } catch (Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                'getRecordById',
                '透過本機錢包取得記錄',
                ['user_id' => $userId]
            ));
            throw $exception;
        }
    }

    /**
     * 透過本機錢包修改記錄為已觸發過
     *
     * @param LoginRecord|string|integer $loginRecord
     * @return LoginRecord
     */
    public static function setClicked($loginRecord)
    {
        if (is_string($loginRecord) || is_numeric($loginRecord)) {
            $loginRecord = self::getRecordById($loginRecord);
        }
        try {
            $loginRecord->status = self::STATUS_CLICKED;
            $loginRecord->clicked_at = Carbon::now()->toDateTimeString();
            $loginRecord->save();
            return $loginRecord;
        } catch (Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                'setClicked',
                '透過本機錢包修改記錄為已觸發過',
                []
            ));
            throw $exception;
        }
    }

    /**
     * 透過本機錢包修改記錄為已忽略
     *
     * @param collection $loginRecords
     * @return collection
     */
    public static function setAbort($loginRecords)
    {
        try {
            $loginRecordIds = $loginRecords->pluck('id');
            DB::table('station_login_records')
                ->whereIn('id', $loginRecordIds)
                ->update([
                    'status' => self::STATUS_ABORT
                ]);
            return LoginRecord::whereIn('id', $loginRecordIds)->get();
        } catch (Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                'setAbort',
                '透過本機錢包修改記錄為已忽略',
                []
            ));
            throw $exception;
        }
    }

    /**
     * 透過本機錢包修改記錄為登入失敗
     *
     * @param collection $loginRecords
     * @return collection
     */
    public static function setFail($loginRecords)
    {
        try {
            $loginRecordIds = $loginRecords->pluck('id');
            DB::table('station_login_records')
                ->whereIn('id', $loginRecordIds)
                ->update([
                    'status' => self::STATUS_FAIL
                ]);
            return LoginRecord::whereIn('id', $loginRecordIds)->get();
        } catch (Exception $exception) {
            // show_exception_message($exception);
            event(new ConnectorExceptionOccurred(
                $exception,
                'setFail',
                '透過本機錢包修改記錄為登入失敗',
                $loginRecords->toArray()
            ));
            throw $exception;
        }
    }
}
