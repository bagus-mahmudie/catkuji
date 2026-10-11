<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('purpose')->nullable()->after('user_id'); // topup, shipping
            $table->string('reference_type')->nullable()->after('status');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->json('meta_json')->nullable()->after('settlement_payload_json');

            $table->index('purpose');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex(['reference_type', 'reference_id']);
            $table->dropIndex(['purpose']);

            $table->dropColumn([
                'purpose',
                'reference_type',
                'reference_id',
                'meta_json',
            ]);
        });
    }
};