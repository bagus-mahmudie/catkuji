<?php

namespace App\Http\Controllers\Web;

use App\Actions\Shipment\CreateShipmentOrderAction;
use App\Actions\Shipment\CreateShippingPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shipment\ShipmentCheckoutRequest;
use App\Http\Requests\Shipment\ShipmentPreviewRequest;
use App\Http\Requests\Shipment\ShipmentRatesRequest;
use App\Models\ShipmentOrder;
use App\Models\UserAddress;
use App\Models\UserInventory;
use App\Services\Shipping\BiteshipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Actions\Shipment\RetryBookShipmentAction;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Inertia\Response;

class ShipmentController extends Controller
{
    protected const PREVIEW_SESSION_KEY = 'shipment.preview';

    public function __construct(
        protected BiteshipService $biteshipService,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $shipments = ShipmentOrder::query()
            ->with(['items.inventory', 'address', 'paymentTransaction', 'trackingEvents'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10)
            ->through(function (ShipmentOrder $shipment) {
                return [
                    'id' => $shipment->id,
                    'order_no' => $shipment->order_no,
                    'status' => $shipment->status,
                    'shipping_cost' => (float) $shipment->shipping_cost,
                    'tracking_number' => $shipment->tracking_number,
                    'tracking_status' => $shipment->tracking_status,
                    'tracking_events' => $shipment->trackingEvents->map(function ($event) {
                        return [
                            'id' => $event->id,
                            'status_code' => $event->status_code,
                            'status_label' => $event->status_label,
                            'description' => $event->description,
                            'source' => $event->source,
                            'event_time' => optional($event->event_time)?->toDateTimeString(),
                        ];
                    })->values(),
                    'courier_name' => $shipment->courier_name,
                    'service_name' => $shipment->service_name,
                    'requested_at' => optional($shipment->requested_at)?->toDateTimeString(),
                    'paid_at' => optional($shipment->paid_at)?->toDateTimeString(),
                    'created_at' => optional($shipment->created_at)?->toDateTimeString(),
                    'items_count' => $shipment->items->sum('qty'),
                    'payment' => $shipment->paymentTransaction ? [
                        'id' => $shipment->paymentTransaction->id,
                        'status' => $shipment->paymentTransaction->status,
                        'purpose' => $shipment->paymentTransaction->purpose,
                        'gross_amount' => (float) $shipment->paymentTransaction->gross_amount,
                        'snap_token' => $shipment->paymentTransaction->snap_token,
                        'snap_redirect_url' => $shipment->paymentTransaction->snap_redirect_url,
                    ] : null,
                    'items' => $shipment->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'inventory_id' => $item->inventory_id,
                            'prize_name_snapshot' => $item->prize_name_snapshot,
                            'qty' => (int) $item->qty,
                            'image' => $item->inventory?->prize_image_snapshot,
                            'inventory_status' => $item->inventory?->status,
                        ];
                    })->values(),
                ];
            });

        return Inertia::render('Shipment/Index', [
            'shipments' => $shipments,
        ]);
    }

    public function preview(ShipmentPreviewRequest $request): RedirectResponse
    {
        $user = $request->user();
        $requestedItems = collect($request->validated('items'));

        $previewItems = $this->buildPreviewItems($user->id, $requestedItems);

        if ($previewItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Tidak ada hadiah valid yang dapat dikirim.',
            ]);
        }

        $payload = [
            'selected_items' => $previewItems->map(function (array $item) {
                return [
                    'gacha_prize_id' => $item['gacha_prize_id'],
                    'inventory_ids' => $item['inventory_ids'],
                    'qty' => $item['qty'],
                    'name' => $item['name'],
                    'image' => $item['image'],
                    'prize_type' => $item['prize_type'],
                    'sellback_price' => $item['sellback_price'],
                    'market_price' => $item['market_price'],
                    'weight_grams' => $item['weight_grams'],
                    'length_cm' => $item['length_cm'],
                    'width_cm' => $item['width_cm'],
                    'height_cm' => $item['height_cm'],
                ];
            })->values()->all(),
        ];

        session([self::PREVIEW_SESSION_KEY => $payload]);

        return redirect()->route('shipment.confirm');
    }

    public function confirm(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $preview = session(self::PREVIEW_SESSION_KEY);

        if (! is_array($preview) || empty($preview['selected_items'])) {
            return redirect()
                ->route('inventory.index')
                ->with('error', 'Pilih hadiah yang ingin dikirim terlebih dahulu.');
        }

        $addresses = UserAddress::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map(function (UserAddress $address) {
                return [
                    'id' => $address->id,
                    'label' => $address->label,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->recipient_phone,
                    'province' => $address->province,
                    'city' => $address->city,
                    'district' => $address->district,
                    'subdistrict' => $address->subdistrict,
                    'postal_code' => $address->postal_code,
                    'full_address' => $address->full_address,
                    'notes' => $address->notes,
                    'is_default' => (bool) $address->is_default,
                ];
            })
            ->values();

        $defaultAddressId = optional(
            $addresses->firstWhere('is_default', true) ?? $addresses->first()
        )['id'] ?? null;

        return Inertia::render('Shipment/Confirm', [
            'items' => $preview['selected_items'],
            'addresses' => $addresses,
            'defaultAddressId' => $defaultAddressId,
        ]);
    }

    public function rates(ShipmentRatesRequest $request)
    {
        $user = $request->user();
        $preview = session(self::PREVIEW_SESSION_KEY);

        if (! is_array($preview) || empty($preview['selected_items'])) {
            throw ValidationException::withMessages([
                'items' => 'Preview pengiriman tidak ditemukan. Silakan pilih hadiah lagi.',
            ]);
        }

        $address = UserAddress::query()
            ->where('user_id', $user->id)
            ->find($request->integer('address_id'));

        if (! $address) {
            throw ValidationException::withMessages([
                'address_id' => 'Alamat pengiriman tidak ditemukan.',
            ]);
        }

        $biteshipItems = $this->buildBiteshipItems($preview['selected_items']);

        $ratesResponse = $this->biteshipService->getRates(
            destinationPostalCode: (string) $address->postal_code,
            items: $biteshipItems,
        );

        $pricing = collect(data_get($ratesResponse, 'pricing', []))
            ->map(fn (array $rate) => $this->normalizeShippingRate($rate))
            ->filter(fn (array $rate) => $rate['shipping_cost'] >= 0)
            ->values();

        return response()->json([
            'address' => [
                'id' => $address->id,
                'label' => $address->label,
                'recipient_name' => $address->recipient_name,
                'recipient_phone' => $address->recipient_phone,
                'postal_code' => $address->postal_code,
                'full_address' => $address->full_address,
            ],
            'items' => $preview['selected_items'],
            'rates' => $pricing,
            'raw' => $ratesResponse,
        ]);
    }

    public function checkout(
        ShipmentCheckoutRequest $request,
        CreateShipmentOrderAction $createShipmentOrderAction,
        CreateShippingPaymentAction $createShippingPaymentAction
    ): RedirectResponse {
        $user = $request->user();
        $preview = session(self::PREVIEW_SESSION_KEY);

        if (! is_array($preview) || empty($preview['selected_items'])) {
            return redirect()
                ->route('inventory.index')
                ->with('error', 'Preview pengiriman tidak ditemukan. Silakan pilih hadiah lagi.');
        }

        $address = UserAddress::query()
            ->where('user_id', $user->id)
            ->find($request->integer('address_id'));

        if (! $address) {
            throw ValidationException::withMessages([
                'address_id' => 'Alamat pengiriman tidak ditemukan.',
            ]);
        }

        $biteshipItems = $this->buildBiteshipItems($preview['selected_items']);

        $ratesResponse = $this->biteshipService->getRates(
            destinationPostalCode: (string) $address->postal_code,
            items: $biteshipItems,
        );

        $selectedRate = collect(data_get($ratesResponse, 'pricing', []))
            ->map(fn (array $rate) => $this->normalizeShippingRate($rate))
            ->first(function (array $rate) use ($request) {
                return $rate['courier_code'] === strtolower((string) $request->string('courier_code'))
                    && (string) $rate['service_code'] === (string) $request->string('service_code');
            });

        if (! $selectedRate) {
            throw ValidationException::withMessages([
                'service_code' => 'Layanan pengiriman tidak valid atau sudah tidak tersedia.',
            ]);
        }

        $shipmentOrder = $createShipmentOrderAction->execute(
            user: $user,
            previewItems: collect($preview['selected_items']),
            address: $address,
            selectedRate: $selectedRate,
        );

        $createShippingPaymentAction->execute($shipmentOrder);

        session()->forget(self::PREVIEW_SESSION_KEY);

        return redirect()
            ->route('shipment.show', $shipmentOrder->order_no)
            ->with('success', 'Order pengiriman berhasil dibuat. Silakan lanjutkan pembayaran.');
    }

    public function retryBooking(
        string $orderNo,
        RetryBookShipmentAction $retryBookShipmentAction
    ): RedirectResponse {
        $shipmentOrder = ShipmentOrder::query()
            ->where('order_no', $orderNo)
            ->firstOrFail();

        if (! in_array($shipmentOrder->status, ['paid', 'booking_failed'], true)) {
            return back()->with('error', 'Booking hanya bisa diulang untuk shipment yang sudah dibayar atau gagal booking.');
        }

        try {
            $retryBookShipmentAction->execute($shipmentOrder);

            return back()->with('success', 'Booking shipment berhasil diulang.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal mengulang booking shipment: '.$e->getMessage());
        }
    }

    public function cancel(Request $request, string $orderNo): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($orderNo, $validated) {
                $shipmentOrder = ShipmentOrder::query()
                    ->with('items')
                    ->where('order_no', $orderNo)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($shipmentOrder->status, ['paid', 'booking_failed', 'shipping_requested'], true)) {
                    throw new RuntimeException('Shipment ini tidak bisa dibatalkan.');
                }

                if ($shipmentOrder->external_order_id || $shipmentOrder->hasUnresolvedBooking()) {
                    throw new RuntimeException('Booking kurir sudah dibuat atau belum pasti. Rekonsiliasi dengan Biteship sebelum pembatalan.');
                }

                $inventoryIds = $shipmentOrder->items
                    ->pluck('inventory_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->values();

                if ($inventoryIds->isEmpty()) {
                    throw new RuntimeException('Tidak ada item inventory pada shipment ini.');
                }

                UserInventory::query()
                    ->whereIn('id', $inventoryIds->all())
                    ->whereIn('status', ['shipping_pending_payment', 'shipping_requested'])
                    ->update([
                        'status' => 'stored',
                    ]);

                $previousExternalResponse = is_array($shipmentOrder->external_response_json)
                    ? $shipmentOrder->external_response_json
                    : [];

                $shipmentOrder->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'external_response_json' => array_merge($previousExternalResponse, [
                        'admin_cancel' => [
                            'cancelled_at' => now()->toDateTimeString(),
                            'reason' => $validated['reason'] ?? 'Dibatalkan oleh admin.',
                        ],
                    ]),
                ]);
            });

            return back()->with('success', 'Shipment berhasil dibatalkan dan item dikembalikan ke inventory.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Terjadi kesalahan saat membatalkan shipment.');
        }
    }

    public function show(Request $request, string $orderNo): Response
    {
        $user = $request->user();

        $shipment = ShipmentOrder::query()
            ->with(['items.inventory.prize', 'address', 'paymentTransaction', 'trackingEvents'])
            ->where('user_id', $user->id)
            ->where('order_no', $orderNo)
            ->firstOrFail();

        $payment = $shipment->paymentTransaction;

        if (! $payment) {
            $payment = \App\Models\PaymentTransaction::query()
                ->where('reference_type', ShipmentOrder::class)
                ->where('reference_id', $shipment->id)
                ->latest('id')
                ->first();
        }

        return Inertia::render('Shipment/Show', [
            'shipment' => [
                'id' => $shipment->id,
                'order_no' => $shipment->order_no,
                'status' => $shipment->status,
                'shipping_cost' => (float) $shipment->shipping_cost,
                'tracking_number' => $shipment->tracking_number,
                'tracking_status' => $shipment->tracking_status,
                'tracking_events' => $shipment->trackingEvents->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'status_code' => $event->status_code,
                        'status_label' => $event->status_label,
                        'description' => $event->description,
                        'source' => $event->source,
                        'event_time' => optional($event->event_time)?->toDateTimeString(),
                    ];
                })->values(),
                'external_order_id' => $shipment->external_order_id,
                'courier_name' => $shipment->courier_name,
                'courier_code' => $shipment->courier_code,
                'service_name' => $shipment->service_name,
                'service_code' => $shipment->service_code,
                'requested_at' => optional($shipment->requested_at)?->toDateTimeString(),
                'paid_at' => optional($shipment->paid_at)?->toDateTimeString(),
                'packed_at' => optional($shipment->packed_at)?->toDateTimeString(),
                'shipped_at' => optional($shipment->shipped_at)?->toDateTimeString(),
                'delivered_at' => optional($shipment->delivered_at)?->toDateTimeString(),
                'booking_failed_at' => optional($shipment->booking_failed_at)?->toDateTimeString(),
                'cancelled_at' => optional($shipment->cancelled_at)?->toDateTimeString(),
                'created_at' => optional($shipment->created_at)?->toDateTimeString(),
                'recipient_name_snapshot' => $shipment->recipient_name_snapshot,
                'recipient_phone_snapshot' => $shipment->recipient_phone_snapshot,
                'address_snapshot_json' => $shipment->address_snapshot_json,
                'payment' => $payment ? [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'gross_amount' => (float) $payment->gross_amount,
                    'purpose' => $payment->purpose,
                    'snap_token' => $payment->snap_token,
                    'snap_redirect_url' => $payment->snap_redirect_url,
                    'midtrans_order_id' => $payment->midtrans_order_id,
                ] : null,
                'items' => $shipment->items->map(function ($item) {
                    $inventory = $item->inventory;
                    $prize = $inventory?->prize;

                    return [
                        'id' => $item->id,
                        'inventory_id' => $item->inventory_id,
                        'qty' => (int) $item->qty,

                        // format yang dipakai TransactionPrizeItemCard
                        'name' => $item->prize_name_snapshot,
                        'image' => $inventory?->prize_image_snapshot,
                        'rarity' => $inventory?->rarity_code
                            ?? $prize?->rarity_code
                            ?? null,
                        'sellback_price' => (int) (
                            $inventory?->sellback_price_snapshot
                            ?? $inventory?->sellback_price
                            ?? $prize?->sellback_price
                            ?? 0
                        ),

                        // tetap simpan field lama kalau masih dipakai bagian lain
                        'prize_name_snapshot' => $item->prize_name_snapshot,
                        'inventory' => [
                            'id' => $inventory?->id,
                            'status' => $inventory?->status,
                            'image' => $inventory?->prize_image_snapshot,
                            'prize_type' => $inventory?->prize_type,
                        ],
                    ];
                })->values(),
            ],
        ]);
    }

    protected function buildBiteshipItems(array $previewItems): array
    {
        return collect($previewItems)
            ->map(function (array $item) {
                return [
                    'name' => $item['name'],
                    'description' => $item['name'],
                    'value' => (int) round((float) ($item['market_price'] ?? 0)),
                    'weight' => max(1, (int) ($item['weight_grams'] ?? 100)),
                    'length' => max(1, (int) ($item['length_cm'] ?? 1)),
                    'width' => max(1, (int) ($item['width_cm'] ?? 1)),
                    'height' => max(1, (int) ($item['height_cm'] ?? 1)),
                    'quantity' => (int) $item['qty'],
                ];
            })
            ->values()
            ->all();
    }

    protected function normalizeShippingRate(array $rate): array
    {
        return [
            'company' => data_get($rate, 'company'),
            'courier_name' => data_get($rate, 'courier_name') ?: data_get($rate, 'company'),
            'courier_code' => strtolower((string) data_get($rate, 'courier_code', data_get($rate, 'company'))),
            'service_name' => data_get($rate, 'courier_service_name') ?: data_get($rate, 'type'),
            'service_code' => data_get($rate, 'courier_service_code') ?: data_get($rate, 'type'),
            'shipping_cost' => (float) (
                data_get($rate, 'price')
                ?? data_get($rate, 'final_price')
                ?? data_get($rate, 'shipping_price')
                ?? 0
            ),
            'estimated_days' => data_get($rate, 'duration'),
            'available_for_cash_on_delivery' => (bool) data_get($rate, 'available_for_cash_on_delivery', false),
        ];
    }

    protected function buildPreviewItems(int $userId, Collection $requestedItems): Collection
    {
        $requestedByPrize = $requestedItems
            ->map(function ($item) {
                return [
                    'gacha_prize_id' => (int) data_get($item, 'gacha_prize_id'),
                    'quantity' => (int) data_get($item, 'quantity', 0),
                ];
            })
            ->filter(fn (array $item) => $item['gacha_prize_id'] > 0 && $item['quantity'] > 0)
            ->keyBy('gacha_prize_id');

        if ($requestedByPrize->isEmpty()) {
            return collect();
        }

        $inventories = UserInventory::query()
            ->with('prize')
            ->where('user_id', $userId)
            ->where('status', 'stored')
            ->where('prize_type', 'physical')
            ->whereIn('gacha_prize_id', $requestedByPrize->keys()->all())
            ->orderBy('id')
            ->get()
            ->groupBy('gacha_prize_id');

        $results = collect();

        foreach ($requestedByPrize as $prizeId => $requestItem) {
            $pool = $inventories->get($prizeId, collect());
            $qty = (int) $requestItem['quantity'];

            if ($pool->count() < $qty) {
                throw ValidationException::withMessages([
                    'items' => 'Sebagian hadiah yang dipilih tidak lagi tersedia untuk dikirim.',
                ]);
            }

            $selected = $pool->take($qty)->values();
            $first = $selected->first();
            $prize = $first?->prize;

            if (! $first || ! $prize) {
                throw ValidationException::withMessages([
                    'items' => 'Data hadiah tidak lengkap untuk proses pengiriman.',
                ]);
            }

            $results->push([
                'gacha_prize_id' => (int) $prizeId,
                'inventory_ids' => $selected->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'qty' => $qty,
                'name' => $first->prize_name_snapshot,
                'image' => $first->prize_image_snapshot,
                'prize_type' => $first->prize_type,
                'sellback_price' => (float) $first->sellback_price,
                'market_price' => (float) ($prize->market_price ?? 0),
                'weight_grams' => (int) ($prize->weight_grams ?? 100),
                'length_cm' => (int) ($prize->length_cm ?? 1),
                'width_cm' => (int) ($prize->width_cm ?? 1),
                'height_cm' => (int) ($prize->height_cm ?? 1),
            ]);
        }

        return $results->values();
    }
}