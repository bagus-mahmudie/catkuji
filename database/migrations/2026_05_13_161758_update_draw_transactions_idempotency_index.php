<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('draw_transactions')) {
            return;
        }

        $indexExists = Schema::hasIndex('draw_transactions', 'draw_user_event_idempotency_unique');

        if (! $indexExists) {
            Schema::table('draw_transactions', function (Blueprint $table) {
                $table->unique(
                    ['user_id', 'gacha_event_id', 'request_idempotency_key'],
                    'draw_user_event_idempotency_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('draw_transactions')) {
            return;
        }

        $indexExists = Schema::hasIndex('draw_transactions', 'draw_user_event_idempotency_unique');

        if ($indexExists) {
            Schema::table('draw_transactions', function (Blueprint $table) {
                $table->dropUnique('draw_user_event_idempotency_unique');
            });
        }
    }
};