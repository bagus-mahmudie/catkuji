<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_orders', function (Blueprint $table) {
            $table->foreignId('payment_transaction_id')
                ->nullable()
                ->after('address_id')
                ->constrained('payment_transactions')
                ->nullOnDelete();

            $table->string('external_order_id')->nullable()->after('tracking_number');
            $table->string('courier_code')->nullable()->after('external_order_id');
            $table->string('service_code')->nullable()->after('courier_code');
            $table->string('tracking_status')->nullable()->after('service_code');

            $table->json('external_response_json')->nullable()->after('tracking_status');

            $table->timestamp('paid_at')->nullable()->after('requested_at');
            $table->timestamp('booking_failed_at')->nullable()->after('paid_at');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');

            $table->index('payment_transaction_id');
            $table->index('external_order_id');
            $table->index('courier_code');
            $table->index('tracking_status');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_transaction_id');

            $table->dropIndex(['external_order_id']);
            $table->dropIndex(['courier_code']);
            $table->dropIndex(['tracking_status']);

            $table->dropColumn([
                'external_order_id',
                'courier_code',
                'service_code',
                'tracking_status',
                'external_response_json',
                'paid_at',
                'booking_failed_at',
                'cancelled_at',
            ]);
        });
    }
};