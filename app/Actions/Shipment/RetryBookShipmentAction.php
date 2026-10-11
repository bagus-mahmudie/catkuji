<?php

namespace App\Actions\Shipment;

use App\Models\ShipmentOrder;
use RuntimeException;

class RetryBookShipmentAction
{
    public function __construct(
        protected BookBiteshipOrderAction $bookBiteshipOrderAction,
    ) {
    }

    public function execute(ShipmentOrder $shipmentOrder): ShipmentOrder
    {
        if (! in_array($shipmentOrder->status, ['paid', 'booking_failed', 'processing'])) {
            throw new RuntimeException('Shipment belum bisa di-booking ulang.');
        }

        return $this->bookBiteshipOrderAction->execute($shipmentOrder);
    }
}