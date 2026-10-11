<?php

namespace Tests\Feature;

use App\Models\GachaCategory;
use App\Models\GachaEvent;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReadQueryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_keeps_per_category_limit_and_promo_contents_with_two_event_queries(): void
    {
        foreach (['standard', 'standard', 'promo'] as $index => $style) {
            $category = GachaCategory::create(['name' => 'Category '.$index, 'slug' => 'category-'.$index, 'card_style' => $style, 'sort_order' => $index]);
            for ($i = 0; $i < 6; $i++) {
                GachaEvent::create([
                    'category_id' => $category->id, 'code' => "E-$index-$i", 'slug' => "event-$index-$i",
                    'name' => "Event $index $i", 'price_per_draw' => 10,
                    'start_at' => now()->subDay(), 'status' => 'published', 'sort_order' => $i,
                ]);
            }
        }
        DB::enableQueryLog();
        $response = $this->get('/');
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "gacha_events"'));
        DB::disableQueryLog();
        $this->assertCount(2, $queries);
        $response->assertInertia(fn (Assert $page) => $page->component('Home')
            ->has('categories', 3)->has('categories.0.events', 4)->has('categories.1.events', 4)
            ->has('categories.2.events', 6)->where('categories.0.events.0.name', 'Event 0 0')
            ->where('categories.1.events.3.name', 'Event 1 3'));
        $this->get('/?search=Event%201%205')->assertInertia(fn (Assert $page) => $page
            ->has('categories', 1)->has('categories.0.events', 1)->where('categories.0.events.0.name', 'Event 1 5'));
    }

    public function test_filtered_topups_equal_filtering_the_unfiltered_response(): void
    {
        $user = User::factory()->create();
        foreach (['paid', 'pending', 'failed', 'expired', 'cancelled', 'custom', 'PAID'] as $i => $status) {
            PaymentTransaction::create([
                'user_id' => $user->id, 'purpose' => 'topup', 'order_no' => 'TOPUP-'.$i,
                'gross_amount' => 10000, 'status' => $status, 'created_at' => now()->subMinutes($i),
            ]);
        }
        $all = $this->actingAs($user)->get('/transactions')->viewData('page')['props']['transactions'];
        foreach (['success', 'pending', 'failed', 'expired', 'cancelled', 'other', 'missing'] as $status) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $filtered = $this->get('/transactions?service=topup&status='.$status)->viewData('page')['props']['transactions'];
            $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
            DB::disableQueryLog();
            $this->assertSame(collect($all)->where('status_key', $status)->values()->all(), collect($filtered)->all());
            $this->assertStringNotContainsString('from "shipment_orders"', $queries);
            $this->assertStringNotContainsString('from "draw_transactions"', $queries);
            $this->assertStringNotContainsString('from "sellback_transactions"', $queries);
        }
    }

    public function test_expired_promos_are_hidden_without_writes_or_duplicate_category_queries(): void
    {
        GachaCategory::create(['name' => 'Expired', 'slug' => 'expired', 'card_style' => 'promo', 'promo_ends_at' => now()->subMinute()]);
        GachaCategory::create(['name' => 'Visible', 'slug' => 'visible', 'card_style' => 'standard']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('categories', 1)->has('allCategories', 1)->where('categories.0.slug', 'visible'));
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        $this->assertSame(1, $queries->filter(fn ($sql) => str_contains($sql, 'from "gacha_categories"'))->count());
        $this->assertFalse($queries->contains(fn ($sql) => str_starts_with(strtolower($sql), 'update ')));
        $this->assertDatabaseHas('gacha_categories', ['slug' => 'expired', 'is_archived' => false]);
        GachaCategory::archiveExpiredPromos();
        $this->assertDatabaseHas('gacha_categories', ['slug' => 'expired', 'is_archived' => true]);
    }

    public function test_history_batches_keep_every_row_with_equal_timestamps(): void
    {
        $user = User::factory()->create();
        $time = now()->toDateTimeString();
        $rows = [];
        for ($i = 0; $i < 401; $i++) {
            $rows[] = ['user_id' => $user->id, 'order_no' => 'BATCH-'.$i, 'purpose' => 'topup', 'gross_amount' => 10000, 'status' => 'paid', 'created_at' => $time, 'updated_at' => $time];
        }
        PaymentTransaction::insert($rows);
        $transactions = $this->actingAs($user)->get('/transactions?service=topup')->viewData('page')['props']['transactions'];
        $this->assertCount(401, $transactions);
        $this->assertCount(401, collect($transactions)->pluck('id')->unique());
        $this->assertSame('BATCH-400', collect($transactions)->first()['order_no']);
        $this->assertSame('BATCH-0', collect($transactions)->last()['order_no']);
    }

    public function test_history_query_plan_uses_user_purpose_order_index(): void
    {
        $plan = DB::select("EXPLAIN QUERY PLAN SELECT id, status FROM payment_transactions WHERE user_id = ? AND purpose = ? AND LOWER(COALESCE(status, '')) IN (?) ORDER BY created_at DESC, id DESC LIMIT 200", [1, 'topup', 'paid']);
        $detail = collect($plan)->pluck('detail')->implode(' ');
        $this->assertStringContainsString('history_payment_user_purpose_created', $detail);
        $this->assertStringNotContainsString('TEMP B-TREE', $detail);
    }
}
