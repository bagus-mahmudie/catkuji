<?php

namespace App\Actions\Shipment;

use App\Exceptions\ShipmentBookingPendingException;
use App\Models\ShipmentOrder;
use App\Services\Shipping\BiteshipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BookBiteshipOrderAction
{
    public function __construct(
        protected BiteshipService $biteshipService,
        protected SyncShipmentStatusAction $syncShipmentStatusAction,
    ) {}

    public function execute(ShipmentOrder $shipmentOrder): ShipmentOrder
    {
        $lockedShipment = DB::transaction(function () use ($shipmentOrder) {
            $freshShipment = ShipmentOrder::query()
                ->lockForUpdate()
                ->with([
                    'items.inventory.prize',
                    'address',
                    'user',
                ])
                ->find($shipmentOrder->id);

            if (! $freshShipment) {
                throw new RuntimeException('Shipment order tidak ditemukan.');
            }

            if ($freshShipment->external_order_id) {
                return $freshShipment;
            }

            return $freshShipment;
        });

        if ($lockedShipment->external_order_id) {
            return $lockedShipment->fresh([
                'items.inventory.prize',
                'address',
                'paymentTransaction',
                'trackingEvents',
            ]);
        }

        $addressSnapshot = $lockedShipment->address_snapshot_json ?? [];
        $destinationAddress = data_get($addressSnapshot, 'full_address');
        $destinationPostalCode = data_get($addressSnapshot, 'postal_code');

        $originContactName = (string) config('services.biteship.origin_contact_name');
        $originContactPhone = (string) config('services.biteship.origin_contact_phone');
        $originContactEmail = (string) config('services.biteship.origin_contact_email');
        $originAddress = (string) config('services.biteship.origin_address');
        $originPostalCode = (string) config('services.biteship.origin_postal_code');

        if (
            $originContactName === '' ||
            $originContactPhone === '' ||
            $originContactEmail === '' ||
            $originAddress === '' ||
            $originPostalCode === ''
        ) {
            throw new RuntimeException('Konfigurasi origin Biteship belum lengkap.');
        }

        if (! $destinationAddress || ! $destinationPostalCode) {
            throw new RuntimeException('Data alamat pengiriman tidak lengkap.');
        }

        $items = $lockedShipment->items->map(function ($item) {
            $inventory = $item->inventory;
            $prize = $inventory?->prize;

            return [
                'name' => $item->prize_name_snapshot,
                'description' => $item->prize_name_snapshot,
                'value' => (int) round((float) ($prize?->market_price ?? 0)),
                'weight' => max(1, (int) ($prize?->weight_grams ?? 100)),
                'length' => max(1, (int) ($prize?->length_cm ?? 1)),
                'width' => max(1, (int) ($prize?->width_cm ?? 1)),
                'height' => max(1, (int) ($prize?->height_cm ?? 1)),
                'quantity' => (int) ($item->qty ?? 1),
            ];
        })->values()->all();

        if (! $lockedShipment->recipient_name_snapshot || ! $lockedShipment->recipient_phone_snapshot) {
            throw new RuntimeException('Data penerima pengiriman belum lengkap.');
        }

        if (! $lockedShipment->courier_code || ! $lockedShipment->service_code) {
            throw new RuntimeException('Kurir atau layanan pengiriman belum dipilih.');
        }

        $payload = [
            'shipper_contact_name' => $originContactName,
            'shipper_contact_phone' => $originContactPhone,
            'shipper_contact_email' => $originContactEmail,
            'shipper_organization' => config('app.name'),
            'origin_contact_name' => $originContactName,
            'origin_contact_phone' => $originContactPhone,
            'origin_address' => $originAddress,
            'origin_postal_code' => $originPostalCode,
            'origin_note' => (string) config('services.biteship.origin_note'),

            'destination_contact_name' => $lockedShipment->recipient_name_snapshot,
            'destination_contact_phone' => $lockedShipment->recipient_phone_snapshot,
            'destination_contact_email' => $lockedShipment->user?->email ?: $originContactEmail,
            'destination_address' => $destinationAddress,
            'destination_postal_code' => $destinationPostalCode,
            'destination_note' => data_get($addressSnapshot, 'notes'),

            'courier_company' => $lockedShipment->courier_code,
            'courier_type' => $lockedShipment->service_code,
            'delivery_type' => 'now',
            'items' => $items,
            'reference_id' => $lockedShipment->order_no,
            'note' => 'Shipment order from '.config('app.name'),
        ];

        // Persist the claim before external I/O. A timeout must never release it automatically.
        $claimed = DB::transaction(function () use ($lockedShipment) {
            $shipment = ShipmentOrder::query()->lockForUpdate()->findOrFail($lockedShipment->id);
            if ($shipment->external_order_id) {
                return false;
            }
            if ($shipment->hasUnresolvedBooking()) {
                throw new ShipmentBookingPendingException('Booking sedang diproses atau hasilnya belum pasti. Rekonsiliasi dengan Biteship sebelum mencoba kembali.');
            }
            if (! in_array($shipment->status, ['paid', 'booking_failed'], true)) {
                throw new RuntimeException('Shipment belum bisa di-booking.');
            }
            $meta = $shipment->external_response_json ?? [];
            $meta['booking_attempt'] = ['id' => (string) Str::uuid(), 'started_at' => now()->toIso8601String()];
            $shipment->update(['external_response_json' => $meta]);

            return $meta['booking_attempt']['id'];
        });

        if (! $claimed) {
            return $lockedShipment->fresh();
        }

        try {
            $response = $this->biteshipService->createOrder($payload);
            if (empty($response['id']) || ($response['success'] ?? true) !== true) {
                throw new RuntimeException('Respons booking Biteship tidak memuat ID order yang valid.');
            }
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($lockedShipment, $exception, $claimed) {
                $shipment = ShipmentOrder::query()->lockForUpdate()->findOrFail($lockedShipment->id);
                if ($shipment->external_order_id || data_get($shipment->external_response_json, 'booking_attempt.id') !== $claimed) {
                    return;
                }
                $meta = $shipment->external_response_json ?? [];
                $meta['booking_attempt']['error'] = $exception->getMessage();
                $meta['booking_attempt']['result'] = 'unknown';
                $shipment->update(['external_response_json' => $meta]);
            });
            throw new ShipmentBookingPendingException('Hasil booking belum pasti. Periksa Biteship dan lakukan rekonsiliasi sebelum retry atau pembatalan.', 0, $exception);
        }

        return DB::transaction(function () use ($lockedShipment, $response, $claimed) {
            $shipment = ShipmentOrder::query()->lockForUpdate()->findOrFail($lockedShipment->id);
            if (data_get($shipment->external_response_json, 'booking_attempt.id') !== $claimed) {
                throw new ShipmentBookingPendingException('Percobaan booking sudah berubah. Rekonsiliasi diperlukan.');
            }
            if ($shipment->external_order_id && $shipment->external_order_id !== (string) $response['id']) {
                throw new ShipmentBookingPendingException('ID booking bertentangan. Rekonsiliasi diperlukan.');
            }
            $meta = $shipment->external_response_json ?? [];
            $hasNewerUpdate = ($meta['last_sync_source'] ?? null) === 'biteship_webhook'
                || ($meta['last_sync_source'] ?? null) === 'manual_reconciliation'
                || ! in_array($shipment->status, ['paid', 'booking_failed'], true);
            $meta['booking_response'] = $response;
            $shipment->update([
                'external_order_id' => $response['id'],
                'external_response_json' => $meta,
            ]);
            // An earlier POST response cannot replace metadata received from a newer webhook.
            if ($hasNewerUpdate) {
                return $shipment;
            }

            return $this->syncShipmentStatusAction->execute(
                shipmentOrder: $shipment,
                externalStatus: data_get($response, 'status') ?: 'processing',
                trackingNumber: data_get($response, 'courier.waybill_id') ?: data_get($response, 'waybill_id') ?: data_get($response, 'tracking_number'),
                rawPayload: $response,
                source: 'system',
                description: 'Shipment berhasil dibuat di Biteship',
                eventTime: now(),
                loadDetails: false,
            );
        });
    }
}
