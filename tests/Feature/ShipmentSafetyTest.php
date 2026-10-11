<?php

namespace Tests\Feature;

use App\Actions\Shipment\BookBiteshipOrderAction;
use App\Actions\Shipment\SyncShipmentStatusAction;
use App\Exceptions\ShipmentBookingPendingException;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\Shipping\BiteshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShipmentSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['origin_contact_name', 'origin_contact_phone', 'origin_contact_email', 'origin_address', 'origin_postal_code'] as $key) {
            config(['services.biteship.'.$key => 'test']);
        }
    }

    public function test_overlapping_and_repeated_booking_calls_send_only_one_request(): void
    {
        $shipment = $this->shipment();
        $service = \Mockery::mock(BiteshipService::class);
        $this->app->instance(BiteshipService::class, $service);
        $service->shouldReceive('createOrder')->once()->andReturnUsing(function ($payload) use ($shipment) {
            $this->assertSame($shipment->order_no, $payload['reference_id']);
            $this->assertTrue($shipment->fresh()->hasUnresolvedBooking());
            try {
                app(BookBiteshipOrderAction::class)->execute($shipment);
                $this->fail('Overlapping booking accepted');
            } catch (ShipmentBookingPendingException) {
                // The second execution observes the persisted claim before the first returns.
            }

            return ['success' => true, 'id' => 'external-1', 'status' => 'confirmed', 'courier' => ['waybill_id' => 'AWB-1']];
        });
        $action = app(BookBiteshipOrderAction::class);
        $action->execute($shipment);
        $action->execute($shipment);
        $this->assertSame('external-1', $shipment->fresh()->external_order_id);
        $this->assertSame('AWB-1', $shipment->fresh()->tracking_number);
    }

    public function test_timeout_blocks_retry_and_local_cancellation(): void
    {
        $shipment = $this->shipment();
        $service = \Mockery::mock(BiteshipService::class);
        $service->shouldReceive('createOrder')->once()->andThrow(new \RuntimeException('Timeout'));
        $this->app->instance(BiteshipService::class, $service);
        for ($i = 0; $i < 2; $i++) {
            try {
                app(BookBiteshipOrderAction::class)->execute($shipment);
                $this->fail('Expected unresolved booking');
            } catch (ShipmentBookingPendingException) {
                $this->assertTrue($shipment->fresh()->hasUnresolvedBooking());
            }
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/shipments/'.$shipment->order_no.'/cancel')
            ->assertSessionHas('error', 'Booking kurir sudah dibuat atau belum pasti. Rekonsiliasi dengan Biteship sebelum pembatalan.');
        $this->assertSame('paid', $shipment->fresh()->status);
    }

    public function test_reference_id_alone_is_not_treated_as_provider_order_id(): void
    {
        $shipment = $this->shipment();
        $service = \Mockery::mock(BiteshipService::class);
        $service->shouldReceive('createOrder')->once()->andReturn(['reference_id' => $shipment->order_no]);
        $this->app->instance(BiteshipService::class, $service);
        try {
            app(BookBiteshipOrderAction::class)->execute($shipment);
            $this->fail('Invalid response accepted');
        } catch (ShipmentBookingPendingException) {
            $this->assertNull($shipment->fresh()->external_order_id);
            $this->assertTrue($shipment->fresh()->hasUnresolvedBooking());
        }
    }

    public function test_cancelled_order_cannot_be_booked(): void
    {
        $shipment = $this->shipment();
        $shipment->update(['status' => 'cancelled']);
        $service = \Mockery::mock(BiteshipService::class);
        $service->shouldNotReceive('createOrder');
        $this->app->instance(BiteshipService::class, $service);
        $this->expectException(\RuntimeException::class);
        app(BookBiteshipOrderAction::class)->execute($shipment);
    }

    public function test_reconciliation_checks_provider_reference_before_attaching(): void
    {
        $shipment = $this->shipment();
        $shipment->update(['external_response_json' => ['booking_attempt' => ['started_at' => now()->toIso8601String()]]]);
        $service = \Mockery::mock(BiteshipService::class);
        $this->app->instance(BiteshipService::class, $service);
        $service->shouldReceive('getOrder')->with('wrong')->once()->andReturn([
            'success' => true, 'id' => 'wrong', 'reference_id' => 'other-order',
        ]);
        $this->artisan('shipments:reconcile-booking', ['order_no' => $shipment->order_no, 'external_order_id' => 'wrong'])->assertFailed();
        $this->assertNull($shipment->fresh()->external_order_id);
        $service->shouldReceive('getOrder')->with('correct')->once()->andReturn([
            'success' => true, 'id' => 'correct', 'reference_id' => $shipment->order_no,
            'status' => 'confirmed', 'courier' => ['waybill_id' => 'AWB-2'],
        ]);
        $this->artisan('shipments:reconcile-booking', ['order_no' => $shipment->order_no, 'external_order_id' => 'correct'])->assertSuccessful();
        $this->assertSame('correct', $shipment->fresh()->external_order_id);
        $this->assertSame('processing', $shipment->fresh()->status);
        $this->assertFalse($shipment->fresh()->hasUnresolvedBooking());
    }

    public function test_late_notifications_do_not_regress_shipment_status(): void
    {
        $shipment = $this->shipment();
        $sync = app(SyncShipmentStatusAction::class);
        $sync->execute($shipment, 'shipped');
        $sync->execute($shipment, 'paid');
        $this->assertSame('shipped', $shipment->fresh()->status);
        $sync->execute($shipment, 'delivered');
        $sync->execute($shipment, 'processing');
        $sync->execute($shipment, 'failed');
        $this->assertSame('delivered', $shipment->fresh()->status);
        $this->assertDatabaseCount('shipment_tracking_events', 2);
    }

    public function test_csat_can_only_reference_own_shipment(): void
    {
        $shipment = $this->shipment();
        $otherUser = User::factory()->create();
        $payload = ['shipment_order_id' => $shipment->id, 'rating' => 5, 'agree_terms' => true];
        $this->actingAs($otherUser)->postJson('/csat-feedback', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('shipment_order_id');
        $this->assertDatabaseCount('csat_feedbacks', 0);
        $this->actingAs($shipment->user)->postJson('/csat-feedback', $payload)->assertOk();
        $this->assertDatabaseHas('csat_feedbacks', ['user_id' => $shipment->user_id, 'shipment_order_id' => $shipment->id]);
    }

    public function test_conflicting_webhook_identifiers_do_not_update_either_order(): void
    {
        config(['services.biteship.webhook_secret' => 'test-secret']);
        $first = $this->shipment();
        $second = $this->shipment();
        $first->update(['external_order_id' => 'first-external']);
        $second->update(['external_order_id' => 'second-external']);
        $this->withHeader('X-Biteship-Webhook-Secret', 'test-secret')
            ->postJson('/shipping/biteship/webhook', [
                'reference_id' => $first->order_no, 'id' => 'second-external', 'status' => 'delivered',
            ])->assertUnprocessable();
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame('paid', $second->fresh()->status);
        $this->assertDatabaseCount('shipment_tracking_events', 0);
    }

    public function test_repeated_webhook_uses_latest_event_without_duplicate_tracking(): void
    {
        config(['services.biteship.webhook_secret' => 'test-secret']);
        $shipment = $this->shipment();
        $shipment->update(['external_order_id' => 'external']);
        foreach (['processing', 'shipped', 'shipped'] as $status) {
            $this->withHeader('X-Biteship-Webhook-Secret', 'test-secret')
                ->postJson('/shipping/biteship/webhook', [
                    'reference_id' => $shipment->order_no, 'id' => 'external', 'status' => $status,
                ])->assertOk();
        }
        $this->assertDatabaseCount('shipment_tracking_events', 2);
    }

    public function test_sync_batches_do_not_skip_orders_as_their_status_changes(): void
    {
        $first = $this->shipment();
        $first->update(['external_order_id' => 'external-0']);
        for ($i = 1; $i <= 100; $i++) {
            $copy = $first->replicate();
            $copy->order_no = 'BATCH-'.$i;
            $copy->external_order_id = 'external-'.$i;
            $copy->save();
        }
        $service = \Mockery::mock(BiteshipService::class);
        $service->shouldReceive('getOrder')->times(101)->andReturn([
            'status' => 'delivered', 'courier' => ['waybill_id' => 'AWB'],
        ]);
        $this->app->instance(BiteshipService::class, $service);
        $this->artisan('shipments:sync-pending')->assertSuccessful();
        $this->assertSame(101, ShipmentOrder::where('status', 'delivered')->where('tracking_number', 'AWB')->count());
    }

    public function test_shipment_status_filters_preserve_existing_response(): void
    {
        $first = $this->shipment();
        foreach (['requested', 'pending_payment', 'processing', 'packed', 'shipped', 'delivered', 'booking_failed', 'failed', 'cancelled', 'custom'] as $i => $status) {
            $copy = $first->replicate();
            $copy->order_no = 'FILTER-'.$i;
            $copy->status = $status;
            $copy->save();
        }
        $all = $this->actingAs($first->user)->get('/transactions')->viewData('page')['props']['transactions'];
        foreach (['pending', 'processing', 'shipped', 'delivered', 'failed', 'cancelled', 'other', 'unknown'] as $status) {
            $filtered = $this->get('/transactions?service=shipping&status='.$status)->viewData('page')['props']['transactions'];
            $this->assertEquals(collect($all)->where('status_key', $status)->values()->all(), collect($filtered)->all());
        }
    }

    public function test_sync_reports_failure_without_aborting_remaining_orders(): void
    {
        $first = $this->shipment();
        $first->update(['external_order_id' => 'failed-order']);
        $second = $this->shipment();
        $second->update(['external_order_id' => 'good-order']);
        $service = \Mockery::mock(BiteshipService::class);
        $service->shouldReceive('getOrder')->with('failed-order')->once()->andThrow(new \RuntimeException('Timeout'));
        $service->shouldReceive('getOrder')->with('good-order')->once()->andReturn(['status' => 'delivered']);
        $this->app->instance(BiteshipService::class, $service);
        $this->artisan('shipments:sync-pending')->assertFailed();
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame('delivered', $second->fresh()->status);
    }

    public function test_regression_provider_cancellation_is_applied_after_processing(): void
    {
        $order = $this->shipment();
        $order->update(['status' => 'processing']);
        app(SyncShipmentStatusAction::class)->execute($order, 'cancelled');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseCount('shipment_tracking_events', 1);
    }

    public function test_regression_tracking_only_update_is_applied_after_delivery(): void
    {
        $order = $this->shipment();
        $order->update(['status' => 'delivered', 'tracking_number' => 'OLD']);
        app(SyncShipmentStatusAction::class)->execute($order, null, 'CORRECTED');
        $this->assertSame('CORRECTED', $order->fresh()->tracking_number);
    }

    public function test_regression_late_booking_response_preserves_newer_tracking_metadata(): void
    {
        foreach (['origin_contact_name', 'origin_contact_phone', 'origin_contact_email', 'origin_address', 'origin_postal_code'] as $key) {
            config(['services.biteship.'.$key => 'test']);
        }
        $order = $this->shipment();
        $service = \Mockery::mock(BiteshipService::class);
        $this->app->instance(BiteshipService::class, $service);
        $service->shouldReceive('createOrder')->once()->andReturnUsing(function () use ($order) {
            app(SyncShipmentStatusAction::class)->execute($order, 'delivered', 'NEW-AWB', ['status' => 'delivered'], 'biteship_webhook');

            return ['success' => true, 'id' => 'external', 'status' => 'confirmed', 'courier' => ['waybill_id' => 'OLD-AWB']];
        });
        app(BookBiteshipOrderAction::class)->execute($order);
        $fresh = $order->fresh();
        $this->assertSame('delivered', $fresh->status);
        $this->assertSame('delivered', $fresh->tracking_status);
        $this->assertSame('NEW-AWB', $fresh->tracking_number);
        $this->assertSame('delivered', data_get($fresh->external_response_json, 'last_payload.status'));
    }

    public function test_waybill_webhook_updates_terminal_order_without_reopening_it(): void
    {
        config(['services.biteship.webhook_secret' => 'test-secret']);
        $order = $this->shipment();
        $order->update(['external_order_id' => 'external', 'status' => 'delivered', 'tracking_status' => 'delivered']);
        $this->withHeader('X-Biteship-Webhook-Secret', 'test-secret')->postJson('/shipping/biteship/webhook', [
            'order_id' => 'external', 'event' => 'order.waybill_id', 'courier_waybill_id' => 'NEW', 'status' => 'picked',
        ])->assertOk();
        $this->assertSame('NEW', $order->fresh()->tracking_number);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('delivered', $order->fresh()->tracking_status);
    }

    public function test_background_sync_does_not_fetch_unused_full_timeline(): void
    {
        $order = $this->shipment();
        DB::enableQueryLog();
        app(SyncShipmentStatusAction::class)->execute($order, 'processing', loadDetails: false);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        $this->assertFalse($queries->contains(fn ($query) => str_contains($query, 'from "user_addresses"')));
        $this->assertFalse($queries->contains(fn ($query) => str_contains($query, 'from "payment_transactions"')));
        $this->assertFalse($queries->contains(fn ($query) => str_contains($query, 'from "shipment_tracking_events"') && ! str_contains($query, 'limit 1')));
    }

    public function test_failure_does_not_allow_old_paid_notification_to_regress_shipment_progress(): void
    {
        $order = $this->shipment();
        $sync = app(SyncShipmentStatusAction::class);
        $sync->execute($order, 'shipped');
        $sync->execute($order, 'failed');
        $sync->execute($order, 'paid');
        $this->assertSame('booking_failed', $order->fresh()->status);
        $this->assertSame('failed', $order->fresh()->tracking_status);
    }

    public function test_release_claim_requires_operator_evidence_and_exact_attempt(): void
    {
        $order = $this->shipment();
        $order->update(['external_response_json' => ['booking_attempt' => ['id' => 'attempt-1', 'started_at' => now()->toIso8601String(), 'result' => 'unknown']]]);
        $args = ['order_no' => $order->order_no, 'attempt_id' => 'attempt-1'];
        $this->artisan('shipments:release-uncreated', $args)->assertFailed();
        $this->assertTrue($order->fresh()->hasUnresolvedBooking());
        $evidence = ['--confirmed-not-created' => true, '--worker-stopped' => true, '--reason' => 'Provider ticket TEST-123 verified'];
        $this->artisan('shipments:release-uncreated', [...$args, ...$evidence, 'attempt_id' => 'wrong'])->assertFailed();
        $this->artisan('shipments:release-uncreated', [...$args, ...$evidence])->assertSuccessful();
        $this->assertFalse($order->fresh()->hasUnresolvedBooking());
        $this->assertSame('attempt-1', data_get($order->fresh()->external_response_json, 'booking_release_history.0.attempt.id'));
    }

    private function shipment(): ShipmentOrder
    {
        $user = User::factory()->create();
        $address = UserAddress::create([
            'user_id' => $user->id, 'recipient_name' => 'Test', 'recipient_phone' => '08123456789',
            'province' => 'Jakarta', 'city' => 'Jakarta', 'district' => 'Test',
            'postal_code' => '12345', 'full_address' => 'Test address',
        ]);

        return ShipmentOrder::create([
            'user_id' => $user->id, 'address_id' => $address->id, 'order_no' => 'SHIP-'.$user->id,
            'recipient_name_snapshot' => 'Test', 'recipient_phone_snapshot' => '08123456789',
            'address_snapshot_json' => ['full_address' => 'Test address', 'postal_code' => '12345'],
            'courier_code' => 'jne', 'service_code' => 'reg', 'status' => 'paid',
        ]);
    }
}
