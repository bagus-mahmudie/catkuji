<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gacha_prizes', function (Blueprint $table) {
            $table->id();

            $table->string('sku')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->string('image')->nullable();
            $table->string('category')->nullable();

            $table->string('prize_type')->default('physical'); // physical, digital, voucher
            $table->string('rarity_code'); // C, R, SR, SSR, UR

            $table->decimal('market_price', 18, 2)->nullable();
            $table->decimal('sellback_price', 18, 2)->default(0);

            $table->json('metadata_json')->nullable();

            $table->string('status')->default('active');

            $table->timestamps();

            $table->index('rarity_code');
            $table->index('prize_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gacha_prizes');
    }
};