<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gacha_events', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('banner_image')->nullable();
            $table->string('thumbnail_image')->nullable();

            $table->decimal('price_per_draw', 18, 2);
            $table->decimal('price_per_multi_draw', 18, 2)->nullable();

            $table->string('draw_mode')->default('single'); // single, multi, both

            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();

            $table->string('status')->default('draft'); // draft, published, ended, hidden

            $table->boolean('sellback_enabled')->default(true);
            $table->boolean('shipping_enabled')->default(true);

            $table->unsignedInteger('max_draw_per_user_per_day')->nullable();
            $table->integer('sort_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index(['start_at', 'end_at']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gacha_events');
    }
};