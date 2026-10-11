<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gacha_prizes', function (Blueprint $table) {
            $table->unsignedInteger('weight_grams')->nullable()->after('sellback_price');
            $table->unsignedInteger('length_cm')->nullable()->after('weight_grams');
            $table->unsignedInteger('width_cm')->nullable()->after('length_cm');
            $table->unsignedInteger('height_cm')->nullable()->after('width_cm');
        });
    }

    public function down(): void
    {
        Schema::table('gacha_prizes', function (Blueprint $table) {
            $table->dropColumn([
                'weight_grams',
                'length_cm',
                'width_cm',
                'height_cm',
            ]);
        });
    }
};