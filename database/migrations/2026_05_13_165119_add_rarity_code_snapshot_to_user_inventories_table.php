<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_inventories', function (Blueprint $table) {
            $table->string('rarity_code_snapshot')->nullable()->after('prize_image_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('user_inventories', function (Blueprint $table) {
            $table->dropColumn('rarity_code_snapshot');
        });
    }
};