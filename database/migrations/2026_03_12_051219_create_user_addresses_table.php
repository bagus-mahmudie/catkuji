<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->string('province');
            $table->string('city');
            $table->string('district');
            $table->string('subdistrict')->nullable();
            $table->string('postal_code', 20);
            $table->text('full_address');
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};