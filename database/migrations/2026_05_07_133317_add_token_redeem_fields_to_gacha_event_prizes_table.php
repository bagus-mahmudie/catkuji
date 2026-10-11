<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gacha_event_prizes', function (Blueprint $table) {
            if (! Schema::hasColumn('gacha_event_prizes', 'is_token_redeemable')) {
                $table->boolean('is_token_redeemable')
                    ->default(false)
                    ->after('token_reward');
            }

            if (! Schema::hasColumn('gacha_event_prizes', 'token_redeem_price')) {
                $table->unsignedBigInteger('token_redeem_price')
                    ->default(0)
                    ->after('is_token_redeemable');
            }

            if (! Schema::hasColumn('gacha_event_prizes', 'redeem_stock_total')) {
                $table->unsignedInteger('redeem_stock_total')
                    ->default(0)
                    ->after('token_redeem_price');
            }

            if (! Schema::hasColumn('gacha_event_prizes', 'redeem_stock_left')) {
                $table->unsignedInteger('redeem_stock_left')
                    ->default(0)
                    ->after('redeem_stock_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gacha_event_prizes', function (Blueprint $table) {
            if (Schema::hasColumn('gacha_event_prizes', 'redeem_stock_left')) {
                $table->dropColumn('redeem_stock_left');
            }

            if (Schema::hasColumn('gacha_event_prizes', 'redeem_stock_total')) {
                $table->dropColumn('redeem_stock_total');
            }

            if (Schema::hasColumn('gacha_event_prizes', 'token_redeem_price')) {
                $table->dropColumn('token_redeem_price');
            }

            if (Schema::hasColumn('gacha_event_prizes', 'is_token_redeemable')) {
                $table->dropColumn('is_token_redeemable');
            }
        });
    }
};