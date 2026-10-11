<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gacha_categories', function (Blueprint $table) {
            $table->string('promo_left_bg')->nullable()->after('card_style');
        });
    }

    public function down(): void
    {
        Schema::table('gacha_categories', function (Blueprint $table) {
            $table->dropColumn('promo_left_bg');
        });
    }
};