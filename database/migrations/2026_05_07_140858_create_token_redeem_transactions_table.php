<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_redeem_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('redeem_no')->unique();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('gacha_event_prize_id')
                ->constrained('gacha_event_prizes')
                ->cascadeOnDelete();

            $table->foreignId('gacha_event_id')
                ->constrained('gacha_events')
                ->cascadeOnDelete();

            $table->foreignId('gacha_prize_id')
                ->constrained('gacha_prizes')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('token_cost');

            $table->string('status')->default('success');
            // success, cancelled, refunded

            $table->string('prize_name_snapshot');
            $table->string('prize_image_snapshot')->nullable();
            $table->string('prize_type')->default('physical');
            $table->decimal('sellback_price_snapshot', 18, 2)->default(0);

            $table->json('meta_json')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('gacha_event_prize_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_redeem_transactions');
    }
};