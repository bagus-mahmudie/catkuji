<?php

namespace App\Actions\Shipment;

use App\Models\ShipmentOrder;
use Illuminate\Support\Facades\DB;

class SyncShipmentStatusAction
{
    public function __construct(
        protected AppendShipmentTrackingEventAction $appendTrackingEventAction,
    ) {}

    public function execute(
        ShipmentOrder $shipmentOrder,
        ?string $externalStatus,
        ?string $trackingNumber = null,
        ?array $rawPayload = null,
        string $source = 'system',
        ?string $description = null,
        $eventTime = null,
        bool $loadDetails = true
    ): ShipmentOrder {
        $normalized = $this->normalizeStatus($externalStatus);

        return DB::transaction(function () use (
            $shipmentOrder,
            $externalStatus,
            $trackingNumber,
            $rawPayload,
            $source,
            $description,
            $eventTime,
            $normalized,
            $loadDetails
        ) {
            $shipmentOrder = ShipmentOrder::query()
                ->lockForUpdate()
                ->findOrFail($shipmentOrder->id);

            // Ignore stale notifications without regressing shipment or inventory state.
            $progress = ['pending_payment' => 0, 'requested' => 0, 'paid' => 1,
                'booking_failed' => 1, 'processing' => 2, 'packed' => 3, 'shipped' => 4, 'delivered' => 5];
            $incoming = $normalized['shipment_status'];
            if (in_array($shipmentOrder->status, ['delivered', 'cancelled', 'returned'], true)
                && $incoming !== null && $incoming !== $shipmentOrder->status) {
                return $shipmentOrder;
            }
            if ($incoming && ! in_array($incoming, ['booking_failed', 'cancelled', 'returned'], true) && isset($progress[$incoming])
                && $progress[$incoming] < max($progress[$shipmentOrder->status] ?? 0, (int) data_get($shipmentOrder->external_response_json, 'highest_progress', 0))) {
                return $shipmentOrder;
            }

            $existingResponse = is_array($shipmentOrder->external_response_json)
                ? $shipmentOrder->external_response_json
                : [];

            $existingResponse['highest_progress'] = max(
                (int) ($existingResponse['highest_progress'] ?? 0),
                $progress[$shipmentOrder->status] ?? 0, $progress[$incoming] ?? 0
            );

            $oldStatus = $shipmentOrder->status;
            $oldTrackingNumber = $shipmentOrder->tracking_number;
            $newStatus = $normalized['shipment_status'] ?: $shipmentOrder->status;

            $updates = [
                'tracking_status' => $externalStatus ?: $shipmentOrder->tracking_status,
                'external_response_json' => $rawPayload
                    ? array_merge($existingResponse, [
                        'last_sync_source' => $source,
                        'last_sync_at' => now()->toDateTimeString(),
                        'last_payload' => $rawPayload,
                    ])
                    : $existingResponse,
            ];

            if ($trackingNumber) {
                $updates['tracking_number'] = $trackingNumber;
            }

            if ($normalized['shipment_status']) {
                $updates['status'] = $normalized['shipment_status'];
            }

            if ($normalized['shipment_status'] === 'packed' && ! $shipmentOrder->packed_at) {
                $updates['packed_at'] = now();
            }

            if ($normalized['shipment_status'] === 'shipped' && ! $shipmentOrder->shipped_at) {
                $updates['shipped_at'] = now();
            }

            if ($normalized['shipment_status'] === 'delivered' && ! $shipmentOrder->delivered_at) {
                $updates['delivered_at'] = now();
            }

            if ($normalized['shipment_status'] === 'booking_failed' && ! $shipmentOrder->booking_failed_at) {
                $updates['booking_failed_at'] = now();
            }

            if ($incoming === 'cancelled' && ! $shipmentOrder->cancelled_at) {
                $updates['cancelled_at'] = now();
            }

            $shipmentOrder->update($updates);
            if ($normalized['inventory_status'] && $oldStatus !== $newStatus) {
                $shipmentOrder->load('items.inventory');
            }

            foreach (($shipmentOrder->relationLoaded('items') ? $shipmentOrder->items : []) as $item) {
                if (! $item->inventory) {
                    continue;
                }

                $inventoryUpdates = [];

                if ($normalized['inventory_status']) {
                    $inventoryUpdates['status'] = $normalized['inventory_status'];
                }

                if ($normalized['inventory_status'] === 'shipping_requested' && ! $item->inventory->shipping_requested_at) {
                    $inventoryUpdates['shipping_requested_at'] = now();
                }

                if ($normalized['inventory_status'] === 'shipped' && ! $item->inventory->shipped_at) {
                    $inventoryUpdates['shipped_at'] = now();
                }

                if ($normalized['inventory_status'] === 'delivered' && ! $item->inventory->delivered_at) {
                    $inventoryUpdates['delivered_at'] = now();
                }

                if (! empty($inventoryUpdates)) {
                    $item->inventory->update($inventoryUpdates);
                }
            }

            $shouldAppendEvent =
                $oldStatus !== $newStatus
                || ($trackingNumber && $oldTrackingNumber !== $trackingNumber)
                || $source === 'biteship_webhook';

            if ($shouldAppendEvent) {
                $lastEvent = $shipmentOrder->trackingEvents()
                    ->reorder()->latest('event_time')->latest('id')
                    ->first();

                $newStatusCode = $normalized['shipment_status'] ?: ($externalStatus ?: $shipmentOrder->status);
                $newStatusLabel = $this->label($newStatusCode);

                $isDuplicateLastEvent =
                    $lastEvent
                    && $lastEvent->status_code === $newStatusCode
                    && $lastEvent->source === $source
                    && (string) $lastEvent->description === (string) $description
                    && (! $trackingNumber || $oldTrackingNumber === $trackingNumber);

                if (! $isDuplicateLastEvent) {
                    $this->appendTrackingEventAction->execute(
                        shipmentOrder: $shipmentOrder,
                        statusCode: $newStatusCode,
                        statusLabel: $newStatusLabel,
                        description: $description,
                        source: $source,
                        rawPayload: $rawPayload,
                        eventTime: $eventTime,
                    );
                }
            }

            return $loadDetails ? $shipmentOrder->load([
                'items.inventory',
                'paymentTransaction',
                'address',
                'trackingEvents',
            ]) : $shipmentOrder;
        });
    }

