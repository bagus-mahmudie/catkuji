<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('admin_requested_at')->nullable()->after('status');
            $table->timestamp('admin_approved_at')->nullable()->after('admin_requested_at');
            $table->foreignIdFor(User::class, 'admin_approved_by')
                ->nullable()
                ->after('admin_approved_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_approved_by');
            $table->dropColumn([
                'admin_requested_at',
                'admin_approved_at',
            ]);
        });
    }
};