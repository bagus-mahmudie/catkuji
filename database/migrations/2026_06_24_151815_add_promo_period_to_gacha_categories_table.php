<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gacha_categories', function (Blueprint $table) {
            $table->timestamp('promo_starts_at')->nullable()->after('category_glow_position');
            $table->timestamp('promo_ends_at')->nullable()->after('promo_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('gacha_categories', function (Blueprint $table) {
            $table->dropColumn(['promo_starts_at', 'promo_ends_at']);
        });
    }
};