<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type');
            // credit, debit

            $table->string('category');
            // draw_reward, redeem_event, admin_adjustment

            $table->unsignedBigInteger('amount');

            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');

            $table->nullableMorphs('reference');

            $table->string('description')->nullable();
            $table->json('meta_json')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'type', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_transactions');
    }
};