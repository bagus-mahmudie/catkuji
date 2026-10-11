<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gacha_event_prizes', function (Blueprint $table) {
            if (! Schema::hasColumn('gacha_event_prizes', 'coin_price')) {
                $table->unsignedBigInteger('coin_price')->default(0)->after('sellback_price_override');
            }

            if (! Schema::hasColumn('gacha_event_prizes', 'token_reward')) {
                $table->unsignedInteger('token_reward')->default(0)->after('coin_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gacha_event_prizes', function (Blueprint $table) {
            if (Schema::hasColumn('gacha_event_prizes', 'token_reward')) {
                $table->dropColumn('token_reward');
            }

            if (Schema::hasColumn('gacha_event_prizes', 'coin_price')) {
                $table->dropColumn('coin_price');
            }
        });
    }
};