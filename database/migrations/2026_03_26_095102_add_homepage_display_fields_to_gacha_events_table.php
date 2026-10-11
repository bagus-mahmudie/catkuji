<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gacha_events', function (Blueprint $table) {
            $table->string('badge_label')->nullable()->after('thumbnail_image');
            $table->string('opened_text')->nullable()->after('badge_label');
            $table->unsignedInteger('stock_left')->nullable()->after('opened_text');
            $table->unsignedInteger('stock_total')->nullable()->after('stock_left');
        });
    }

    public function down(): void
    {
        Schema::table('gacha_events', function (Blueprint $table) {
            $table->dropColumn([
                'badge_label',
                'opened_text',
                'stock_left',
                'stock_total',
            ]);
        });
    }
};