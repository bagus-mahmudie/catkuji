<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gacha_event_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gacha_event_id')->constrained()->cascadeOnDelete();

            $table->string('rule_type'); // weighted_random, guarantee, pity_system
            $table->json('config_json');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['gacha_event_id', 'rule_type']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gacha_event_rules');
    }
};