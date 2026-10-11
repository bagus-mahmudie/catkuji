<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shipment_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->unique()->constrained('user_inventories')->cascadeOnDelete();

            $table->string('prize_name_snapshot');
            $table->unsignedInteger('qty')->default(1);

            $table->timestamps();

            $table->index('shipment_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_order_items');
    }
};