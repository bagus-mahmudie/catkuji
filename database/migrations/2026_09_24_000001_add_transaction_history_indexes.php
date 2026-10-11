<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'payment_transactions' => ['history_payment_user_purpose_created', ['user_id', 'purpose', 'created_at', 'id']],
        'shipment_orders' => ['history_shipment_user_created', ['user_id', 'created_at', 'id']],
        'draw_transactions' => ['history_draw_user_created', ['user_id', 'created_at', 'id']],
        'sellback_transactions' => ['history_sellback_user_created', ['user_id', 'created_at', 'id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => [$name, $columns]) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => [$name]) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
        }
    }
};
