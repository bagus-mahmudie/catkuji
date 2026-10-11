<?php

namespace App\Actions\Payment;

use App\Actions\Shipment\BookBiteshipOrderAction;
use App\Actions\Shipment\MarkShipmentOrderPaidAction;
use App\Services\Midtrans\MidtransSignatureVerifier;
use App\Actions\Wallet\CreditWalletAction;
use App\Models\PaymentTransaction;
use App\Models\ShipmentOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class HandleMidtransCallbackAction
{
    public function __construct(
        protected CreditWalletAction $creditWalletAction,
        protected MarkShipmentOrderPaidAction $markShipmentOrderPaidAction,
        protected BookBiteshipOrderAction $bookBiteshipOrderAction,
        protected MidtransSignatureVerifier $midtransSignatureVerifier,
    ) {
    }

    public function execute(array $payload, bool $verifySignature = true): ?PaymentTransaction
    {
        $midtransOrderId = (string) data_get($payload, 'order_id');
        $transactionStatus = (string) data_get($payload, 'transaction_status');
        $fraudStatus = (string) data_get($payload, 'fraud_status');
        $paymentType = (string) data_get($payload, 'payment_type');
        $settlementTime = data_get($payload, 'settlement_time');
        $expiryTime = data_get($payload, 'expiry_time');
        $transactionTime = data_get($payload, 'transaction_time');
        $rawStatus = $this->mapMidtransStatus($transactionStatus, $fraudStatus);

        if (! $midtransOrderId) {
            return null;
        }

        if ($verifySignature && ! $this->midtransSignatureVerifier->isValid($payload)) {
            Log::warning('Invalid Midtrans signature received.', [
                'order_id' => $midtransOrderId,
                'transaction_status' => $transactionStatus,
                'status_code' => data_get($payload, 'status_code'),
                'gross_amount' => data_get($payload, 'gross_amount'),
            ]);

            throw new RuntimeException('Invalid Midtrans signature.');
        }

        $payment = PaymentTransaction::query()
            ->where('midtrans_order_id', $midtransOrderId)
            ->orWhere('order_no', $midtransOrderId)
            ->first();

        if (! $payment) {
            return null;
        }

        $payloadGrossAmount = (float) data_get($payload, 'gross_amount', 0);
        $storedGrossAmount = (float) $payment->gross_amount;

        if (! is_numeric(data_get($payload, 'gross_amount')) || $payloadGrossAmount <= 0 || abs($payloadGrossAmount - $storedGrossAmount) > 0.01) {
            Log::warning('Midtrans gross amount mismatch.', [
                'payment_id' => $payment->id,
                'order_no' => $payment->order_no,
                'midtrans_order_id' => $payment->midtrans_order_id,
                'stored_gross_amount' => $storedGrossAmount,
                'payload_gross_amount' => $payloadGrossAmount,
                'purpose' => $payment->purpose,
            ]);

            throw new RuntimeException('Midtrans gross amount mismatch.');
        }

        DB::transaction(function () use (
            $payment,
            $payload,
            $rawStatus,
            $paymentType,
            $settlementTime,
            $expiryTime,
            $transactionTime,
            $transactionStatus,
            $fraudStatus
        ) {
            $payment = PaymentTransaction::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'paid' && $rawStatus !== 'paid') {
                return;
            }

            $existingMeta = is_array($payment->meta_json) ? $payment->meta_json : [];

            $payment->update([
                'status' => $rawStatus,
                'payment_type' => $paymentType ?: $payment->payment_type,
                'gross_amount' => $payment->gross_amount,
                'transaction_status' => $transactionStatus ?: $payment->transaction_status,
                'fraud_status' => $fraudStatus ?: $payment->fraud_status,
                'expired_at' => $expiryTime ?: $payment->expired_at,
                'paid_at' => $rawStatus === 'paid'
                    ? ($payment->paid_at ?? $this->parseDateTime($settlementTime) ?? now())
                    : $payment->paid_at,
                'settlement_payload_json' => $payload,
                'meta_json' => array_merge($existingMeta, [
                    'midtrans_callback' => $payload,
                    'midtrans_transaction_time' => $transactionTime,
                    'midtrans_settlement_time' => $settlementTime,
                ]),
            ]);
        });

        $payment->refresh();

        if ($payment->status !== 'paid') {
            return $payment;
        }

        if ($payment->purpose === 'topup') {
            $this->handleTopupPaid($payment);
            return $payment->fresh();
        }

        if ($payment->purpose === 'shipping') {
            $this->handleShippingPaid($payment);
            return $payment->fresh();
        }

        return $payment;
    }

    protected function handleTopupPaid(PaymentTransaction $payment): void
    {
        $alreadyCredited = (bool) data_get($payment->meta_json, 'wallet_credited', false);

        if ($alreadyCredited) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $freshPayment = PaymentTransaction::query()
                ->lockForUpdate()
                ->with('user')
                ->find($payment->id);

            if (! $freshPayment) {
                throw new RuntimeException('Payment topup tidak ditemukan.');
            }

            $alreadyCredited = (bool) data_get($freshPayment->meta_json, 'wallet_credited', false);

            if ($alreadyCredited) {
                return;
            }

            if (! $freshPayment->user) {
                throw new RuntimeException('User payment topup tidak ditemukan.');
            }

            $coinRate = (int) config('gacha.coin_rate', 1000);

            if ($coinRate <= 0) {
                throw new RuntimeException('Konfigurasi coin rate tidak valid.');
            }

            $grossAmount = (int) $freshPayment->gross_amount;

            $creditedCoins = (int) data_get($freshPayment->meta_json, 'coin_amount', 0);

            if ($creditedCoins <= 0) {
                $creditedCoins = intdiv($grossAmount, $coinRate);
            }

            if ($creditedCoins <= 0) {
                throw new RuntimeException('Jumlah coin top up tidak valid.');
            }

            $this->creditWalletAction->execute(
                user: $freshPayment->user,
                amount: $creditedCoins,
                category: 'topup',
                referenceType: PaymentTransaction::class,
                referenceId: $freshPayment->id,
                description: 'Top up coin dari pembayaran Midtrans',
                meta: [
                    'unit' => 'COIN',
                    'coin_rate' => $coinRate,
                    'gross_amount_rupiah' => $grossAmount,
                    'credited_coins' => $creditedCoins,
                ],
            );

            $meta = is_array($freshPayment->meta_json) ? $freshPayment->meta_json : [];
            $meta['wallet_credited'] = true;
            $meta['credited_unit'] = 'COIN';
            $meta['credited_coins'] = $creditedCoins;
            $meta['coin_rate'] = $coinRate;
            $meta['topup_rupiah'] = $grossAmount;

            $freshPayment->update([
                'meta_json' => $meta,
            ]);
        });
    }

    protected function handleShippingPaid(PaymentTransaction $payment): void
    {
        $shipmentOrder = $this->resolveShipmentOrder($payment);

        if (! $shipmentOrder) {
            Log::warning('Shipping payment paid but shipment order not found.', [
                'payment_id' => $payment->id,
                'order_no' => $payment->order_no,
                'reference_type' => $payment->reference_type,
                'reference_id' => $payment->reference_id,
                'meta_shipment_order_no' => data_get($payment->meta_json, 'shipment_order_no'),
            ]);

            return;
        }

        DB::transaction(function () use ($shipmentOrder) {
            $freshShipment = ShipmentOrder::query()
                ->lockForUpdate()
                ->find($shipmentOrder->id);

            if (! $freshShipment) {
                return;
            }

            $this->markShipmentOrderPaidAction->execute($freshShipment);
        });

        $shipmentOrder->refresh();

        if ($shipmentOrder->external_order_id) {
            return;
        }

        try {
            $this->bookBiteshipOrderAction->execute($shipmentOrder->fresh());
        } catch (\App\Exceptions\ShipmentBookingPendingException $e) {
            Log::warning('Shipment booking requires reconciliation.', [
                'shipment_order_id' => $shipmentOrder->id,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed booking shipment order to Biteship.', [
                'shipment_order_id' => $shipmentOrder->id,
                'shipment_order_no' => $shipmentOrder->order_no,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            // A late failure must not overwrite a webhook or another worker's success.
            DB::transaction(function () use ($shipmentOrder, $e) {
                $shipment = ShipmentOrder::query()->lockForUpdate()->findOrFail($shipmentOrder->id);
                if ($shipment->external_order_id || $shipment->hasUnresolvedBooking()
                    || ! in_array($shipment->status, ['paid', 'booking_failed'], true)) {
                    return;
                }
                $meta = $shipment->external_response_json ?? [];
                $meta['booking_error'] = $e->getMessage();
                $shipment->update([
                    'status' => 'booking_failed',
                    'booking_failed_at' => $shipment->booking_failed_at ?? now(),
                    'external_response_json' => $meta,
                ]);
            });
        }
    }

    protected function resolveShipmentOrder(PaymentTransaction $payment): ?ShipmentOrder
    {
        if ($payment->reference_type === ShipmentOrder::class && (int) $payment->reference_id > 0) {
            return ShipmentOrder::query()->find($payment->reference_id);
        }

        $shipmentOrderNo = data_get($payment->meta_json, 'shipment_order_no');

        if ($shipmentOrderNo) {
            return ShipmentOrder::query()
                ->where('order_no', $shipmentOrderNo)
                ->first();
        }

        return null;
    }

    protected function mapMidtransStatus(string $transactionStatus, string $fraudStatus = ''): string
    {
        return match ($transactionStatus) {
            'capture' => match ($fraudStatus) {
                'accept' => 'paid',
                'deny' => 'failed',
                default => 'pending',
            },
            'settlement' => 'paid',
            'pending' => 'pending',
            'deny' => 'failed',
            'cancel' => 'cancelled',
            'expire' => 'expired',
            'failure' => 'failed',
            default => 'pending',
        };
    }

    protected function parseDateTime(mixed $value): mixed
    {
        if (! $value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}