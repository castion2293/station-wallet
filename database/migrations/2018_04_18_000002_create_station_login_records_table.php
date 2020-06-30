<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStationLoginRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('station_login_records', function (Blueprint $table) {

            // === 欄位 ===
            // [PK] 資料識別碼
            $table->char('id', 26)
                ->comment('[PK] 資料識別碼');

            // [FK] 對應使用者userID：會員帳號的資料識別碼碼
            $table->string('user_id', 26)->nullable()->default(null);

            // [FK] 對應使用者帳號：會員識別碼後 12 碼，這帳號會寫到遊戲站端當會員帳號
            // 備註：
            //   寫入的對應使用者帳號 type 若是 number 型態, 透過 vinkla/hashids 套件編碼 to string 並擷取後 12 碼
            //   若是 string 就直接擷取後 12 碼
            $table->char('account', 12)
                ->comment('對應使用者帳號：會員識別碼後 12 碼，這帳號會寫到遊戲站端當會員帳號');

            // 遊戲站名稱
            $table->string('station')->default('')
                ->comment('遊戲站名稱', 36);

            // passport method
            $table->string('method')->default('')
                ->comment('passport method', 36);

            // passport web_url
            $table->text('web_url')->nullable()
                ->comment('passport web_url');

            // passport mobile_url
            $table->text('mobile_url')->nullable()
                ->comment('passport mobile_url');

            // passport params
            $table->text('params')->nullable()
                ->comment('passport params');

            // 是否點擊登入
            //   'unclick' 未使用的登入連結
            //   'clicked' 已使用的登入連結
            //   'abort' 多次請求登入產生連結，較新請求登入成功後，被作廢的舊連結
            //   'fail' 請求登入，遊戲端回應失敗的登入連結
            $table->enum('status', ['unclick', 'clicked', 'abort', 'fail'])->default('unclick')
                ->comment('是否點擊登入，unclick：未使用的登入連結，clicked：已使用的登入連結，abort：多次請求登入產生連結，較新請求登入成功後，被作廢的舊連結，fail：遊戲端回應失敗的登入連結，');

            // 點擊時間
            $table->datetime('clicked_at')->nullable()
                ->comment('點擊時間');

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
        Schema::table('station_login_records', function ($table) {

            // 指定主鍵
            $table->primary('id');

            // 索引
            $table->index('station'); // 關聯
            $table->index('status');
            $table->index('created_at');

            // 軟刪除
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('station_login_records');
    }
}
