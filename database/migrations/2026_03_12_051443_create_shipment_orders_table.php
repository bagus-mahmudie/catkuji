<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_no')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->constrained('user_addresses')->restrictOnDelete();

            $table->string('recipient_name_snapshot');
            $table->string('recipient_phone_snapshot');
            $table->json('address_snapshot_json');

            $table->decimal('shipping_cost', 18, 2)->default(0);

            $table->string('courier_name')->nullable();
            $table->string('service_name')->nullable();
            $table->string('tracking_number')->nullable();

            $table->string('status')->default('requested'); // requested, processing, packed, shipped, delivered, returned, cancelled

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('address_id');
            $table->index('status');
            $table->index('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_orders');
    }
};