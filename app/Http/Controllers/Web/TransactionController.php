<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DrawTransaction;
use App\Models\PaymentTransaction;
use App\Models\SellbackTransaction;
use App\Models\ShipmentOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $service = strtolower(trim($request->string('service')->toString() ?: 'all'));
        $status = strtolower(trim($request->string('status')->toString() ?: 'all'));

        $topups = in_array($service, ['all', 'topup'], true) ? $this->mapTopups($user->id, $status) : collect();
        $shipments = in_array($service, ['all', 'shipping'], true) ? $this->mapShipments($user->id, $status) : collect();
        $sellbacks = in_array($service, ['all', 'sellback'], true) ? $this->mapSellbacks($user->id, $status) : collect();
        $purchases = in_array($service, ['all', 'purchase'], true) ? $this->mapPurchases($user->id, $status) : collect();

        // belum ada data backend khusus klaim hadiah digital
        $digitalClaims = collect();

        $transactions = collect()
            ->concat($topups)
            ->concat($shipments)
            ->concat($sellbacks)
            ->concat($purchases)
            ->concat($digitalClaims)
            ->sortByDesc('created_at')
            ->values();

        if ($service !== 'all') {
            $transactions = $transactions
                ->where('service', $service)
                ->values();
        }

        if ($status !== 'all') {
            $transactions = $transactions
                ->filter(fn ($item) => strtolower((string) ($item['status_key'] ?? '')) === $status)
                ->values();
        }

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => [
                'service' => $service,
                'status' => $status,
            ],
        ]);
    }

    protected function imageUrl(?string $path, string $placeholder = 'placeholder-prize.png'): string
    {
        $placeholderUrl = asset('images/'.$placeholder);

        if (! $path) {
            return $placeholderUrl;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return '/'.$path;
        }

        if (str_starts_with($path, '/images/')) {
            $filename = basename($path);

            if ($filename === 'placeholder-prize.png') {
                return $path;
            }

            return asset('storage/prizes/'.$filename);
        }

        if (str_starts_with($path, 'images/')) {
            $filename = basename($path);

            if ($filename === 'placeholder-prize.png') {
                return asset($path);
            }

            return asset('storage/prizes/'.$filename);
        }

        if (! str_contains($path, '/')) {
            return asset('storage/prizes/'.$path);
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    protected function mapTopups(int $userId, string $status = 'all')
    {
        return PaymentTransaction::query()
            ->select(['id', 'user_id', 'purpose', 'status', 'gross_amount', 'payment_type', 'created_at', 'order_no', 'snap_redirect_url', 'snap_token'])
            ->where('user_id', $userId)
            ->where('purpose', 'topup')
            ->tap(fn ($query) => $this->filterStatus($query, $status, [
                'paid' => 'success', 'pending' => 'pending', 'failed' => 'failed',
                'expired' => 'expired', 'cancelled' => 'cancelled',
            ]))
            ->latest()->orderByDesc('id')
            ->pipe(fn ($query) => $this->historyRows($query))
            ->map(function (PaymentTransaction $item) {
                $coins = (int) floor((float) $item->gross_amount / 1000);
                $isPending = strtolower((string) $item->status) === 'pending';
                $isPaid = strtolower((string) $item->status) === 'paid';

                return [
                    'id' => 'topup-'.$item->id,
                    'service' => 'topup',
                    'service_label' => 'Top Up',
                    'icon' => 'bi bi-wallet2',
                    'status_key' => $this->normalizePaymentStatus($item->status),
                    'status_label' => $this->paymentStatusLabel($item->status),
                    'created_at' => optional($item->created_at)?->toDateTimeString(),
                    'amount' => (float) $item->gross_amount,
                    'amount_prefix' => '+',
                    'order_no' => $item->order_no,
                    'summary' => $this->paymentStatusLabel($item->status),
                    'sections' => [
                        'topup' => [
                            'rows' => [
                                [
                                    'label' => 'Status',
                                    'value' => $this->paymentStatusLabel($item->status),
                                ],
                                [
                                    'label' => 'Total Pembayaran',
                                    'value' => 'Rp '.number_format((float) $item->gross_amount, 0, ',', '.'),
                                ],
                                [
                                    'label' => 'Detail Produk',
                                    'value' => number_format($coins, 0, ',', '.').' Cat Koin',
                                ],
                                [
                                    'label' => 'Metode Bayar',
                                    'value' => $item->payment_type ?: 'Belum tercatat',
                                ],
                                [
                                    'label' => 'Transaksi dibuat',
                                    'value' => optional($item->created_at)?->format('d M Y • H.i'),
                                ],
                                [
                                    'label' => 'No. transaksi',
                                    'value' => $item->order_no,
                                ],
                            ],
                            'action_url' => $isPending ? $item->snap_redirect_url : null,
                            'snap_token' => $isPending ? $item->snap_token : null,
                            'action_label' => $isPending ? 'Bayar Sekarang' : null,
                            'receipt_url' => $isPaid
                                ? route('transactions.receipt.topup', $item)
                                : null,
                            'support_url' => '#',
                        ],
                    ],
                ];
            })->collect();
    }

    protected function mapShipments(int $userId, string $status = 'all')
    {
        return ShipmentOrder::query()
            ->select(['id', 'user_id', 'payment_transaction_id', 'status', 'created_at', 'shipping_cost', 'order_no', 'tracking_number', 'tracking_status', 'external_order_id', 'address_snapshot_json', 'recipient_name_snapshot', 'recipient_phone_snapshot', 'courier_name', 'service_name'])
            ->with([
                'paymentTransaction:id,status',
                'items.inventory.prize',
            ])
            ->where('user_id', $userId)
            ->tap(fn ($query) => $this->filterStatus($query, $status, [
                'requested' => 'pending', 'pending_payment' => 'pending',
                'paid' => 'processing', 'processing' => 'processing', 'packed' => 'processing',
                'shipped' => 'shipped', 'delivered' => 'delivered',
                'booking_failed' => 'failed', 'failed' => 'failed', 'cancelled' => 'cancelled',
            ]))
            ->latest()->orderByDesc('id')
            ->pipe(fn ($query) => $this->historyRows($query))
            ->map(function (ShipmentOrder $item) {
                $address = $item->address_snapshot_json ?? [];

                $timeline = [
                    [
                        'label' => 'Order dibuat',
                        'active' => true,
                        'icon' => 'bi bi-box-seam',
                    ],
                    [
                        'label' => 'Pembayaran valid',
                        'active' => in_array($item->status, ['paid', 'processing', 'packed', 'shipped', 'delivered']),
                        'icon' => 'bi bi-patch-check',
                    ],
                    [
                        'label' => 'Diproses gudang',
                        'active' => in_array($item->status, ['processing', 'packed', 'shipped', 'delivered']),
                        'icon' => 'bi bi-archive',
                    ],
                    [
                        'label' => 'Dikirim',
                        'active' => in_array($item->status, ['shipped', 'delivered']),
                        'icon' => 'bi bi-truck',
                    ],
                    [
                        'label' => 'Selesai',
                        'active' => in_array($item->status, ['delivered']),
                        'icon' => 'bi bi-check-circle',
                    ],
                ];

                $items = $item->items->map(function ($orderItem) {
                    $inventory = $orderItem->inventory;
                    $prize = $inventory?->prize;

                    return [
                        'name' => $orderItem->prize_name_snapshot,
                        'image' => $inventory?->prize_image_snapshot ?? $prize?->image,
                        'rarity' => strtoupper($prize?->rarity_code ?? 'SILVER'),
                        'qty' => (int) $orderItem->qty,
                        'sellback_price' => (float) ($inventory?->sellback_price ?? 0),
                    ];
                })->values();

                return [
                    'id' => 'shipment-'.$item->id,
                    'service' => 'shipping',
                    'service_label' => 'Pengiriman',
                    'icon' => 'bi bi-box-seam',
                    'status_key' => $this->normalizeShipmentStatus($item->status),
                    'status_label' => $this->shipmentStatusLabel($item->status),
                    'created_at' => optional($item->created_at)?->toDateTimeString(),
                    'amount' => (float) $item->shipping_cost,
                    'amount_prefix' => '-',
                    'order_no' => $item->order_no,
                    'summary' => $this->shipmentStatusLabel($item->status),
                    'tracking_number' => $item->tracking_number,
                    'tracking_status' => $item->tracking_status,
                    'external_order_id' => $item->external_order_id,
                    'sections' => [
                        'shipping' => [
                            'timeline' => $timeline,
                            'address' => [
                                'name' => $item->recipient_name_snapshot,
                                'phone' => $item->recipient_phone_snapshot,
                                'full_address' => $address['full_address'] ?? '-',
                            ],
                            'rows' => [
                                [
                                    'label' => 'No. Pesanan',
                                    'value' => $item->order_no ?: '-',
                                ],
                                [
                                    'label' => 'Status order',
                                    'value' => $this->shipmentStatusLabel($item->status),
                                ],
                                [
                                    'label' => 'Kurir',
                                    'value' => trim(($item->courier_name ?: '-').' - '.($item->service_name ?: '-')),
                                ],
                                [
                                    'label' => 'No. resi',
                                    'value' => $item->tracking_number ?: '-',
                                ],
                                [
                                    'label' => 'Tracking status',
                                    'value' => 'Lihat Detail',
                                    'type' => 'link',
                                    'href' => route('shipment.show', $item->order_no),
                                ],
                            ],
                            'items' => $items,
                            'payment_url' => $item->paymentTransaction && strtolower((string) $item->paymentTransaction->status) === 'pending'
                                ? route('shipment.show', [
                                    'orderNo' => $item->order_no,
                                    'open_payment' => 1,
                                ])
                                : null,
                            'courier_name' => $item->courier_name,
                            'service_name' => $item->service_name,
                            'tracking_number' => $item->tracking_number,
                            'tracking_status' => $item->tracking_status,
                            'status' => $item->status,
                        ],
                    ],
                ];
            })->collect();
    }

    protected function mapSellbacks(int $userId, string $status = 'all')
    {
        if (! in_array($status, ['all', 'success'], true)) {
            return collect();
        }

        $items = SellbackTransaction::query()
            ->select(['id', 'user_id', 'inventory_id', 'wallet_transaction_id', 'created_at', 'amount'])
            ->with(['inventory.prize'])
            ->where('user_id', $userId)
            ->latest()->orderByDesc('id')
            ->get()
            ->groupBy(function (SellbackTransaction $item) {
                return $item->wallet_transaction_id ?: 'sellback-'.$item->id;
            });

        return $items->map(function ($group, $groupKey) {
            $first = $group->first();

            $groupedItems = $group
                ->groupBy('inventory.gacha_prize_id')
                ->map(function ($samePrizeGroup) {
                    $firstItem = $samePrizeGroup->first();
                    $inventory = $firstItem->inventory;
                    $prize = $inventory?->prize;

                    return [
                        'name' => $inventory?->prize_name_snapshot,
                        'image' => $inventory?->prize_image_snapshot ?? $prize?->image,
                        'rarity' => strtoupper($prize?->rarity_code ?? 'SILVER'),
                        'qty' => $samePrizeGroup->count(),
                        'sellback_price' => (float) ($inventory?->sellback_price ?? 0),
                    ];
                })
                ->values();

            $totalAmount = (float) $group->sum('amount');
            $totalQty = (int) $group->count();
            $estimatedMarket = (float) $group->sum(function ($row) {
                return (float) ($row->inventory?->prize?->market_price ?? 0);
            });

            return [
                'id' => 'sellback-'.$groupKey,
                'service' => 'sellback',
                'service_label' => 'Jual Kembali',
                'icon' => 'bi bi-credit-card-2-front',
                'status_key' => 'success',
                'status_label' => 'Berhasil',
                'created_at' => optional($first->created_at)?->toDateTimeString(),
                'amount' => $totalAmount,
                'amount_prefix' => '+',
                'order_no' => 'SB-'.$groupKey,
                'summary' => $totalQty.' Pack',
                'sections' => [
                    'sellback' => [
                        'items' => $groupedItems,
                        'rows' => [
                            [
                                'label' => 'Total Harga',
                                'value' => 'Rp '.number_format($estimatedMarket * 1000, 0, ',', '.'),
                                'is_coin' => false,
                            ],
                            [
                                'label' => 'Total Penjualan',
                                'value' => number_format($totalAmount, 0, ',', '.'),
                                'is_coin' => true,
                            ],
                        ],
                    ],
                ],
            ];
        })->values();
    }

    protected function mapPurchases(int $userId, string $status = 'all')
    {
        return DrawTransaction::query()
            ->select(['id', 'user_id', 'status', 'created_at', 'total_cost', 'draw_no', 'draw_count'])
            ->with(['results:id,draw_transaction_id,gacha_prize_id,prize_name_snapshot,prize_image_snapshot,rarity_code,sellback_price_snapshot'])
            ->when($status !== 'all', fn ($query) => $query->whereRaw("LOWER(COALESCE(status, '')) = ?", [$status]))
            ->where('user_id', $userId)
            ->latest()->orderByDesc('id')
            ->pipe(fn ($query) => $this->historyRows($query))
            ->map(function (DrawTransaction $item) {
                $groupedItems = $item->results
                    ->groupBy('gacha_prize_id')
                    ->map(function ($samePrizeGroup) {
                        $first = $samePrizeGroup->first();

                        return [
                            'name' => $first->prize_name_snapshot,
                            'image' => $this->imageUrl($first->prize_image_snapshot ?? null),
                            'rarity' => strtoupper($first->rarity_code ?? 'SILVER'),
                            'qty' => $samePrizeGroup->count(),
                            'sellback_price' => (float) ($first->sellback_price_snapshot ?? 0),
                        ];
                    })
                    ->values();

                $totalSellback = (float) $item->results->sum('sellback_price_snapshot');

                return [
                    'id' => 'purchase-'.$item->id,
                    'service' => 'purchase',
                    'service_label' => 'Pembelian',
                    'icon' => 'bi bi-cart3',
                    'status_key' => $item->status === 'success' ? 'success' : strtolower((string) $item->status),
                    'status_label' => $item->status === 'success' ? 'Berhasil' : ucfirst((string) $item->status),
                    'created_at' => optional($item->created_at)?->toDateTimeString(),
                    'amount' => (float) $item->total_cost,
                    'amount_prefix' => '-',
                    'order_no' => $item->draw_no,
                    'summary' => $item->status === 'success' ? 'Berhasil' : ucfirst((string) $item->status),
                    'sections' => [
                        'purchase' => [
                            'items' => $groupedItems,
                            'rows' => [
                                [
                                    'label' => 'Total Pembelian',
                                    'value' => $item->draw_count.' Pack',
                                ],
                                [
                                    'label' => 'Total Harga',
                                    'value' => 'Rp '.number_format((float) $item->total_cost * 1000, 0, ',', '.'),
                                ],
                                [
                                    'label' => 'Total Penjualan',
                                    'value' => number_format($totalSellback, 0, ',', '.'),
                                    'is_coin' => true,
                                ],
                            ],
                        ],
                    ],
                ];
            })->collect();
    }

    private function historyRows($query): LazyCollection
    {
        return new LazyCollection(function () use ($query) {
            $cursor = null;
            do {
                $batchQuery = clone $query;
                if ($cursor !== null) {
                    [$createdAt, $id] = $cursor;
                    $batchQuery->where(function ($where) use ($createdAt, $id) {
                        if ($createdAt === null) {
                            $where->whereNull('created_at')->where('id', '<', $id);
                        } else {
                            $where->where('created_at', '<', $createdAt)
                                ->orWhereNull('created_at')
                                ->orWhere(fn ($sameTime) => $sameTime->where('created_at', $createdAt)->where('id', '<', $id));
                        }
                    });
                }
                $batch = $batchQuery->limit(200)->get();
                foreach ($batch as $row) {
                    yield $row;
                }
                if ($batch->isNotEmpty()) {
                    $last = $batch->last();
                    $cursor = [$last->getRawOriginal('created_at'), $last->id];
                }
            } while ($batch->count() === 200);
        });
    }

    private function filterStatus($query, string $status, array $mapping): void
    {
        if ($status === 'all') {
            return;
        }
        $column = DB::raw("LOWER(COALESCE(status, ''))");
        if ($status === 'other') {
            $query->whereNotIn($column, array_keys($mapping));
        } else {
            $query->whereIn($column, array_keys($mapping, $status, true));
        }
    }

    protected function normalizePaymentStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'paid' => 'success',
            'pending' => 'pending',
            'failed' => 'failed',
            'expired' => 'expired',
            'cancelled' => 'cancelled',
            default => 'other',
        };
    }

    protected function paymentStatusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'paid' => 'Sukses',
            'pending' => 'Belum dibayar',
            'failed' => 'Pembayaran gagal',
            'expired' => 'Expired',
            'cancelled' => 'Dibatalkan',
            default => ucfirst((string) $status),
        };
    }

    protected function normalizeShipmentStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'requested', 'pending_payment' => 'pending',
            'paid', 'processing', 'packed' => 'processing',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'booking_failed', 'failed' => 'failed',
            'cancelled' => 'cancelled',
            default => 'other',
        };
    }

    protected function shipmentStatusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'requested', 'pending_payment' => 'Menunggu pembayaran',
            'paid' => 'Pembayaran tervalidasi',
            'processing' => 'Sedang diproses',
            'packed' => 'Paket sedang disiapkan',
            'shipped' => 'Sedang dikirim',
            'delivered' => 'Tiba di tujuan',
            'booking_failed', 'failed' => 'Booking pengiriman gagal',
            'cancelled' => 'Dibatalkan',
            default => ucfirst((string) $status),
        };
    }
}
