<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_inventories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draw_result_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('gacha_prize_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_event_id')->constrained('gacha_events')->cascadeOnDelete();

            $table->string('prize_name_snapshot');
            $table->string('prize_image_snapshot')->nullable();
            $table->string('prize_type');
            $table->decimal('sellback_price', 18, 2)->default(0);

            $table->string('status')->default('stored');

            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sellbacked_at')->nullable();
            $table->timestamp('shipping_requested_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('source_event_id');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_inventories');
    }
};