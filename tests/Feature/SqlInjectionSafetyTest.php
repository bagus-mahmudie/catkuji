<?php

namespace Tests\Feature;

use App\Models\GachaCategory;
use App\Models\GachaEvent;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqlInjectionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = "' OR 1=1 --";

    public function test_home_search_payload_stays_in_bindings(): void
    {
        $category = GachaCategory::create(['name' => 'Test', 'slug' => 'test', 'card_style' => 'standard']);
        GachaEvent::create(['category_id' => $category->id, 'name' => 'Normal event', 'slug' => 'normal', 'code' => 'NORMAL', 'price_per_draw' => 10, 'start_at' => now()->subDay(), 'status' => 'published']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get('/?'.http_build_query(['search' => self::PAYLOAD]));
        $response->assertOk();
        $this->assertCount(0, $response->viewData('page')['props']['categories']);
        $this->assertBoundPayload('%'.self::PAYLOAD.'%');
        $this->assertDatabaseCount('gacha_events', 1);
    }

    public function test_raw_status_filter_cannot_expand_transaction_results(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$owner, $other] as $user) {
            PaymentTransaction::create(['user_id' => $user->id, 'order_no' => 'TEST-'.$user->id, 'purpose' => 'topup', 'gross_amount' => 10000, 'status' => 'paid']);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        // Purchase uses whereRaw with a bound status; topup maps unknown status to an empty allowlist.
        $response = $this->actingAs($owner)->get('/transactions?'.http_build_query(['service' => 'purchase', 'status' => self::PAYLOAD]));
        $response->assertOk();
        $this->assertCount(0, $response->viewData('page')['props']['transactions']);
        $this->assertBoundPayload(strtolower(self::PAYLOAD));
        $normal = $this->get('/transactions?service=topup')->viewData('page')['props']['transactions'];
        $this->assertCount(1, $normal);
        $this->assertSame('TEST-'.$owner->id, collect($normal)->first()['order_no']);
        $this->assertDatabaseCount('payment_transactions', 2);
    }

    public function test_admin_search_is_bound_and_sort_input_is_not_sql(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($admin)->get('/admin/products?'.http_build_query([
            'search' => self::PAYLOAD, 'sort' => 'name DESC, (SELECT 1)',
        ]))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        $this->assertStringNotContainsString('name DESC, (SELECT 1)', $queries);
        $this->assertBoundPayload('%'.self::PAYLOAD.'%');
    }

    private function assertBoundPayload(string $value): void
    {
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertTrue($queries->contains(fn ($query) => in_array($value, $query['bindings'], true)));
        foreach ($queries as $query) {
            $this->assertStringNotContainsString(self::PAYLOAD, $query['query']);
            $this->assertStringNotContainsString(strtolower(self::PAYLOAD), $query['query']);
        }
    }
}
