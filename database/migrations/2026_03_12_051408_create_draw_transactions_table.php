<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('draw_no')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gacha_event_id')->constrained()->cascadeOnDelete();

            $table->string('payment_source')->default('wallet');
            $table->decimal('total_cost', 18, 2);
            $table->unsignedInteger('draw_count')->default(1);

            $table->string('status')->default('success'); // success, failed, rolled_back

            $table->string('request_idempotency_key')->nullable()->unique();

            $table->unique(
                ['user_id', 'gacha_event_id', 'request_idempotency_key'],
                'draw_user_event_idempotency_unique'
            );

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('gacha_event_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_transactions');
    }
};