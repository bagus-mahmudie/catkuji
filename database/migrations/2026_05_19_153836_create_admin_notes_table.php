<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('csat_feedback_id')
                ->nullable()
                ->constrained('csat_feedbacks')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('note_date')->nullable();
            $table->timestamps();

            $table->index(['note_date', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notes');
    }
};