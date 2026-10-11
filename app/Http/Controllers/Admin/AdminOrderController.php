<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShipmentOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminOrderController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $period = (string) $request->query('period', 'day');

        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $date = Carbon::parse((string) $request->query('date', now()->toDateString()));

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);
        [$previousStart, $previousEnd] = $this->previousDateRange($period, $date);

        $paginator = $this->buildOrderQuery($search, $status)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->paginate(9)
            ->withQueryString();

        $orders = $paginator
            ->getCollection()
            ->map(fn (ShipmentOrder $order) => $this->mapOrder($order))
            ->values();

        $currentSummaryOrders = ShipmentOrder::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(fn (ShipmentOrder $order) => [
                'status' => $this->normalizeUiStatus($order->status, $order->tracking_status),
            ]);

        $previousSummaryOrders = ShipmentOrder::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->get()
            ->map(fn (ShipmentOrder $order) => [
                'status' => $this->normalizeUiStatus($order->status, $order->tracking_status),
            ]);

        return Inertia::render('Admin/Orders/Index', [
            'summary' => $this->summary($currentSummaryOrders, $previousSummaryOrders),
            'orders' => $orders,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'period' => $period,
                'date' => $date->toDateString(),
                'date_label' => $this->dateLabel($startDate, $endDate),
                'comparison_label' => $this->comparisonLabel($period),
            ],
            'statusOptions' => [
                ['label' => 'New Orders', 'value' => 'new'],
                ['label' => 'In Progress', 'value' => 'in_progress'],
                ['label' => 'Problem', 'value' => 'problem'],
                ['label' => 'Cancelled', 'value' => 'cancelled'],
                ['label' => 'Shipment Success', 'value' => 'success'],
            ],
        ]);
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $period = (string) $request->query('period', 'day');

        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $date = Carbon::parse((string) $request->query('date', now()->toDateString()));

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);

        $orders = $this->buildOrderQuery($search, $status)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->get()
            ->map(fn (ShipmentOrder $order) => $this->mapOrder($order))
            ->values();

        $filename = 'shipment-orders-' . now()->format('Ymd-His') . '.csv';

        $callback = function () use ($orders) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Order No',
                'Customer',
                'Phone',
                'Email',
                'Payment Method',
                'Total Payment',
                'Courier',
                'Tracking Number',
                'Tracking Status',
                'Status',
            ]);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order['order_no'],
                    $order['customer']['name'],
                    $order['customer']['phone'],
                    $order['customer']['email'],
                    $order['payment_method'],
                    $order['total_payment'],
                    $order['shipping']['courier'],
                    $order['shipping']['vehicle'],
                    $order['shipping']['tracking_status'],
                    $order['status_label'],
                ]);
            }

            fclose($handle);
        };

        return Response::streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function buildOrderQuery(string $search, string $status)
    {
        return ShipmentOrder::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->with([
                'user:id,name,email,phone,avatar',
                'paymentTransaction:id,order_no,payment_type,gross_amount,status,paid_at',
                'items:id,shipment_order_id,inventory_id,prize_name_snapshot,qty',
                'items.inventory:id,prize_image_snapshot,sellback_price',
                'trackingEvents:id,shipment_order_id,status_code,status_label,description,source,event_time',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_no', 'like', "%{$search}%")
                        ->orWhere('recipient_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('recipient_phone_snapshot', 'like', "%{$search}%")
                        ->orWhere('courier_name', 'like', "%{$search}%")
                        ->orWhere('service_name', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%")
                        ->orWhere('external_order_id', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('tracking_status', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== 'all', function ($query) use ($status) {
                $databaseStatuses = $this->databaseStatusesForFilter($status);

                /*
                 * Penting:
                 * Filter status hanya memakai shipment_orders.status.
                 * Jadi walaupun tracking_status masih confirmed,
                 * kalau status = shipped, dia masuk In Progress.
                 */
                $query->whereIn('status', $databaseStatuses);
            });
    }

    private function mapOrder(ShipmentOrder $order): array
    {
        $address = $this->asArray($order->address_snapshot_json);
        $externalResponse = $this->asArray($order->external_response_json);

        $destinationAddress = data_get($externalResponse, 'destination.address')
            ?: ($address['full_address'] ?? '-');

        $originAddress = data_get($externalResponse, 'origin.address')
            ?: 'Catkuji Warehouse';

        $customerEmail = data_get($externalResponse, 'destination.contact_email')
            ?: $order->user?->email
            ?: '-';

        $items = $order->items
            ->map(fn ($item) => $this->mapOrderItem($item))
            ->values();

        $tracking = $order->trackingEvents
            ->map(fn ($event) => $this->mapTrackingEvent($event))
            ->values();

        if ($tracking->isEmpty()) {
            $tracking = collect([
                [
                    'title' => $this->trackingStatusLabel($order->tracking_status ?: $order->status),
                    'location' => $this->orderNote($order),
                    'date' => $order->created_at?->translatedFormat('d M Y') ?? '-',
                    'time' => $order->created_at?->format('h:i A') ?? '-',
                    'active' => true,
                ],
            ]);
        }

        /*
         * Badge dan filter memakai group sederhana:
         * new / in_progress / success.
         *
         * Sumber penentu utamanya adalah shipment_orders.status,
         * bukan tracking_status.
         */
        $uiStatus = $this->normalizeUiStatus($order->status, null);

        return [
            'id' => $order->id,
            'order_no' => $order->order_no,

            /*
             * Dipakai frontend untuk warna badge dan filter.
             * Nilainya hanya: new, in_progress, success.
             */
            'status' => $uiStatus,

            /*
             * Tetap dikirim untuk kebutuhan detail/debug,
             * tapi bukan untuk label badge utama.
             */
            'status_raw' => $order->status,
            'tracking_status_raw' => $order->tracking_status,
            'payment_status' => $order->paymentTransaction?->status,
            'external_order_id' => $order->external_order_id,
            'status_label' => $this->orderBadgeLabel($uiStatus),

            'customer' => [
                'name' => $order->recipient_name_snapshot
                    ?: $order->user?->name
                    ?: 'Unknown Customer',
                'phone' => $order->recipient_phone_snapshot
                    ?: $order->user?->phone
                    ?: '-',
                'email' => $customerEmail,
                'avatar' => $this->avatarUrl($order->user?->avatar, $order->user?->name),
            ],

            'payment_method' => $this->paymentMethodLabel($order->paymentTransaction?->payment_type),
            'total_payment' => (float) (
                $order->paymentTransaction?->gross_amount
                ?: $order->shipping_cost
                ?: 0
            ),
            'notes' => $this->orderNote($order),
            'items' => $items->toArray(),

            'shipping' => [
                'estimated_date' => $this->estimatedDeliveryDate($order),
                'origin' => $originAddress,
                'destination' => $destinationAddress,
                'courier' => trim(
                    ($order->courier_name ?: '-')
                    . ($order->service_name ? ' ' . $order->service_name : '')
                ),
                'vehicle' => $order->tracking_number
                    ? 'Resi - ' . $order->tracking_number
                    : ($order->external_order_id ? 'Order - ' . $order->external_order_id : '-'),
                'tracking_status' => $order->tracking_status ?: $order->status,
                'tracking' => $tracking->toArray(),
            ],
        ];
    }

    private function mapOrderItem($item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->prize_name_snapshot,
            'quantity' => (int) $item->qty,
            'draw_count' => 1,
            'price' => (float) ($item->inventory?->sellback_price ?: 0),
            'image' => $this->imageUrl($item->inventory?->prize_image_snapshot),
            'inventory_id' => $item->inventory_id,
        ];
    }

    private function mapTrackingEvent($event): array
    {
        $eventTime = $event->event_time
            ? Carbon::parse($event->event_time)
            : null;

        return [
            'title' => $event->status_label
                ?: $this->trackingStatusLabel($event->status_code),
            'location' => $event->description ?: '-',
            'date' => $eventTime
                ? $eventTime->translatedFormat('d M Y')
                : '-',
            'time' => $eventTime
                ? $eventTime->format('h:i A')
                : '-',
            'active' => true,
            'source' => $event->source,
            'status_code' => $event->status_code,
        ];
    }

    private function summary(Collection $orders, Collection $previousOrders): array
    {
        $totalOrders = $orders->count();
        $newOrders = $orders->where('status', 'new')->count();
        $inProgress = $orders->where('status', 'in_progress')->count();
        $success = $orders->where('status', 'success')->count();

        $previousTotalOrders = $previousOrders->count();
        $previousNewOrders = $previousOrders->where('status', 'new')->count();
        $previousInProgress = $previousOrders->where('status', 'in_progress')->count();
        $previousSuccess = $previousOrders->where('status', 'success')->count();

        $successRate = $totalOrders > 0
            ? round(($success / $totalOrders) * 100, 1)
            : 0;

        $previousSuccessRate = $previousTotalOrders > 0
            ? round(($previousSuccess / $previousTotalOrders) * 100, 1)
            : 0;

        return [
            [
                'label' => 'Total Orders',
                'value' => number_format($totalOrders, 0, ',', '.'),
                'growth' => $this->calculateGrowth($totalOrders, $previousTotalOrders),
                'icon' => 'bi-truck',
            ],
            [
                'label' => 'New Orders',
                'value' => number_format($newOrders, 0, ',', '.'),
                'growth' => $this->calculateGrowth($newOrders, $previousNewOrders),
                'icon' => 'bi-box-seam',
            ],
            [
                'label' => 'In Progress',
                'value' => number_format($inProgress, 0, ',', '.'),
                'growth' => $this->calculateGrowth($inProgress, $previousInProgress),
                'icon' => 'bi-clipboard-check',
            ],
            [
                'label' => 'Shipment Success',
                'value' => $successRate . '%',
                'growth' => $this->calculateGrowth($successRate, $previousSuccessRate),
                'icon' => 'bi-check2-circle',
            ],
        ];
    }

    private function resolveDateRange(string $period, Carbon $date): array
    {
        return match ($period) {
            'week' => [
                $date->copy()->startOfWeek()->startOfDay(),
                $date->copy()->endOfWeek()->endOfDay(),
            ],
            'month' => [
                $date->copy()->startOfMonth()->startOfDay(),
                $date->copy()->endOfMonth()->endOfDay(),
            ],
            default => [
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
            ],
        };
    }

    private function previousDateRange(string $period, Carbon $date): array
    {
        return match ($period) {
            'week' => [
                $date->copy()->subWeek()->startOfWeek()->startOfDay(),
                $date->copy()->subWeek()->endOfWeek()->endOfDay(),
            ],
            'month' => [
                $date->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                $date->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
            ],
            default => [
                $date->copy()->subDay()->startOfDay(),
                $date->copy()->subDay()->endOfDay(),
            ],
        };
    }

    private function dateLabel(Carbon $startDate, Carbon $endDate): string
    {
        if ($startDate->isSameDay($endDate)) {
            return $startDate->translatedFormat('j F Y');
        }

        return $startDate->translatedFormat('j M') . ' - ' . $endDate->translatedFormat('j M Y');
    }

    private function comparisonLabel(string $period): string
    {
        return match ($period) {
            'week' => 'Last Week',
            'month' => 'Last Month',
            default => 'Last Day',
        };
    }

    private function calculateGrowth(float|int $current, float|int $previous): float
    {
        if ((float) $previous <= 0) {
            return (float) $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
    /*
     * Ini tetap seperti sebelumnya.
     * Group status:
     * - new
     * - in_progress
     * - success
     */
    private function databaseStatusesForFilter(string $status): array
    {
        return match ($status) {
            'new' => [
                'requested',
                'pending',
                'pending_payment',
                'paid',
                'confirmed',
            ],
            'in_progress' => [
                'processing',
                'allocated',
                'packed',
                'shipped',
                'picking_up',
                'picked',
                'dropping_off',
                'on_delivery',
            ],
            'problem' => [
                'booking_failed',
                'failed',
            ],
            'cancelled' => [
                'cancelled',
                'canceled',
            ],
            'success' => [
                'success',
                'delivered',
                'completed',
                'done',
            ],
            default => [],
        };
    }

    /*
     * Status UI juga memakai mapping yang sama seperti databaseStatusesForFilter().
     * Sumber utama: shipment_orders.status.
     */
    private function normalizeUiStatus(?string $status, ?string $trackingStatus): string
    {
        $value = strtolower((string) ($status ?: $trackingStatus));

        if (in_array($value, [
            'success',
            'delivered',
            'completed',
            'done',
        ], true)) {
            return 'success';
        }

        if (in_array($value, [
            'booking_failed',
            'failed',
        ], true)) {
            return 'problem';
        }

        if (in_array($value, [
            'cancelled',
            'canceled',
        ], true)) {
            return 'cancelled';
        }

        if (in_array($value, [
            'processing',
            'allocated',
            'packed',
            'shipped',
            'picking_up',
            'picked',
            'dropping_off',
            'on_delivery',
        ], true)) {
            return 'in_progress';
        }

        return 'new';
    }

    private function orderBadgeLabel(?string $status): string
    {
        return match ($status) {
            'new' => 'New',
            'in_progress' => 'In Progress',
            'problem' => 'Problem',
            'cancelled' => 'Cancelled',
            'success' => 'Shipment Success',
            default => 'New',
        };
    }

    private function paymentMethodLabel(?string $paymentType): string
    {
        return match ($paymentType) {
            'bank_transfer' => 'Virtual Account (VA)',
            'qris' => 'E-Wallet & QRIS',
            'gopay', 'shopeepay' => 'E-Wallet',
            'cstore' => 'Retail / Gerai Offline',
            'credit_card' => 'Credit Card',
            null => 'Shipping Payment',
            default => str_replace('_', ' ', ucfirst($paymentType)),
        };
    }

    private function trackingStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending_payment' => 'Menunggu pembayaran ongkir',
            'paid' => 'Pembayaran tervalidasi',
            'confirmed' => 'Order dikonfirmasi',
            'processing' => 'Pesanan sedang diproses',
            'allocated' => 'Kurir dialokasikan',
            'picking_up' => 'Kurir menuju pickup',
            'picked' => 'Paket sudah diambil',
            'dropping_off' => 'Dalam perjalanan',
            'delivered' => 'Order Delivered',
            'cancelled', 'canceled' => 'Order dibatalkan',
            default => $status ? str_replace('_', ' ', ucfirst($status)) : 'Tracking Update',
        };
    }

    private function orderNote(ShipmentOrder $order): string
    {
        if ($order->delivered_at) {
            return 'Pesanan sudah berhasil diterima customer.';
        }

        if ($order->shipped_at) {
            return 'Pesanan sedang dalam proses pengiriman.';
        }

        if ($order->packed_at) {
            return 'Pesanan sudah dikemas dan siap dikirim.';
        }

        if ($order->paid_at) {
            return 'Pembayaran ongkir sudah diterima.';
        }

        if ($order->cancelled_at) {
            return 'Pesanan dibatalkan.';
        }

        if ($order->booking_failed_at) {
            return 'Booking pengiriman gagal. Silakan cek response Biteship.';
        }

        return 'Pesanan shipment dibuat dan menunggu proses berikutnya.';
    }

    private function estimatedDeliveryDate(ShipmentOrder $order): string
    {
        $baseDate = $order->delivered_at
            ?: $order->shipped_at
            ?: $order->packed_at
            ?: $order->paid_at
            ?: $order->requested_at
            ?: $order->created_at;

        if (! $baseDate) {
            return '-';
        }

        return Carbon::parse($baseDate)
            ->addDays(3)
            ->translatedFormat('d F Y');
    }

    private function avatarUrl(?string $avatar, ?string $name): string
    {
        if ($avatar) {
            if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
                return $avatar;
            }

            if (str_starts_with($avatar, '/storage/')) {
                return $avatar;
            }

            if (str_starts_with($avatar, 'storage/')) {
                return asset($avatar);
            }

            return asset('storage/' . ltrim($avatar, '/'));
        }

        return 'https://ui-avatars.com/api/?name='
            . urlencode($name ?: 'User')
            . '&background=343531&color=ffffff';
    }

    private function imageUrl(?string $path): string
    {
        if (! $path) {
            return '/images/placeholder-prize.png';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    private function asArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! $value) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}