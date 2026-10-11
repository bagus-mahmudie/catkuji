<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellback_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->unique()->constrained('user_inventories')->cascadeOnDelete();
            $table->foreignId('wallet_transaction_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 18, 2);
            $table->string('status')->default('completed');
            $table->json('meta_json')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellback_transactions');
    }
};