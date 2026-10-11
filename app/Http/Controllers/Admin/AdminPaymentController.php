<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminPaymentController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $period = (string) $request->query('period', 'day');
        $date = (string) $request->query('date', now()->toDateString());
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $method = (string) $request->query('method', 'all');
        $sort = (string) $request->query('sort', 'latest');

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);
        [$previousStart, $previousEnd] = $this->previousDateRange($period, $date);

        $query = PaymentTransaction::query()
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
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($method !== 'all', function ($query) use ($method) {
                $paymentTypes = $this->paymentTypesForMethod($method);

                if (! empty($paymentTypes)) {
                    $query->whereIn('payment_type', $paymentTypes);
                }
            });

        match ($sort) {
            'oldest' => $query->oldest(),
            'amount_highest' => $query->orderByDesc('gross_amount'),
            'amount_lowest' => $query->orderBy('gross_amount'),
            'customer_az' => $query
                ->leftJoin('users', 'payment_transactions.user_id', '=', 'users.id')
                ->select('payment_transactions.*')
                ->orderBy('users.name'),
            'customer_za' => $query
                ->leftJoin('users', 'payment_transactions.user_id', '=', 'users.id')
                ->select('payment_transactions.*')
                ->orderByDesc('users.name'),
            default => $query->latest(),
        };

        $rawTransactions = (clone $query)->get();

        $previousQuery = PaymentTransaction::query()
            ->with('user:id,name,email')
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->whereBetween('created_at', [$previousStart, $previousEnd])
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
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($method !== 'all', function ($query) use ($method) {
                $paymentTypes = $this->paymentTypesForMethod($method);

                if (! empty($paymentTypes)) {
                    $query->whereIn('payment_type', $paymentTypes);
                }
            });

        $previousTransactions = $previousQuery->get();

        $transactions = $query
            ->paginate(8)
            ->withQueryString()
            ->through(fn (PaymentTransaction $transaction) => $this->mapTransaction($transaction));

        return Inertia::render('Admin/Payments/Index', [
            'summary' => $this->summary($rawTransactions, $previousTransactions),
            'transactions' => $transactions,
            'filters' => [
                'period' => $period,
                'date' => $date,
                'search' => $search,
                'status' => $status,
                'method' => $method,
                'sort' => $sort,
                'comparison_label' => $this->comparisonLabel($period),
            ],
            'periodOptions' => [
                ['label' => 'Day', 'value' => 'day'],
                ['label' => 'Week', 'value' => 'week'],
                ['label' => 'Month', 'value' => 'month'],
            ],
            'statusOptions' => [
                ['label' => 'All Status', 'value' => 'all'],
                ['label' => 'Paid', 'value' => 'paid'],
                ['label' => 'Pending', 'value' => 'pending'],
                ['label' => 'Failed', 'value' => 'failed'],
                ['label' => 'Expired', 'value' => 'expired'],
                ['label' => 'Canceled', 'value' => 'canceled'],
            ],
            'methodOptions' => [
                ['label' => 'All Method', 'value' => 'all'],
                ['label' => 'Virtual Account', 'value' => 'va'],
                ['label' => 'Retail / Gerai Offline', 'value' => 'retail'],
                ['label' => 'E-Wallet & QRIS', 'value' => 'ewallet'],
                ['label' => 'Credit Card', 'value' => 'credit_card'],
            ],
            'sortOptions' => [
                ['label' => 'Newest', 'value' => 'latest'],
                ['label' => 'Oldest', 'value' => 'oldest'],
                ['label' => 'Amount Highest', 'value' => 'amount_highest'],
                ['label' => 'Amount Lowest', 'value' => 'amount_lowest'],
                ['label' => 'Customer A-Z', 'value' => 'customer_az'],
                ['label' => 'Customer Z-A', 'value' => 'customer_za'],
            ],
        ]);
    }

    public function export(Request $request)
    {
        $period = (string) $request->query('period', 'day');
        $date = (string) $request->query('date', now()->toDateString());
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $method = (string) $request->query('method', 'all');
        $sort = (string) $request->query('sort', 'latest');

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);

        $query = PaymentTransaction::query()
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
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($method !== 'all', function ($query) use ($method) {
                $paymentTypes = $this->paymentTypesForMethod($method);

                if (! empty($paymentTypes)) {
                    $query->whereIn('payment_type', $paymentTypes);
                }
            });

        match ($sort) {
            'oldest' => $query->oldest(),
            'amount_highest' => $query->orderByDesc('gross_amount'),
            'amount_lowest' => $query->orderBy('gross_amount'),
            default => $query->latest(),
        };

        $rows = $query
            ->get()
            ->map(fn (PaymentTransaction $transaction) => $this->mapTransaction($transaction))
            ->values();

        $filename = 'finance-transactions-' . now()->format('Ymd-His') . '.csv';

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Order No',
                'Customer',
                'Amount',
                'Payment Method',
                'Date',
                'Status',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['order_no'],
                    $row['customer'],
                    $row['amount'],
                    $row['payment_method'],
                    $row['date'],
                    $row['status'],
                ]);
            }

            fclose($handle);
        };

        return Response::streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function resolveDateRange(string $period, string $date): array
    {
        $baseDate = Carbon::parse($date);

        return match ($period) {
            'week' => [
                $baseDate->copy()->startOfWeek(),
                $baseDate->copy()->endOfWeek(),
            ],
            'month' => [
                $baseDate->copy()->startOfMonth(),
                $baseDate->copy()->endOfMonth(),
            ],
            default => [
                $baseDate->copy()->startOfDay(),
                $baseDate->copy()->endOfDay(),
            ],
        };
    }

    private function previousDateRange(string $period, string $date): array
    {
        $baseDate = Carbon::parse($date);

        return match ($period) {
            'week' => [
                $baseDate->copy()->subWeek()->startOfWeek(),
                $baseDate->copy()->subWeek()->endOfWeek(),
            ],
            'month' => [
                $baseDate->copy()->subMonthNoOverflow()->startOfMonth(),
                $baseDate->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            default => [
                $baseDate->copy()->subDay()->startOfDay(),
                $baseDate->copy()->subDay()->endOfDay(),
            ],
        };
    }

    private function comparisonLabel(string $period): string
    {
        return match ($period) {
            'week' => 'Last Week',
            'month' => 'Last Month',
            default => 'Last Day',
        };
    }
    

    private function growthPercentage(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
    

    private function mapTransaction(PaymentTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'order_no' => $transaction->order_no,
            'customer' => $transaction->user?->name ?? 'Unknown User',
            'customer_email' => $transaction->user?->email,
            'amount' => (float) $transaction->gross_amount,
            'payment_method' => $this->resolvePaymentMethodLabel($transaction->payment_type),
            'payment_type' => $transaction->payment_type,
            'method_key' => $this->methodKeyFromPaymentType($transaction->payment_type),
            'date' => $transaction->created_at?->translatedFormat('d M Y'),
            'status' => $transaction->status,
            'created_at' => $transaction->created_at?->toDateTimeString(),
            'paid_at' => $transaction->paid_at?->toDateTimeString(),
            'note' => $this->transactionNote($transaction),
        ];
    }

    private function summary($transactions, $previousTransactions): array
    {
        $coinRate = (int) config('gacha.coin_rate', 1000);

        $paidTransactions = $transactions->filter(function (PaymentTransaction $transaction) {
            return strtolower((string) $transaction->status) === 'paid'
                && str_starts_with((string) $transaction->order_no, 'TOPUP-');
        });

        $previousPaidTransactions = $previousTransactions->filter(function (PaymentTransaction $transaction) {
            return strtolower((string) $transaction->status) === 'paid';
        });

        $totalTopUp = (float) $paidTransactions->sum('gross_amount');
        $previousTotalTopUp = (float) $previousPaidTransactions->sum('gross_amount');

        $platformRevenue = round($totalTopUp * 0.05);
        $previousPlatformRevenue = round($previousTotalTopUp * 0.05);

        $totalTransactions = $transactions->count();
        $previousTotalTransactions = $previousTransactions->count();

        $totalCoinsPurchased = $coinRate > 0
            ? floor($totalTopUp / $coinRate)
            : 0;

        $previousTotalCoinsPurchased = $coinRate > 0
            ? floor($previousTotalTopUp / $coinRate)
            : 0;

        return [
            [
                'label' => 'Total Top Up',
                'value' => 'Rp ' . number_format($totalTopUp, 0, ',', '.'),
                'growth' => $this->growthPercentage($totalTopUp, $previousTotalTopUp),
                'icon' => 'bi-wallet2',
            ],
            [
                'label' => 'Platform Revenue',
                'value' => 'Rp ' . number_format($platformRevenue, 0, ',', '.'),
                'growth' => $this->growthPercentage($platformRevenue, $previousPlatformRevenue),
                'icon' => 'bi-box-seam',
            ],
            [
                'label' => 'Total Transactions',
                'value' => number_format($totalTransactions, 0, ',', '.'),
                'growth' => $this->growthPercentage($totalTransactions, $previousTotalTransactions),
                'icon' => 'bi-receipt',
            ],
            [
                'label' => 'Total Coins Purchased',
                'value' => number_format($totalCoinsPurchased, 0, ',', '.') . ' Coin',
                'growth' => $this->growthPercentage($totalCoinsPurchased, $previousTotalCoinsPurchased),
                'icon' => 'bi-coin',
            ],
        ];
    }

    private function transactionNote(PaymentTransaction $transaction): string
    {
        return match ($transaction->status) {
            'paid' => 'Pembayaran berhasil dan saldo user sudah diproses.',
            'pending' => 'Menunggu pembayaran dari user.',
            'failed' => 'Pembayaran gagal.',
            'expired' => 'Pembayaran kedaluwarsa.',
            'canceled', 'cancelled' => 'Pembayaran dibatalkan.',
            default => 'Tidak ada catatan transaksi.',
        };
    }

    private function paymentTypesForMethod(string $method): array
    {
        return match ($method) {
            'va' => ['bank_transfer'],
            'retail' => ['cstore'],
            'ewallet' => ['qris', 'gopay', 'shopeepay'],
            'credit_card' => ['credit_card'],
            default => [],
        };
    }

    private function methodKeyFromPaymentType(?string $paymentType): string
    {
        return match ($paymentType) {
            'bank_transfer' => 'va',
            'qris', 'gopay', 'shopeepay' => 'ewallet',
            'cstore' => 'retail',
            'credit_card' => 'credit_card',
            default => 'other',
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