    protected function normalizeStatus(?string $status): array
    {
        $value = strtolower(trim((string) $status));

        return match (true) {
            $value === 'paid' => [
                'shipment_status' => 'paid',
                'inventory_status' => 'shipping_requested',
            ],
            in_array($value, ['processing', 'confirmed', 'allocated', 'picked_up']) => [
                'shipment_status' => 'processing',
                'inventory_status' => 'shipping_requested',
            ],
            in_array($value, ['packed', 'package_ready']) => [
                'shipment_status' => 'packed',
                'inventory_status' => 'shipping_requested',
            ],
            in_array($value, ['shipped', 'on_going', 'in_transit', 'courier_on_the_way']) => [
                'shipment_status' => 'shipped',
                'inventory_status' => 'shipped',
            ],
            in_array($value, ['delivered', 'completed']) => [
                'shipment_status' => 'delivered',
                'inventory_status' => 'delivered',
            ],
            in_array($value, ['failed', 'booking_failed']) => [
                'shipment_status' => 'booking_failed',
                'inventory_status' => null,
            ],
            in_array($value, ['cancelled', 'canceled']) => [
                'shipment_status' => 'cancelled', 'inventory_status' => null,
            ],
            $value === 'returned' => [
                'shipment_status' => 'returned', 'inventory_status' => null,
            ],
            default => [
                'shipment_status' => null,
                'inventory_status' => null,
            ],
        };
    }

    protected function label(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'paid' => 'Pembayaran tervalidasi',
            'processing' => 'Pesanan sedang diproses',
            'packed' => 'Paket sedang disiapkan',
            'shipped' => 'Paket sedang dikirim',
            'delivered' => 'Paket telah diterima',
            'booking_failed' => 'Booking pengiriman gagal',
            'cancelled' => 'Pengiriman dibatalkan',
            'returned' => 'Pengiriman dikembalikan',
            default => ucfirst((string) $status),
        };
    }
}
