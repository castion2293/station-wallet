<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAllBetSingleWalletBetNo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('all_bet_single_wallet_bet_no', function (Blueprint $table) {
            // [PK] 資料識別碼
            $table->unsignedBigInteger('bet_no')->comment('[PK] 注單編號');

            // 下注金額 本金
            $table->decimal('bet_amount', 18, 4)->comment('下注金額 本金');

            // 輸贏金額
            $table->decimal('win_lose_amount', 18, 4)->comment('輸贏金額');

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
            $table->primary(['bet_no']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('all_bet_single_wallet_bet_no');
    }
}
