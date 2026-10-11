<?php

namespace App\Actions\Shipment;

use App\Models\PaymentTransaction;
use App\Models\ShipmentOrder;
use App\Services\Midtrans\MidtransService;
use App\Services\Support\OrderNumberService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateShippingPaymentAction
{
    public function __construct(
        protected OrderNumberService $orderNumberService,
        protected MidtransService $midtransService,
    ) {
    }

    public function execute(ShipmentOrder $shipmentOrder): PaymentTransaction
    {
        if ((float) $shipmentOrder->shipping_cost < 0) {
            throw new RuntimeException('Biaya pengiriman tidak valid.');
        }

        return DB::transaction(function () use ($shipmentOrder) {
            $shipmentOrder->loadMissing('user');

            if ((int) $shipmentOrder->payment_transaction_id > 0) {
                $existingPayment = PaymentTransaction::query()->find($shipmentOrder->payment_transaction_id);

                if ($existingPayment) {
                    return $existingPayment;
                }
            }

            $orderNo = $this->orderNumberService->shippingPaymentOrderNo();
            $grossAmount = (float) $shipmentOrder->shipping_cost;

            $payment = PaymentTransaction::query()->create([
                'user_id' => $shipmentOrder->user_id,
                'purpose' => 'shipping',
                'order_no' => $orderNo,
                'midtrans_order_id' => $orderNo,
                'gross_amount' => $grossAmount,
                'currency' => 'IDR',
                'status' => 'pending',
                'reference_type' => ShipmentOrder::class,
                'reference_id' => $shipmentOrder->id,
                'meta_json' => [
                    'shipment_order_id' => $shipmentOrder->id,
                    'shipment_order_no' => $shipmentOrder->order_no,
                    'shipping_cost' => $grossAmount,
                    'courier_name' => $shipmentOrder->courier_name,
                    'service_name' => $shipmentOrder->service_name,
                ],
            ]);

            $customer = [
                'first_name' => $shipmentOrder->recipient_name_snapshot ?: ($shipmentOrder->user?->name ?? 'Customer'),
                'email' => $shipmentOrder->user?->email,
                'phone' => $shipmentOrder->recipient_phone_snapshot,
            ];

            $itemDetails = [[
                'id' => 'SHIP-' . $shipmentOrder->id,
                'name' => sprintf(
                    'Biaya Pengiriman %s - %s',
                    $shipmentOrder->courier_name ?: 'Kurir',
                    $shipmentOrder->service_name ?: 'Service'
                ),
                'price' => (int) round($grossAmount),
                'quantity' => 1,
            ]];

            $callbacks = [
                'finish' => route('shipment.show', $shipmentOrder->order_no),
                'unfinish' => route('shipment.show', $shipmentOrder->order_no),
                'error' => route('shipment.show', $shipmentOrder->order_no),
            ];

            $payload = [
                'transaction_details' => [
                    'order_id' => $payment->midtrans_order_id,
                    'gross_amount' => (int) round($grossAmount),
                ],
                'customer_details' => array_filter([
                    'first_name' => $customer['first_name'] ?? null,
                    'email' => $customer['email'] ?? null,
                    'phone' => $customer['phone'] ?? null,
                ]),
                'item_details' => $itemDetails,
                'callbacks' => $callbacks,
            ];

            $response = $this->midtransService->createSnapTransaction($payload);

            $payment->update([
                'snap_token' => data_get($response, 'token'),
                'snap_redirect_url' => data_get($response, 'redirect_url'),
                'raw_request_json' => $payload,
                'raw_response_json' => is_array($response) ? $response : (array) $response,
            ]);

            // WAJIB: hubungkan shipment ke payment ini
            $shipmentOrder->update([
                'payment_transaction_id' => $payment->id,
            ]);

            return $payment->fresh();
        });
    }
}