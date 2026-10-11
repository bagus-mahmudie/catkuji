<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draw_results', function (Blueprint $table) {
            if (! Schema::hasColumn('draw_results', 'token_reward_snapshot')) {
                $table->unsignedBigInteger('token_reward_snapshot')
                    ->default(0)
                    ->after('sellback_price_snapshot');
            }
        });

        Schema::table('user_pack_items', function (Blueprint $table) {
            if (! Schema::hasColumn('user_pack_items', 'token_reward')) {
                $table->unsignedBigInteger('token_reward')
                    ->default(0)
                    ->after('sellback_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_pack_items', function (Blueprint $table) {
            if (Schema::hasColumn('user_pack_items', 'token_reward')) {
                $table->dropColumn('token_reward');
            }
        });

        Schema::table('draw_results', function (Blueprint $table) {
            if (Schema::hasColumn('draw_results', 'token_reward_snapshot')) {
                $table->dropColumn('token_reward_snapshot');
            }
        });
    }
};