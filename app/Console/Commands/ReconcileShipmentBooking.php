<?php

namespace App\Console\Commands;

use App\Actions\Shipment\SyncShipmentStatusAction;
use App\Models\ShipmentOrder;
use App\Services\Shipping\BiteshipService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReconcileShipmentBooking extends Command
{
    protected $signature = 'shipments:reconcile-booking {order_no} {external_order_id}';

    protected $description = 'Attach a verified Biteship order after an ambiguous booking response';

    public function handle(BiteshipService $service, SyncShipmentStatusAction $sync): int
    {
        try {
            $orderNo = (string) $this->argument('order_no');
            $externalId = (string) $this->argument('external_order_id');
            $response = $service->getOrder($externalId);
            if (($response['success'] ?? false) !== true
                || (string) ($response['id'] ?? '') !== $externalId
                || (string) ($response['reference_id'] ?? '') !== $orderNo) {
                throw new RuntimeException('ID atau reference_id Biteship tidak cocok dengan shipment lokal.');
            }

            DB::transaction(function () use ($orderNo, $externalId, $response, $sync) {
                $shipment = ShipmentOrder::where('order_no', $orderNo)->lockForUpdate()->firstOrFail();
                if (in_array($shipment->status, ['cancelled', 'returned'], true)
                    || ($shipment->external_order_id && $shipment->external_order_id !== $externalId)) {
                    throw new RuntimeException('Shipment tidak dapat ditautkan ke order ini.');
                }
                if (ShipmentOrder::where('external_order_id', $externalId)->whereKeyNot($shipment->id)->exists()) {
                    throw new RuntimeException('Order Biteship sudah dipakai oleh shipment lain.');
                }
                $meta = $shipment->external_response_json ?? [];
                $meta['booking_reconciliation'] = [
                    'external_order_id' => $externalId,
                    'reconciled_at' => now()->toIso8601String(),
                ];
                $shipment->update(['external_order_id' => $externalId, 'external_response_json' => $meta]);
                $sync->execute(
                    shipmentOrder: $shipment,
                    externalStatus: $response['status'] ?? 'processing',
                    trackingNumber: data_get($response, 'courier.waybill_id'),
                    rawPayload: $response,
                    source: 'manual_reconciliation',
                    description: 'Booking direkonsiliasi dengan order Biteship terverifikasi',
                    eventTime: now(),
                    loadDetails: false,
                );
            });
            $this->info('Booking berhasil direkonsiliasi tanpa membuat order kurir baru.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
