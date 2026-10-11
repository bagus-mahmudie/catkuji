<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gacha_categories', function (Blueprint $table) {
            $table->string('card_style')->default('standard')->after('description');
        });

        DB::table('gacha_categories')
            ->whereIn('slug', ['promo-spesial', 'gaming'])
            ->update(['card_style' => 'promo']);

        DB::table('gacha_categories')
            ->where('slug', 'gadget')
            ->update(['card_style' => 'standard']);
    }

    public function down(): void
    {
        Schema::table('gacha_categories', function (Blueprint $table) {
            $table->dropColumn('card_style');
        });
    }
};