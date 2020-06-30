<?php

namespace SuperPlatform\StationWallet\Models;

use Ariby\Ulid\HasUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * 遊戲站錢包
 *
 * @package SuperPlatform\StationWallet\Models
 */
class StationLoginRecord extends Model
{
    use HasUlid;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $casts = [
        'params' => 'json'
    ];
}
