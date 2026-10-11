<?php

namespace App\Http\Controllers\Web;

use App\Actions\Shipment\SyncShipmentStatusAction;
use App\Http\Controllers\Controller;
use App\Models\ShipmentOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BiteshipWebhookController extends Controller
{
    public function __invoke(Request $request, SyncShipmentStatusAction $syncShipmentStatusAction)
    {
        $payload = $request->all();

        $expectedSecret = config('services.biteship.webhook_secret');
        $receivedSecret = $request->header('X-Biteship-Webhook-Secret');

        if (! $expectedSecret) {
            Log::error('Biteship webhook secret is not configured');

            return response()->json([
                'message' => 'Webhook secret is not configured',
            ], 500);
        }

        if (! $receivedSecret || ! hash_equals((string) $expectedSecret, (string) $receivedSecret)) {
            Log::warning('Biteship webhook rejected: invalid secret', [
                'has_secret_header' => (bool) $receivedSecret,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Unauthorized webhook',
            ], 401);
        }

        $referenceId = $this->normalizeIdentifier(
            data_get($payload, 'reference_id')
                ?: data_get($payload, 'order.reference_id')
        );

        $externalOrderId = $this->normalizeIdentifier(
            data_get($payload, 'id')
                ?: data_get($payload, 'order.id')
                ?: data_get($payload, 'order_id')
        );

        $trackingNumber = $this->normalizeIdentifier(
            data_get($payload, 'courier_waybill_id') ?: data_get($payload, 'waybill_id')
                ?: data_get($payload, 'tracking_number')
                ?: data_get($payload, 'courier.waybill_id')
        );

        $externalStatus = $this->normalizeIdentifier(
            data_get($payload, 'status')
                ?: data_get($payload, 'order.status')
                ?: data_get($payload, 'courier.status')
        );

        if (data_get($payload, 'event') === 'order.waybill_id' && $trackingNumber) {
            $externalStatus = null; // A waybill update is metadata, not a status transition.
        }

        /*
         * Wajib ada minimal salah satu identifier.
         *
         * Jangan pernah melakukan query ShipmentOrder tanpa filter,
         * karena itu bisa mengambil shipment terbaru secara random
         * dan mengubah status order milik user lain.
         */
        if (! $referenceId && ! $externalOrderId) {
            Log::warning('Biteship webhook rejected: missing shipment identifier', [
                'reference_id' => $referenceId,
                'external_order_id' => $externalOrderId,
                'payload' => $payload,
            ]);

            return response()->json([
                'message' => 'Missing shipment identifier',
            ], 422);
        }

        return DB::transaction(function () use ($referenceId, $externalOrderId, $externalStatus, $trackingNumber, $payload, $syncShipmentStatusAction) {
            $candidates = ShipmentOrder::query()
                ->where(function ($query) use ($referenceId, $externalOrderId) {
                    if ($referenceId) {
                        $query->where('order_no', $referenceId);
                    }

                    if ($externalOrderId) {
                        $query->orWhere('external_order_id', $externalOrderId);
                    }
                })
                ->lockForUpdate()
                ->limit(2)->get();

            $shipmentOrder = $candidates->first();
            if ($candidates->count() > 1 || ($shipmentOrder && (
                ($referenceId && $shipmentOrder->order_no !== $referenceId)
                || ($externalOrderId && $shipmentOrder->external_order_id && $shipmentOrder->external_order_id !== $externalOrderId)
            ))) {
                return response()->json(['message' => 'Conflicting shipment identifiers'], 422);
            }

            if (! $shipmentOrder) {
                Log::warning('Biteship webhook shipment not found', [
                    'reference_id' => $referenceId,
                    'external_order_id' => $externalOrderId,
                    'payload' => $payload,
                ]);

                return response()->json([
                    'message' => 'Shipment not found',
                ], 404);
            }

            if (! $externalStatus && ! (data_get($payload, 'event') === 'order.waybill_id' && $trackingNumber)) {
                Log::warning('Biteship webhook rejected: missing status', [
                    'shipment_order_no' => $shipmentOrder->order_no,
                    'reference_id' => $referenceId,
                    'external_order_id' => $externalOrderId,
                    'payload' => $payload,
                ]);

                return response()->json([
                    'message' => 'Missing shipment status',
                ], 422);
            }

            $syncShipmentStatusAction->execute(
                shipmentOrder: $shipmentOrder,
                externalStatus: $externalStatus,
                trackingNumber: $trackingNumber,
                rawPayload: $payload,
                source: 'biteship_webhook',
                description: data_get($payload, 'note')
                    ?: data_get($payload, 'description')
                    ?: 'Update status dari Biteship webhook',
                eventTime: now(),
                loadDetails: false,
            );

            return response()->json([
                'message' => 'OK',
            ]);
        });
    }

    private function normalizeIdentifier(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
