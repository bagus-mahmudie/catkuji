<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Item redeem token bukan berasal dari draw_result.
         * Jadi draw_result_id harus boleh null.
         */
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE user_inventories MODIFY draw_result_id BIGINT UNSIGNED NULL');
        }

        Schema::table('user_inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('user_inventories', 'token_redeem_transaction_id')) {
                $table->foreignId('token_redeem_transaction_id')
                    ->nullable()
                    ->after('draw_result_id')
                    ->constrained('token_redeem_transactions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_inventories', function (Blueprint $table) {
            if (Schema::hasColumn('user_inventories', 'token_redeem_transaction_id')) {
                $table->dropConstrainedForeignId('token_redeem_transaction_id');
            }
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE user_inventories MODIFY draw_result_id BIGINT UNSIGNED NOT NULL');
        }
    }
};