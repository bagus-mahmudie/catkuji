<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shipment_order_id')
                ->constrained('shipment_orders')
                ->cascadeOnDelete();

            $table->string('status_code')->nullable();
            $table->string('status_label')->nullable();
            $table->text('description')->nullable();
            $table->string('source')->default('system'); // system, biteship_webhook, biteship_sync, admin
            $table->timestamp('event_time')->nullable();
            $table->json('raw_payload_json')->nullable();

            $table->timestamps();

            $table->index(['shipment_order_id', 'event_time']);
            $table->index('status_code');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
    }
};