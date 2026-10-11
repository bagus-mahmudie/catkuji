<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('draw_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gacha_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gacha_event_prize_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gacha_prize_id')->constrained()->cascadeOnDelete();

            $table->string('rarity_code');
            $table->unsignedInteger('result_position');

            $table->string('prize_name_snapshot');
            $table->string('prize_image_snapshot')->nullable();

            $table->decimal('market_price_snapshot', 18, 2)->nullable();
            $table->decimal('sellback_price_snapshot', 18, 2)->default(0);

            $table->string('random_seed_snapshot')->nullable();
            $table->json('probability_snapshot_json')->nullable();

            $table->timestamps();

            $table->index('draw_transaction_id');
            $table->index('user_id');
            $table->index('gacha_event_id');
            $table->index('gacha_event_prize_id');
            $table->index('gacha_prize_id');
            $table->index('rarity_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_results');
    }
};
