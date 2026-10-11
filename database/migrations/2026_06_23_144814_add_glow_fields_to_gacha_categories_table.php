<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gacha_categories', function (Blueprint $table) {
            $table->string('category_glow_color')->nullable()->after('promo_left_bg');
            $table->string('category_glow_position')->default('right')->after('category_glow_color');
        });
    }

    public function down(): void
    {
        Schema::table('gacha_categories', function (Blueprint $table) {
            $table->dropColumn(['category_glow_color', 'category_glow_position']);
        });
    }
};