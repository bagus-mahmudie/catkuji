<?php

namespace App\Actions\Shipment;

use App\Models\ShipmentOrder;

class MarkShipmentOrderPaidAction
{
    public function __construct(
        protected SyncShipmentStatusAction $syncShipmentStatusAction,
    ) {}

    public function execute(ShipmentOrder $shipmentOrder): ShipmentOrder
    {
        return $this->syncShipmentStatusAction->execute(
            shipmentOrder: $shipmentOrder,
            externalStatus: 'paid',
            trackingNumber: $shipmentOrder->tracking_number,
            rawPayload: $shipmentOrder->paymentTransaction?->settlement_payload_json,
            source: 'system',
            description: 'Pembayaran ongkir telah diterima',
            eventTime: now(),
            loadDetails: false,
        );
    }
}
