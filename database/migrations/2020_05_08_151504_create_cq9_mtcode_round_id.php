<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCq9MtcodeRoundId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cq9_mtcode_round_ids', function (Blueprint $table) {
            // [PK] 資料識別碼
            $table->string('mt_code', 80)->comment('[PK] 注單編號');

            // 局號
            $table->string('round_id', 40)->comment('局號');

            // 交易動作
            $table->char('action', 20)->comment('交易動作');

            // 建立時間
            $table->datetime('created_at')
                ->default(DB::raw('CURRENT_TIMESTAMP'))
                ->comment('建立時間');

            // 最後更新
            $table->datetime('updated_at')
                ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))
                ->comment('最後更新');

            // === 索引 ===
            // 指定主鍵
            $table->primary(['mt_code']);
            $table->index(['round_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cq9_mtcode_round_ids');
    }
}
