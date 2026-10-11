<?php

namespace Tests\Feature;

use App\Actions\Payment\HandleMidtransCallbackAction;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_and_out_of_order_callbacks_credit_only_once(): void
    {
        $user = User::factory()->create();
        $payment = PaymentTransaction::create([
            'user_id' => $user->id, 'order_no' => 'TEST-1', 'purpose' => 'topup',
            'gross_amount' => 10000, 'status' => 'pending', 'meta_json' => ['coin_amount' => 10],
        ]);
        $action = app(HandleMidtransCallbackAction::class);
        $payload = ['order_id' => 'TEST-1', 'gross_amount' => '10000.00', 'transaction_status' => 'settlement'];
        $action->execute($payload, false);
        $action->execute($payload, false);
        $action->execute([...$payload, 'transaction_status' => 'pending'], false);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertEquals(10, $user->wallet()->first()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_missing_zero_and_mismatched_amounts_are_rejected(): void
    {
        $user = User::factory()->create();
        $payment = PaymentTransaction::create([
            'user_id' => $user->id, 'order_no' => 'TEST-2', 'purpose' => 'topup',
            'gross_amount' => 10000, 'status' => 'pending',
        ]);
        foreach ([null, 0, 1] as $amount) {
            try {
                app(HandleMidtransCallbackAction::class)->execute([
                    'order_id' => 'TEST-2', 'gross_amount' => $amount, 'transaction_status' => 'settlement',
                ], false);
                $this->fail('Invalid payment amount accepted');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Midtrans gross amount mismatch.', $exception->getMessage());
            }
        }
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_capture_with_rejected_fraud_status_does_not_credit_wallet(): void
    {
        $user = User::factory()->create();
        $payment = PaymentTransaction::create([
            'user_id' => $user->id, 'order_no' => 'TEST-3', 'purpose' => 'topup',
            'gross_amount' => 10000, 'status' => 'pending',
        ]);
        app(HandleMidtransCallbackAction::class)->execute([
            'order_id' => 'TEST-3', 'gross_amount' => 10000,
            'transaction_status' => 'capture', 'fraud_status' => 'deny',
        ], false);
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
