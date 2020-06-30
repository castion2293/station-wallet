<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStationWalletsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('station_wallets', function (Blueprint $table) {

            // === 欄位 ===
            // [PK] 資料識別碼：對應資料的唯一識別碼後 12 碼，通常是使用會員識別碼後 12 碼
            $table->char('id', 26)
                ->comment('[PK] 資料識別碼：');

            // [FK] 對應使用者userID：會員帳號的資料識別碼
            $table->string('user_id', 26)->nullable()->default(null);

            // [FK] 對應使用者帳號：會員識別碼後 12 碼，這帳號會寫到遊戲站端當會員帳號
            // 備註：
            //   寫入的對應使用者帳號 type 若是 number 型態, 透過 vinkla/hashids 套件編碼 to string 並擷取後 12 碼
            //   若是 string 就直接擷取後 12 碼
            $table->string('account', 36)
                ->comment('對應使用者帳號：會員識別碼後 12 碼，這帳號會寫到遊戲站端當會員帳號');

            // 錢包密碼
            $table->string('password', 36)
                ->comment('錢包密碼：以錢包id欄位，md5後取config所設定之位置為密碼，若遊戲站需要密碼，這密碼會寫到遊戲站端當密碼');

            // 遊戲站名稱
            $table->string('station', 36)
                ->comment('遊戲站名稱');

            // 錢包狀態 (active=有效的, freezing=凍結的)
            $table->enum('status', ['active', 'freezing'])->default('active')
                ->comment('錢包狀態');

            // 錢包同步狀態 (free=非同步中, lock=同步中)
            $table->enum('sync_status', ['free', 'lock'])->default('free')
                ->comment('錢包同步狀態');

            // 錢包在該遊戲站是否已開通(是否已在遊戲站創建帳號)
            $table->enum('activated_status', ['yes', 'no'])->default('no')
                ->comment('是否已開通');

            // 錢包餘額
            $table->decimal('balance', 11, 4)->default(0)
                ->comment('錢包餘額');

            // 備註
            $table->text('remark')
                ->comment('備註');

            // 最後同步(開始)時間
            $table->datetime('last_sync_at')
                ->comment('最後同步(開始的)時間')->nullable()->default(null);;

            // 建立時間
            $table->datetime('created_at')
                ->comment('建立時間');

            // 最後更新
            $table->datetime('updated_at')
                ->comment('最後更新');
        });

        // ----
        //   定義索引與約束資料
        // ----
        Schema::table('station_wallets', function ($table) {

            // 指定主鍵
            $table->primary('id');

            // 一個人同一站只會存在一個錢包資料
            $table->unique(['account', 'station']);

            // 軟刪除
            $table->softDeletes();

            // 索引
            // 遊戲站名稱
            $table->index('station');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('station_wallets');
    }
}
