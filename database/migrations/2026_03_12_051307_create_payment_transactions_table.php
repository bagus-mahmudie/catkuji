<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('order_no')->unique();
            $table->string('midtrans_order_id')->nullable()->unique();

            $table->string('payment_type')->nullable();
            $table->decimal('gross_amount', 18, 2);
            $table->string('currency', 10)->default('IDR');

            $table->string('transaction_status')->nullable();
            $table->string('fraud_status')->nullable();

            $table->string('snap_token')->nullable();
            $table->text('snap_redirect_url')->nullable();

            $table->string('status')->default('pending'); // pending, paid, failed, expired, cancelled

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->json('raw_request_json')->nullable();
            $table->json('raw_response_json')->nullable();
            $table->json('settlement_payload_json')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('transaction_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};