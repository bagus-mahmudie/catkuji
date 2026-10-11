<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        /*
         * SQLite dipakai oleh php artisan test.
         * SQLite tidak mendukung ALTER TABLE ... MODIFY.
         *
         * Karena migration awal di test database dibuat fresh dari awal,
         * kita tidak perlu paksa MODIFY di SQLite.
         */
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE wallets MODIFY balance BIGINT UNSIGNED NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE wallets MODIFY locked_balance BIGINT UNSIGNED NOT NULL DEFAULT 0');

            DB::statement('ALTER TABLE wallet_transactions MODIFY amount BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE wallet_transactions MODIFY balance_before BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE wallet_transactions MODIFY balance_after BIGINT UNSIGNED NOT NULL');

            DB::statement('ALTER TABLE gacha_events MODIFY price_per_draw BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE gacha_events MODIFY price_per_multi_draw BIGINT UNSIGNED NULL');
        }

        DB::table('wallets')->update([
            'currency' => 'COIN',
        ]);
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE wallets MODIFY balance DECIMAL(18,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE wallets MODIFY locked_balance DECIMAL(18,2) NOT NULL DEFAULT 0');

            DB::statement('ALTER TABLE wallet_transactions MODIFY amount DECIMAL(18,2) NOT NULL');
            DB::statement('ALTER TABLE wallet_transactions MODIFY balance_before DECIMAL(18,2) NOT NULL');
            DB::statement('ALTER TABLE wallet_transactions MODIFY balance_after DECIMAL(18,2) NOT NULL');

            DB::statement('ALTER TABLE gacha_events MODIFY price_per_draw DECIMAL(18,2) NOT NULL');
            DB::statement('ALTER TABLE gacha_events MODIFY price_per_multi_draw DECIMAL(18,2) NULL');
        }

        DB::table('wallets')->update([
            'currency' => 'IDR',
        ]);
    }
};