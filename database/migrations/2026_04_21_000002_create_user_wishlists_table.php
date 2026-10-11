<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gacha_event_id')->constrained('gacha_events')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'gacha_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_wishlists');
    }
};