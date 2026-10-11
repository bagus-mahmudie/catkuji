<?php

use App\Models\GachaCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gacha_categories', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('sort_order');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
        });

        GachaCategory::query()
            ->whereNull('sort_order')
            ->orWhere('sort_order', '<=', 0)
            ->orderBy('id')
            ->get()
            ->each(function ($category, $index) {
                $category->update([
                    'sort_order' => $index + 1,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('gacha_categories', function (Blueprint $table) {
            $table->dropColumn(['is_archived', 'archived_at']);
        });
    }
};