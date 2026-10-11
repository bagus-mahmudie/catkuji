<?php

namespace App\Actions\Shipment;

use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderItem;
use App\Models\UserAddress;
use App\Models\UserInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateShipmentOrderAction
{
public function execute(
            $user,
            Collection $previewItems,
            UserAddress $address,
            array $selectedRate,
        ): ShipmentOrder {
            if ($previewItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tidak ada hadiah yang dipilih untuk dikirim.',
                ]);
            }

            if ($address->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'address_id' => 'Alamat pengiriman tidak valid.',
                ]);
            }

            $shippingCost = (int) ($selectedRate['shipping_cost'] ?? $selectedRate['price'] ?? 0);

            $courierCode = (string) ($selectedRate['courier_code'] ?? '');
            $courierName = (string) ($selectedRate['courier_name'] ?? '');
            $serviceCode = (string) ($selectedRate['service_code'] ?? '');
            $serviceName = (string) ($selectedRate['service_name'] ?? '');

            if ($shippingCost <= 0) {
                throw ValidationException::withMessages([
                    'shipping_cost' => 'Biaya pengiriman tidak valid.',
                ]);
            }

            if ($courierCode === '' || $serviceCode === '') {
                throw ValidationException::withMessages([
                    'courier_code' => 'Layanan pengiriman tidak valid.',
                ]);
            }

            return DB::transaction(function () use (
                $user,
                $previewItems,
                $address,
                $courierCode,
                $courierName,
                $serviceCode,
                $serviceName,
                $shippingCost,
            ) {
            $allInventoryIds = $previewItems
                ->pluck('inventory_ids')
                ->flatten()
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values();

            if ($allInventoryIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tidak ada inventory valid untuk dikirim.',
                ]);
            }

            if ($allInventoryIds->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Inventory duplikat tidak valid untuk dikirim.',
                ]);
            }

            $inventories = UserInventory::query()
                ->with('prize')
                ->where('user_id', $user->id)
                ->whereIn('id', $allInventoryIds->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($inventories->count() !== $allInventoryIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Sebagian hadiah tidak ditemukan atau bukan milik Anda.',
                ]);
            }

            foreach ($previewItems as $item) {
                $inventoryIds = collect($item['inventory_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->values();

                $expectedQty = (int) ($item['qty'] ?? 0);
                $expectedPrizeId = (int) ($item['gacha_prize_id'] ?? 0);

                if ($inventoryIds->count() !== $expectedQty) {
                    throw ValidationException::withMessages([
                        'items' => 'Jumlah hadiah yang dipilih tidak sesuai.',
                    ]);
                }

                foreach ($inventoryIds as $inventoryId) {
                    /** @var UserInventory|null $inventory */
                    $inventory = $inventories->get($inventoryId);

                    if (! $inventory) {
                        throw ValidationException::withMessages([
                            'items' => 'Sebagian hadiah tidak ditemukan.',
                        ]);
                    }

                    if ((int) $inventory->gacha_prize_id !== $expectedPrizeId) {
                        throw ValidationException::withMessages([
                            'items' => 'Data hadiah tidak cocok dengan inventory yang dipilih.',
                        ]);
                    }

                    if ($inventory->status !== 'stored') {
                        throw ValidationException::withMessages([
                            'items' => 'Sebagian hadiah sudah tidak tersedia untuk dikirim.',
                        ]);
                    }

                    if ($inventory->prize_type !== 'physical') {
                        throw ValidationException::withMessages([
                            'items' => 'Hanya hadiah fisik yang dapat dikirim.',
                        ]);
                    }
                }
            }

            $order = ShipmentOrder::query()->create([
                'order_no' => $this->generateOrderNo(),
                'user_id' => $user->id,
                'address_id' => $address->id,
                'recipient_name_snapshot' => $address->recipient_name,
                'recipient_phone_snapshot' => $address->recipient_phone,
                'address_snapshot_json' => [
                    'label' => $address->label,
                    'province' => $address->province,
                    'city' => $address->city,
                    'district' => $address->district,
                    'subdistrict' => $address->subdistrict,
                    'postal_code' => $address->postal_code,
                    'full_address' => $address->full_address,
                    'notes' => $address->notes,
                ],
                'shipping_cost' => $shippingCost,
                'courier_name' => $courierName,
                'courier_code' => $courierCode,
                'service_name' => $serviceName,
                'service_code' => $serviceCode,
                'status' => 'pending_payment',
                'requested_at' => now(),
            ]);

            app(\App\Actions\Shipment\AppendShipmentTrackingEventAction::class)->execute(
                shipmentOrder: $order,
                statusCode: 'pending_payment',
                statusLabel: 'Menunggu pembayaran ongkir',
                description: 'Order shipment berhasil dibuat dan menunggu pembayaran ongkir.',
                source: 'system',
                rawPayload: [
                    'shipping_cost' => $shippingCost,
                    'courier_code' => $courierCode,
                    'service_code' => $serviceCode,
                ],
                eventTime: now(),
            );

            foreach ($previewItems as $item) {
                $inventoryIds = collect($item['inventory_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->values();

                foreach ($inventoryIds as $inventoryId) {
                    /** @var UserInventory $inventory */
                    $inventory = $inventories->get($inventoryId);

                    ShipmentOrderItem::query()->create([
                        'shipment_order_id' => $order->id,
                        'inventory_id' => $inventory->id,
                        'prize_name_snapshot' => $inventory->prize_name_snapshot,
                        'qty' => 1,
                    ]);

                    $inventory->update([
                        'status' => 'shipping_pending_payment',
                    ]);
                }
            }

            return $order->fresh([
                'items.inventory',
                'address',
                'paymentTransaction',
            ]);
        });
    }

    protected function generateOrderNo(): string
    {
        return 'SHP-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
    }
}