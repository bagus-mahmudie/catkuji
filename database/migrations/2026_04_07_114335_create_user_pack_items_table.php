<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_pack_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_pack_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('draw_result_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('gacha_prize_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('prize_name_snapshot');
            $table->string('prize_image_snapshot')->nullable();

            $table->string('rarity_code')->nullable();

            $table->decimal('sellback_price', 12, 2)->default(0);

            $table->string('prize_type')->default('physical');

            $table->timestamp('revealed_at')->nullable();

            $table->timestamps();

            $table->index(['user_pack_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pack_items');
    }
};