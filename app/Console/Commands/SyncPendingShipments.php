<?php

namespace App\Console\Commands;

use App\Actions\Shipment\SyncShipmentStatusAction;
use App\Models\ShipmentOrder;
use App\Services\Shipping\BiteshipService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;

class SyncPendingShipments extends Command implements Isolatable
{
    protected $isolated = true;

    protected $signature = 'shipments:sync-pending';

    protected $description = 'Sync pending/processing shipments from Biteship';

    public function handle(BiteshipService $biteshipService, SyncShipmentStatusAction $syncShipmentStatusAction): int
    {
        $shipments = ShipmentOrder::query()
            ->whereNotNull('external_order_id')
            ->whereIn('status', ['paid', 'processing', 'packed', 'shipped'])
            ->lazyById(100);

        $failed = false;

        foreach ($shipments as $shipment) {
            try {
                $response = $biteshipService->getOrder($shipment->external_order_id);

                $syncShipmentStatusAction->execute(
                    shipmentOrder: $shipment,
                    externalStatus: data_get($response, 'status') ?: data_get($response, 'courier.status'),
                    trackingNumber: data_get($response, 'courier.waybill_id') ?: data_get($response, 'waybill_id') ?: data_get($response, 'tracking_number'),
                    rawPayload: $response,
                    source: 'biteship_sync',
                    description: 'Sinkronisasi berkala dari Biteship',
                    eventTime: now(),
                    loadDetails: false,
                );

                $this->info("Synced {$shipment->order_no}");
            } catch (\Throwable $e) {
                $failed = true;
                $this->error("Failed {$shipment->order_no}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
