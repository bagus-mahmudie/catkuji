<?php

namespace App\Services\Support;

class OrderNumberService
{
    public function topupOrderNo(): string
    {
        return 'TOPUP-' . now()->format('YmdHis') . '-' . strtoupper(str()->random(6));
    }

    public function shippingPaymentOrderNo(): string
    {
        return 'SHIPPAY-' . now()->format('YmdHis') . '-' . strtoupper(str()->random(6));
    }

    public function shipmentOrderNo(): string
    {
        return 'SHIP-' . now()->format('YmdHis') . '-' . strtoupper(str()->random(6));
    }
}