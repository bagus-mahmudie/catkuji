<?php

namespace App\Actions\Shipment;

use App\Models\ShipmentOrder;
use App\Models\ShipmentTrackingEvent;

class AppendShipmentTrackingEventAction
{
    public function execute(
        ShipmentOrder $shipmentOrder,
        ?string $statusCode,
        ?string $statusLabel,
        ?string $description = null,
        string $source = 'system',
        ?array $rawPayload = null,
        $eventTime = null
    ): ShipmentTrackingEvent {
        return ShipmentTrackingEvent::query()->create([
            'shipment_order_id' => $shipmentOrder->id,
            'status_code' => $statusCode,
            'status_label' => $statusLabel,
            'description' => $description,
            'source' => $source,
            'event_time' => $eventTime ?: now(),
            'raw_payload_json' => $rawPayload,
        ]);
    }
}