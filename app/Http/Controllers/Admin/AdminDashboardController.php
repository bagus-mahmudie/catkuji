<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DrawTransaction;
use App\Models\GachaEvent;
use App\Models\PaymentTransaction;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Models\GachaCategory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $coinRate = (int) config('gacha.coin_rate', 1000);

        $period = (string) $request->query('period', 'day');
        $date = $period === 'day'
            ? Carbon::parse((string) $request->query('date', now()->toDateString()))
            : now();
        $search = trim((string) $request->query('search', ''));
        $requestedTrend = $request->query('trend');

        $trend = in_array($requestedTrend, ['weekly', 'monthly', 'yearly'], true)
            ? (string) $requestedTrend
            : 'monthly';

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);
        [$previousStart, $previousEnd] = $this->previousDateRange($period, $date);

        $currentRevenueBreakdown = $this->buildRevenueBreakdown(
            $coinRate,
            $startDate,
            $endDate
        );

        $previousRevenueBreakdown = $this->buildRevenueBreakdown(
            $coinRate,
            $previousStart,
            $previousEnd
        );

        $currentRevenue = collect($currentRevenueBreakdown)->sum('amount');
        $previousRevenue = collect($previousRevenueBreakdown)->sum('amount');

        $currentTopUp = (clone $this->dashboardTopUpQuery($startDate, $endDate, $search))
            ->whereIn('status', ['paid', 'success', 'complete', 'completed'])
            ->sum('gross_amount');

        $previousTopUp = (clone $this->dashboardTopUpQuery($previousStart, $previousEnd, $search))
            ->whereIn('status', ['paid', 'success', 'complete', 'completed'])
            ->sum('gross_amount');

        $currentProducts = GachaEvent::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $previousProducts = GachaEvent::query()
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        $currentUsers = User::query()
            ->where('role', 'user')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $previousUsers = User::query()
            ->where('role', 'user')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        $currentTransactions = (clone $this->dashboardPaymentQuery($startDate, $endDate, $search))
            ->count();

        $previousTransactions = (clone $this->dashboardPaymentQuery($previousStart, $previousEnd, $search))
            ->count();

        $salesTrend = $this->buildSalesTrend($trend, $date, $coinRate);

        $revenueBreakdown = $currentRevenueBreakdown;

        $recentTransactions = $this->recentTransactions(
            $startDate,
            $endDate,
            $search
        );

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                [
                    'label' => 'Total Top Up',
                    'value' => (float) $currentTopUp,
                    'type' => 'currency',
                    'icon' => 'bi-wallet2',
                    'growth' => $this->calculateGrowth($currentTopUp, $previousTopUp),
                ],
                [
                    'label' => 'Total Product',
                    'value' => GachaEvent::query()->count(),
                    'type' => 'number',
                    'icon' => 'bi-box-seam',
                    'growth' => $this->calculateGrowth($currentProducts, $previousProducts),
                ],
                [
                    'label' => 'Total Users',
                    'value' => User::query()->where('role', 'user')->count(),
                    'type' => 'number',
                    'icon' => 'bi-person',
                    'growth' => $this->calculateGrowth($currentUsers, $previousUsers),
                ],
                [
                    'label' => 'Total Transaksi',
                    'value' => $currentTransactions,
                    'type' => 'number',
                    'icon' => 'bi-receipt',
                    'growth' => $this->calculateGrowth($currentTransactions, $previousTransactions),
                ],
            ],
            'summary' => [
                'active_events' => GachaEvent::query()->where('status', 'published')->count(),
                'pending_payments' => PaymentTransaction::query()->where('status', 'pending')->count(),
                'pending_shipments' => ShipmentOrder::query()
                    ->whereIn('status', ['pending_payment', 'paid', 'ready_to_ship', 'requested', 'processing'])
                    ->count(),
                'successful_draws' => DrawTransaction::query()
                    ->where('status', 'success')
                    ->whereHas('user', fn ($query) => $query->where('role', 'user'))
                    ->count(),
                'total_revenue' => (float) $currentTopUp,
            ],
            'salesTrend' => $salesTrend,
            'revenueBreakdown' => $revenueBreakdown,
            'recentTransactions' => $recentTransactions,
            'filters' => [
                'period' => $period,
                'date' => $date->toDateString(),
                'search' => $search,
                'trend' => $trend,
                'date_label' => $this->dateLabel($startDate, $endDate),
                'comparison_label' => $this->comparisonLabel($period),
            ],
        ]);
    }

    private function dashboardTopUpQuery(Carbon $startDate, Carbon $endDate, string $search = '')
    {
        return $this->dashboardPaymentQuery($startDate, $endDate, $search)
            ->where(function ($query) {
                $query
                    ->where('order_no', 'like', 'TOPUP-%')
                    ->orWhere('order_no', 'like', 'TOPUP_%');
            });
    }

    public function export(Request $request): StreamedResponse
    {
        $period = (string) $request->query('period', 'day');
        $date = Carbon::parse((string) $request->query('date', now()->toDateString()));
        $search = trim((string) $request->query('search', ''));

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);

        $transactions = PaymentTransaction::query()
            ->with('user:id,name,email')
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_no', 'like', "%{$search}%")
                        ->orWhere('payment_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->get();

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Order ID',
                'Customer',
                'Email',
                'Amount',
                'Payment Method',
                'Date',
                'Status',
            ]);

            foreach ($transactions as $transaction) {
                fputcsv($handle, [
                    $transaction->order_no,
                    $transaction->user?->name ?? 'Unknown User',
                    $transaction->user?->email ?? '-',
                    $transaction->gross_amount,
                    $this->resolvePaymentMethodLabel($transaction->payment_type),
                    optional($transaction->created_at)->format('Y-m-d H:i:s'),
                    $transaction->status,
                ]);
            }

            fclose($handle);
        }, 'dashboard-transactions.csv');
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
            'year' => [
                $date->copy()->startOfYear()->startOfDay(),
                $date->copy()->endOfYear()->endOfDay(),
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
            'year' => [
                $date->copy()->subYearNoOverflow()->startOfYear()->startOfDay(),
                $date->copy()->subYearNoOverflow()->endOfYear()->endOfDay(),
            ],
            default => [
                $date->copy()->subDay()->startOfDay(),
                $date->copy()->subDay()->endOfDay(),
            ],
        };
    }

    private function defaultTrendFromPeriod(string $period): string
    {
        return match ($period) {
            'month' => 'monthly',
            'year' => 'yearly',
            default => 'weekly',
        };
    }

    private function buildSalesTrend(string $trend, Carbon $baseDate, int $coinRate): array
    {
        return match ($trend) {
            'weekly' => $this->weeklySalesTrend($baseDate, $coinRate),
            'yearly' => $this->yearlySalesTrend($baseDate, $coinRate),
            default => $this->monthlySalesTrend($baseDate, $coinRate),
        };
    }

    private function weeklySalesTrend(Carbon $baseDate, int $coinRate): array
    {
        $startDate = $baseDate->copy()->startOfWeek()->startOfDay();
        $endDate = $baseDate->copy()->endOfWeek()->endOfDay();

        $draws = DrawTransaction::query()
            ->selectRaw('DATE(created_at) as period_key')
            ->selectRaw('SUM(total_cost) as total_coin')
            ->where('status', 'success')
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('period_key');

        $users = User::query()
            ->selectRaw('DATE(created_at) as period_key')
            ->selectRaw('COUNT(*) as total_users')
            ->where('role', 'user')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('period_key');

        $items = [];
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            $key = $cursor->toDateString();

            $items[] = [
                'month' => $cursor->translatedFormat('D'),
                'revenue' => (float) (($draws->get($key)?->total_coin ?? 0) * $coinRate),
                'new_users' => (int) ($users->get($key)?->total_users ?? 0),
                'existing_users' => User::query()
                    ->where('role', 'user')
                    ->where('created_at', '<', $cursor->copy()->startOfDay())
                    ->count(),
            ];

            $cursor->addDay();
        }

        return $items;
    }

    private function monthlySalesTrend(Carbon $baseDate, int $coinRate): array
    {
        $year = $baseDate->year;

        $draws = DrawTransaction::query()
            ->selectRaw('MONTH(created_at) as period_key')
            ->selectRaw('SUM(total_cost) as total_coin')
            ->where('status', 'success')
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('period_key');

        $users = User::query()
            ->selectRaw('MONTH(created_at) as period_key')
            ->selectRaw('COUNT(*) as total_users')
            ->where('role', 'user')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('period_key');

        return collect(range(1, 12))
            ->map(function (int $month) use ($draws, $users, $year, $coinRate) {
                return [
                    'month' => Carbon::create($year, $month, 1)->format('M'),
                    'revenue' => (float) (($draws->get($month)?->total_coin ?? 0) * $coinRate),
                    'new_users' => (int) ($users->get($month)?->total_users ?? 0),
                    'existing_users' => User::query()
                        ->where('role', 'user')
                        ->where('created_at', '<', Carbon::create($year, $month, 1)->startOfMonth())
                        ->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function onlyUserRole($query)
    {
        return $query->whereHas('user', function ($userQuery) {
            $userQuery->where('role', 'user');
        });
    }

    private function yearlySalesTrend(Carbon $baseDate, int $coinRate): array
    {
        $startYear = $baseDate->copy()->subYears(5)->year;
        $endYear = $baseDate->year;

        $draws = DrawTransaction::query()
            ->selectRaw('YEAR(created_at) as period_key')
            ->selectRaw('SUM(total_cost) as total_coin')
            ->where('status', 'success')
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->whereBetween(DB::raw('YEAR(created_at)'), [$startYear, $endYear])
            ->groupByRaw('YEAR(created_at)')
            ->get()
            ->keyBy('period_key');

        $users = User::query()
            ->selectRaw('YEAR(created_at) as period_key')
            ->selectRaw('COUNT(*) as total_users')
            ->where('role', 'user')
            ->whereBetween(DB::raw('YEAR(created_at)'), [$startYear, $endYear])
            ->groupByRaw('YEAR(created_at)')
            ->get()
            ->keyBy('period_key');

        return collect(range($startYear, $endYear))
            ->map(function (int $year) use ($draws, $users, $coinRate) {
                return [
                    'month' => (string) $year,
                    'revenue' => (float) (($draws->get($year)?->total_coin ?? 0) * $coinRate),
                    'new_users' => (int) ($users->get($year)?->total_users ?? 0),
                    'existing_users' => User::query()
                        ->where('role', 'user')
                        ->where('created_at', '<', Carbon::create($year, 1, 1)->startOfYear())
                        ->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function buildRevenueBreakdown(int $coinRate, Carbon $startDate, Carbon $endDate): array
    {
        $rows = GachaCategory::query()
            ->leftJoin('gacha_events', 'gacha_categories.id', '=', 'gacha_events.category_id')
            ->leftJoin('draw_transactions', function ($join) use ($startDate, $endDate) {
                $join->on('gacha_events.id', '=', 'draw_transactions.gacha_event_id')
                    ->where('draw_transactions.status', '=', 'success')
                    ->whereBetween('draw_transactions.created_at', [$startDate, $endDate])
                    ->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('users')
                            ->whereColumn('users.id', 'draw_transactions.user_id')
                            ->where('users.role', 'user');
                    });
            })
            ->select([
                'gacha_categories.id',
                'gacha_categories.name',
            ])
            ->selectRaw('COALESCE(SUM(draw_transactions.total_cost), 0) as total_coin')
            ->groupBy('gacha_categories.id', 'gacha_categories.name')
            ->orderBy('gacha_categories.name')
            ->get();

        $total = max(1, (int) $rows->sum('total_coin'));

        return $rows
            ->map(fn ($row) => [
                'category' => $row->name,
                'amount' => (int) $row->total_coin * $coinRate,
                'percentage' => round(((int) $row->total_coin / $total) * 100, 1),
            ])
            ->values()
            ->all();
    }

    private function dashboardPaymentQuery(Carbon $startDate, Carbon $endDate, string $search = '')
    {
        return PaymentTransaction::query()
            ->with('user:id,name,email')
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_no', 'like', "%{$search}%")
                        ->orWhere('payment_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });
    }

    private function recentTransactions(Carbon $startDate, Carbon $endDate, string $search)
    {
        return $this->dashboardPaymentQuery($startDate, $endDate, $search)
            ->latest()
            ->paginate(8)
            ->withQueryString()
            ->through(fn (PaymentTransaction $transaction) => [
                'id' => $transaction->id,
                'order_no' => $transaction->order_no,
                'customer' => $transaction->user?->name ?? 'Unknown User',
                'amount' => (float) $transaction->gross_amount,
                'payment_method' => $this->resolvePaymentMethodLabel($transaction->payment_type),
                'date' => $transaction->created_at?->translatedFormat('d M Y'),
                'status' => $this->statusLabel($transaction->status),
                'raw_status' => $transaction->status,
            ]);
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
            'year' => 'Last Year',
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

    private function statusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'paid', 'success', 'complete', 'completed' => 'Complete',
            'pending', 'processing' => 'Processing',
            'failed', 'expired', 'cancelled', 'canceled' => 'Canceled',
            default => $status ? ucfirst(str_replace('_', ' ', $status)) : '-',
        };
    }

    private function resolvePaymentMethodLabel(?string $paymentType): string
    {
        return match ($paymentType) {
            'bank_transfer' => 'Virtual Account (VA)',
            'qris' => 'E-Wallet & QRIS',
            'gopay', 'shopeepay' => 'E-Wallet',
            'cstore' => 'Retail / Gerai Offline',
            'credit_card' => 'Credit Card',
            default => $paymentType ? str_replace('_', ' ', ucfirst($paymentType)) : '-',
        };
    }
}