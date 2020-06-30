<?php

namespace SuperPlatform\StationWallet\Models;

use Ariby\Ulid\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Kblais\QueryFilter\Traits\FilterableTrait;
use Kblais\QueryFilter\Traits\SortableTrait;
use SuperPlatform\StationWallet\StationWallet as SrcWallet;

/**
 * 遊戲站錢包
 *
 * @package SuperPlatform\StationWallet\Models
 */

/**
 * Class StationWallet
 * @package SuperPlatform\StationWallet\Models
 * @property char id Ulid
 * @property char user_id 對應使用者userID：會員帳號的資料識別碼
 * @property String account 帳號
 * @property String password 密碼
 * @property String station 遊戲站
 * @property decimal balance 點數
 * @property enum status 錢包狀態
 * @property enum sync_status 同步狀態
 * @property text remark 備註
 * @property enum activated_status 錢包在該遊戲站是否已開通(是否已在遊戲站創建帳號)
 * @property datetime last_sync_at 最後同步時間
 *
 */
class StationWallet extends Model
{
    use HasUlid;
    use FilterableTrait;
    use SortableTrait;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'balance' => 'float',
    ];

    protected $attributes = [
        'balance' => 0,
        'remark' => '',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }

    /**
     * 如果沒給電話給個假電話
     */
    public function setMobileAttribute($value)
    {
        if (!array_has($this->attributes, 'mobile')) {
            $this->attributes['mobile'] = SrcWallet::generateFakeMobile();
        }
    }
}
