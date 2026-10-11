<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gacha_events', function (Blueprint $table) {
            if (! Schema::hasColumn('gacha_events', 'price_coin')) {
                $table->unsignedBigInteger('price_coin')->default(0)->after('thumbnail_image');
            }

            if (! Schema::hasColumn('gacha_events', 'price_token')) {
                $table->unsignedInteger('price_token')->default(0)->after('price_coin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gacha_events', function (Blueprint $table) {
            if (Schema::hasColumn('gacha_events', 'price_token')) {
                $table->dropColumn('price_token');
            }

            if (Schema::hasColumn('gacha_events', 'price_coin')) {
                $table->dropColumn('price_coin');
            }
        });
    }
};