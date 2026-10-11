<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gacha_event_prizes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gacha_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gacha_prize_id')->constrained()->cascadeOnDelete();

            $table->string('display_name')->nullable();
            $table->string('rarity_code');
            $table->unsignedBigInteger('draw_weight');

            $table->unsignedInteger('initial_stock');
            $table->unsignedInteger('current_stock');
            $table->unsignedInteger('reserved_stock')->default(0);

            $table->decimal('sellback_price_override', 18, 2)->nullable();

            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['gacha_event_id', 'gacha_prize_id']);
            $table->index('gacha_event_id');
            $table->index('rarity_code');
            $table->index('current_stock');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gacha_event_prizes');
    }
};