<?php

namespace App\Actions\Payment;

use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Midtrans\MidtransService;
use App\Services\Support\OrderNumberService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTopupTransactionAction
{
    public function __construct(
        protected OrderNumberService $orderNumberService,
        protected MidtransService $midtransService,
    ) {
    }

    public function execute(User $user, float|int|string $amount): PaymentTransaction
    {
        $amount = (int) $amount;

        $package = collect(config('gacha.topup_packages', []))
            ->firstWhere('amount', $amount);

        if (! $package) {
            throw new InvalidArgumentException('Paket top up tidak tersedia.');
        }

        $coinAmount = (int) $package['coin'];

        if ($coinAmount <= 0) {
            throw new InvalidArgumentException('Jumlah coin top up tidak valid.');
        }

        return DB::transaction(function () use ($user, $amount, $package, $coinAmount) {
            $orderNo = $this->orderNumberService->topupOrderNo();

            $payment = PaymentTransaction::create([
                'user_id' => $user->id,
                'purpose' => 'topup',
                'order_no' => $orderNo,
                'midtrans_order_id' => $orderNo,
                'gross_amount' => $amount,
                'currency' => 'IDR',
                'status' => 'pending',
                'reference_type' => null,
                'reference_id' => null,
                'meta_json' => [
                    'topup_package' => $package,
                    'coin_amount' => $coinAmount,
                    'coin_rate' => config('gacha.coin_rate', 1000),
                ],
            ]);

            $walletUrl = route('wallet.index');

            $payload = [
                'transaction_details' => [
                    'order_id' => $payment->midtrans_order_id,
                    'gross_amount' => $amount,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'item_details' => [
                    [
                        'id' => 'CAT-COIN-' . $coinAmount,
                        'price' => $amount,
                        'quantity' => 1,
                        'name' => $coinAmount . ' Cat Koin',
                    ],
                ],
                'callbacks' => [
                    'finish' => $walletUrl,
                    'unfinish' => $walletUrl,
                    'error' => $walletUrl,
                ],
                'custom_field1' => (string) $user->id,
                'custom_field2' => 'wallet_topup',
                'custom_field3' => (string) $coinAmount,
            ];

            $response = $this->midtransService->createSnapTransaction($payload);

            $payment->update([
                'snap_token' => $response->token ?? null,
                'snap_redirect_url' => $response->redirect_url ?? null,
                'raw_request_json' => $payload,
                'raw_response_json' => (array) $response,
            ]);

            return $payment->fresh();
        });
    }
}