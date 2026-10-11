<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_packs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('draw_transaction_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('pack_no')->unique();

            $table->unsignedInteger('pack_count')->default(1);

            $table->string('status')->default('unopened'); // unopened | opened

            $table->timestamp('opened_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_packs');
    }
};
