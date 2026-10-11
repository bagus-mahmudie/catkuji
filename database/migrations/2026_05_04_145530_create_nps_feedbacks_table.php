<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nps_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gacha_event_id')->nullable()->constrained('gacha_events')->nullOnDelete();
            $table->unsignedTinyInteger('score');
            $table->string('type'); // detractor, passive, promoter
            $table->text('feedback')->nullable();
            $table->string('page_url')->nullable();
            $table->boolean('agree_terms')->default(false);
            $table->timestamps();

            $table->unique(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nps_feedbacks');
    }
};
