<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminUserController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $period = (string) $request->query('period', 'day');

        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $date = (string) $request->query('date', now()->toDateString());
        $search = trim((string) $request->query('search', ''));
        $trendMode = (string) $request->query('trend', 'dau');

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);
        [$previousStartDate, $previousEndDate] = $this->previousDateRange($period, $date);

        return Inertia::render('Admin/Users/Index', [
            'stats' => $this->stats(
                $startDate,
                $endDate,
                $previousStartDate,
                $previousEndDate
            ),
            'csat' => $this->csatData($startDate, $endDate),
            'nps' => $this->npsData($startDate, $endDate),
            'responses' => $this->responses($startDate, $endDate, $search),
            'userTrend' => $this->userTrend($trendMode),
            'filters' => [
                'period' => $period,
                'date' => $date,
                'search' => $search,
                'trend' => $trendMode,
                'date_label' => Carbon::parse($date)->translatedFormat('d F Y'),
                'comparison_label' => $this->comparisonLabel($period),
            ],
            'periodOptions' => [
                ['label' => 'Day', 'value' => 'day'],
                ['label' => 'Week', 'value' => 'week'],
                ['label' => 'Month', 'value' => 'month'],
            ],
            'trendOptions' => [
                ['label' => 'DAU', 'value' => 'dau'],
                ['label' => 'WAU', 'value' => 'wau'],
                ['label' => 'MAU', 'value' => 'mau'],
            ],
        ]);
    }

    public function export(Request $request)
    {
        $period = (string) $request->query('period', 'day');

        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $date = (string) $request->query('date', now()->toDateString());
        $search = trim((string) $request->query('search', ''));

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);

        $rows = $this->responses($startDate, $endDate, $search);

        $filename = 'users-feedback-' . now()->format('Ymd-His') . '.csv';

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'User',
                'Email',
                'NPS Score',
                'NPS Type',
                'Feedback',
                'Date',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['email'],
                    $row['rating'],
                    $row['type'] ?? '-',
                    $row['review'],
                    $row['date'],
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
                $baseDate->copy()->subWeek()->startOfWeek()->startOfDay(),
                $baseDate->copy()->subWeek()->endOfWeek()->endOfDay(),
            ],
            'month' => [
                $baseDate->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                $baseDate->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
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

    private function stats(
        Carbon $startDate,
        Carbon $endDate,
        Carbon $previousStartDate,
        Carbon $previousEndDate
    ): array {
        $activeUserIds = $this->activeUserIds($startDate, $endDate);
        $previousActiveUserIds = $this->activeUserIds($previousStartDate, $previousEndDate);

        $newUsers = User::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('role', 'user')
            ->count();

        $previousNewUsers = User::query()
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->where('role', 'user')
            ->count();

        $totalUsers = User::query()
            ->where('role', 'user')
            ->where('created_at', '<=', $endDate)
            ->count();

        $previousTotalUsers = User::query()
            ->where('role', 'user')
            ->where('created_at', '<=', $previousEndDate)
            ->count();

        $activeUsers = count($activeUserIds);
        $previousActiveUsers = count($previousActiveUserIds);

        $inactiveUsers = max($totalUsers - $activeUsers, 0);
        $previousInactiveUsers = max($previousTotalUsers - $previousActiveUsers, 0);

        $churnRate = $totalUsers > 0
            ? round(($inactiveUsers / $totalUsers) * 100, 1)
            : 0;

        $previousChurnRate = $previousTotalUsers > 0
            ? round(($previousInactiveUsers / $previousTotalUsers) * 100, 1)
            : 0;

        $successfulDraws = DB::table('draw_transactions')
            ->join('users', 'draw_transactions.user_id', '=', 'users.id')
            ->where('users.role', 'user')
            ->where('draw_transactions.status', 'success')
            ->whereBetween('draw_transactions.created_at', [$startDate, $endDate])
            ->sum('draw_transactions.draw_count');

        $previousSuccessfulDraws = DB::table('draw_transactions')
            ->join('users', 'draw_transactions.user_id', '=', 'users.id')
            ->where('users.role', 'user')
            ->where('draw_transactions.status', 'success')
            ->whereBetween('draw_transactions.created_at', [$previousStartDate, $previousEndDate])
            ->sum('draw_transactions.draw_count');

        $engagementMinutes = round(((int) $successfulDraws * 1.5), 1);
        $previousEngagementMinutes = round(((int) $previousSuccessfulDraws * 1.5), 1);

        return [
        [
            'label' => 'Total Active user',
            'value' => $this->compactNumber($activeUsers),
            'raw_value' => $activeUsers,
            'growth' => $this->calculateGrowth($activeUsers, $previousActiveUsers),
            'icon' => 'bi-people',
        ],
        [
            'label' => 'New Users',
            'value' => $this->compactNumber($newUsers),
            'raw_value' => $newUsers,
            'growth' => $this->calculateGrowth($newUsers, $previousNewUsers),
            'icon' => 'bi-person',
        ],
        [
            'label' => 'Inactive Users',
            'value' => $churnRate . '%',
            'raw_value' => $churnRate,
            'growth' => $this->calculateGrowth($churnRate, $previousChurnRate),
            'icon' => 'bi-graph-down-arrow',
        ],
        [
            'label' => 'Engagement Time',
            'value' => $engagementMinutes . 'm',
            'raw_value' => $engagementMinutes,
            'growth' => $this->calculateGrowth($engagementMinutes, $previousEngagementMinutes),
            'icon' => 'bi-stopwatch',
        ],
    ];
    }

    private function activeUserIds(Carbon $startDate, Carbon $endDate): array
    {
        $drawUsers = DB::table('draw_transactions')
            ->join('users', 'draw_transactions.user_id', '=', 'users.id')
            ->where('users.role', 'user')
            ->whereBetween('draw_transactions.created_at', [$startDate, $endDate])
            ->pluck('draw_transactions.user_id');

        $paymentUsers = DB::table('payment_transactions')
            ->join('users', 'payment_transactions.user_id', '=', 'users.id')
            ->where('users.role', 'user')
            ->whereBetween('payment_transactions.created_at', [$startDate, $endDate])
            ->pluck('payment_transactions.user_id');

        $walletUsers = DB::table('wallet_transactions')
            ->join('users', 'wallet_transactions.user_id', '=', 'users.id')
            ->where('users.role', 'user')
            ->whereBetween('wallet_transactions.created_at', [$startDate, $endDate])
            ->pluck('wallet_transactions.user_id');

        return $drawUsers
            ->merge($paymentUsers)
            ->merge($walletUsers)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function csatData(Carbon $startDate, Carbon $endDate): array
    {
        $avgRating = DB::table('csat_feedbacks')
            ->join('users', 'csat_feedbacks.user_id', '=', 'users.id')
            ->where('users.role', 'user')
            ->whereBetween('csat_feedbacks.created_at', [$startDate, $endDate])
            ->avg('csat_feedbacks.rating');

        // Score utama sekarang skala 1-5
        $score = $avgRating ? round($avgRating, 1) : 0;

        $chart = $this->csatChartData($startDate, $endDate);

        return [
            'score' => $score,
            'date_label' => $this->chartDateLabel($startDate, $endDate),
            'chart' => $chart,

            'trend' => collect($chart['categories'])->map(function ($label, $index) use ($chart) {
                return [
                    'month' => $label,
                    'score' => $chart['scores'][$index] ?? 0,
                    'responses' => $chart['responses'][$index] ?? 0,
                ];
            })->values(),
        ];
    }

    private function csatChartData(Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate);

        $categories = [];
        $scores = [];
        $responses = [];

        // Jika filter 1 hari, tampilkan per jam
        if ($days <= 1) {
            for ($hour = 0; $hour < 24; $hour += 2) {
                $bucketStart = $startDate->copy()->setTime($hour, 0, 0);
                $bucketEnd = $bucketStart->copy()->addHour()->endOfHour();

                $query = DB::table('csat_feedbacks')
                    ->join('users', 'csat_feedbacks.user_id', '=', 'users.id')
                    ->where('users.role', 'user')
                    ->whereBetween('csat_feedbacks.created_at', [$bucketStart, $bucketEnd]);

                $avg = (clone $query)->avg('csat_feedbacks.rating');
                $count = (clone $query)->count();

                $categories[] = $bucketStart->format('H.00');
                $scores[] = $avg ? round($avg, 1) : 0;
                $responses[] = $count;
            }

            return [
                'categories' => $categories,
                'scores' => $scores,
                'responses' => $responses,
            ];
        }

        // Jika filter mingguan / bulanan, tampilkan per tanggal
        if ($days <= 45) {
            $cursor = $startDate->copy()->startOfDay();

            while ($cursor->lte($endDate)) {
                $bucketStart = $cursor->copy()->startOfDay();
                $bucketEnd = $cursor->copy()->endOfDay();

                $query = DB::table('csat_feedbacks')
                    ->whereBetween('created_at', [$bucketStart, $bucketEnd]);

                $avg = (clone $query)->avg('rating');
                $count = (clone $query)->count();

                $categories[] = $bucketStart->format('j M');
                $scores[] = $avg ? round($avg, 1) : 0;
                $responses[] = $count;

                $cursor->addDay();
            }

            return [
                'categories' => $categories,
                'scores' => $scores,
                'responses' => $responses,
            ];
        }

        // Jika range panjang, tampilkan per bulan
        $cursor = $startDate->copy()->startOfMonth();

        while ($cursor->lte($endDate)) {
            $bucketStart = $cursor->copy()->startOfMonth();
            $bucketEnd = $cursor->copy()->endOfMonth();

            $query = DB::table('csat_feedbacks')
                ->whereBetween('created_at', [$bucketStart, $bucketEnd]);

            $avg = (clone $query)->avg('rating');
            $count = (clone $query)->count();

            $categories[] = $bucketStart->format('M');
            $scores[] = $avg ? round($avg, 1) : 0;
            $responses[] = $count;

            $cursor->addMonth();
        }

        return [
            'categories' => $categories,
            'scores' => $scores,
            'responses' => $responses,
        ];
    }

    private function chartDateLabel(Carbon $startDate, Carbon $endDate): string
    {
        if ($startDate->isSameDay($endDate)) {
            return $startDate->format('j M Y');
        }

        if ($startDate->isSameYear($endDate)) {
            return $startDate->format('j M') . ' - ' . $endDate->format('j M Y');
        }

        return $startDate->format('j M Y') . ' - ' . $endDate->format('j M Y');
    }

    private function npsData(Carbon $startDate, Carbon $endDate): array
    {
        $rows = DB::table('nps_feedbacks')
            ->join('users', 'nps_feedbacks.user_id', '=', 'users.id')
            ->where('users.role', 'user')
            ->whereBetween('nps_feedbacks.created_at', [$startDate, $endDate])
            ->select([
                'nps_feedbacks.id',
                'nps_feedbacks.score',
                'nps_feedbacks.type',
                'nps_feedbacks.feedback',
                'nps_feedbacks.created_at',
                'nps_feedbacks.user_id',
            ])
            ->get();

        $totalResponses = $rows->count();

        $summary = $this->calculateNpsBucket($rows);

        $averageScore = $totalResponses > 0
            ? round($rows->avg('score'), 1)
            : 0;

        $chart = $this->npsChartData($startDate, $endDate);

        return [
            // Ini sekarang average score dari semua response
            'score' => $averageScore,

            // Kalau suatu saat tetap butuh NPS asli, simpan di key lain
            'nps_score' => $summary['score'],

            'promoters' => $summary['promoters'],
            'passives' => $summary['passives'],
            'detractors' => $summary['detractors'],
            'total_responses' => $totalResponses,
            'date_label' => $this->chartDateLabel($startDate, $endDate),
            'chart' => $chart,

            'trend' => collect($chart['categories'])->map(function ($label, $index) use ($chart) {
                return [
                    'month' => $label,
                    'score' => $chart['scores'][$index] ?? 0,
                    'promoters' => $chart['promoters'][$index] ?? 0,
                    'passives' => $chart['passives'][$index] ?? 0,
                    'detractors' => $chart['detractors'][$index] ?? 0,
                    'promoters_count' => $chart['promoters_count'][$index] ?? 0,
                    'passives_count' => $chart['passives_count'][$index] ?? 0,
                    'detractors_count' => $chart['detractors_count'][$index] ?? 0,
                ];
            })->values(),
        ];
    }

    private function npsChartData(Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate);

        $categories = [];
        $scores = [];

        $promotersSeries = [];
        $passivesSeries = [];
        $detractorsSeries = [];
        $responsesSeries = [];

        $promotersCountSeries = [];
        $passivesCountSeries = [];
        $detractorsCountSeries = [];

        $pushBucket = function (Carbon $bucketStart, Carbon $bucketEnd, string $label) use (
            &$categories,
            &$scores,
            &$promotersSeries,
            &$passivesSeries,
            &$detractorsSeries,
            &$responsesSeries,
            &$promotersCountSeries,
            &$passivesCountSeries,
            &$detractorsCountSeries
        ) {
            $rows = DB::table('nps_feedbacks')
                ->join('users', 'nps_feedbacks.user_id', '=', 'users.id')
                ->where('users.role', 'user')
                ->whereBetween('nps_feedbacks.created_at', [$bucketStart, $bucketEnd])
                ->select([
                    'nps_feedbacks.id',
                    'nps_feedbacks.score',
                    'nps_feedbacks.type',
                    'nps_feedbacks.feedback',
                    'nps_feedbacks.created_at',
                    'nps_feedbacks.user_id',
                ])
                ->get();

            $result = $this->calculateNpsBucket($rows);

            $categories[] = $label;
            $scores[] = $result['score'];

            // Persentase untuk tinggi garis chart
            $promotersSeries[] = $result['promoters_percentage'];
            $passivesSeries[] = $result['passives_percentage'];
            $detractorsSeries[] = $result['detractors_percentage'];

            // Jumlah user untuk tooltip
            $promotersCountSeries[] = $result['promoters'];
            $passivesCountSeries[] = $result['passives'];
            $detractorsCountSeries[] = $result['detractors'];

            $responsesSeries[] = $result['total'];
        };

        if ($days <= 1) {
            for ($hour = 0; $hour < 24; $hour += 2) {
                $bucketStart = $startDate->copy()->setTime($hour, 0, 0);
                $bucketEnd = $bucketStart->copy()->addHour()->endOfHour();

                $pushBucket(
                    $bucketStart,
                    $bucketEnd,
                    $bucketStart->format('H.00')
                );
            }
        } elseif ($days <= 45) {
            $cursor = $startDate->copy()->startOfDay();

            while ($cursor->lte($endDate)) {
                $bucketStart = $cursor->copy()->startOfDay();
                $bucketEnd = $cursor->copy()->endOfDay();

                $pushBucket(
                    $bucketStart,
                    $bucketEnd,
                    $bucketStart->format('j M')
                );

                $cursor->addDay();
            }
        } else {
            $cursor = $startDate->copy()->startOfMonth();

            while ($cursor->lte($endDate)) {
                $bucketStart = $cursor->copy()->startOfMonth();
                $bucketEnd = $cursor->copy()->endOfMonth();

                $pushBucket(
                    $bucketStart,
                    $bucketEnd,
                    $bucketStart->format('M')
                );

                $cursor->addMonth();
            }
        }

        return [
            'categories' => $categories,
            'scores' => $scores,

            // Persentase untuk chart
            'promoters' => $promotersSeries,
            'passives' => $passivesSeries,
            'detractors' => $detractorsSeries,

            // Count untuk tooltip
            'promoters_count' => $promotersCountSeries,
            'passives_count' => $passivesCountSeries,
            'detractors_count' => $detractorsCountSeries,

            'responses' => $responsesSeries,
        ];
    }

    private function calculateNpsBucket($rows): array
    {
        $total = $rows->count();

        if ($total === 0) {
            return [
                'score' => 0,
                'total' => 0,

                'promoters' => 0,
                'passives' => 0,
                'detractors' => 0,

                'promoters_percentage' => 0,
                'passives_percentage' => 0,
                'detractors_percentage' => 0,
            ];
        }

        $promoters = $rows->filter(function ($row) {
            return (int) $row->score >= 9;
        })->count();

        $passives = $rows->filter(function ($row) {
            return (int) $row->score >= 7 && (int) $row->score <= 8;
        })->count();

        $detractors = $rows->filter(function ($row) {
            return (int) $row->score <= 6;
        })->count();

        $promotersPercentage = round(($promoters / $total) * 100);
        $passivesPercentage = round(($passives / $total) * 100);
        $detractorsPercentage = round(($detractors / $total) * 100);

        $score = round($promotersPercentage - $detractorsPercentage);

        return [
            'score' => $score,
            'total' => $total,

            'promoters' => $promoters,
            'passives' => $passives,
            'detractors' => $detractors,

            'promoters_percentage' => $promotersPercentage,
            'passives_percentage' => $passivesPercentage,
            'detractors_percentage' => $detractorsPercentage,
        ];
    }

    private function responses(Carbon $startDate, Carbon $endDate, string $search): array
    {
        $responses = DB::table('nps_feedbacks as nf')
            ->join('users as u', 'nf.user_id', '=', 'u.id')
            ->where('u.role', 'user')
            ->select([
                'nf.id',
                'nf.score',
                'nf.type',
                'nf.feedback',
                'nf.created_at',
                'u.name',
                'u.email',
                'u.avatar',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('u.name', 'like', "%{$search}%")
                        ->orWhere('u.email', 'like', "%{$search}%")
                        ->orWhere('nf.feedback', 'like', "%{$search}%")
                        ->orWhere('nf.type', 'like', "%{$search}%")
                        ->orWhere('nf.score', 'like', "%{$search}%");
                });
            })
            ->latest('nf.created_at')
            ->get();

        return $responses->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'avatar' => $this->avatarUrl($row->avatar, $row->name),

            // Frontend tetap pakai response.review
            // Tapi datanya dari nps_feedbacks.feedback
            'review' => $row->feedback ?: 'Belum ada feedback NPS.',

            // Frontend tetap pakai response.rating
            // Tapi datanya dari nps_feedbacks.score
            'rating' => (float) $row->score,

            'type' => $row->type,
            'date' => $row->created_at
                ? Carbon::parse($row->created_at)->translatedFormat('F d, Y')
                : '-',
        ])->values()->all();
    }

    private function userTrend(string $mode): array
    {
        $mode = in_array($mode, ['dau', 'wau', 'mau'], true) ? $mode : 'dau';

        $series = match ($mode) {
            'wau' => $this->weeklyActiveUserTrend(),
            'mau' => $this->monthlyActiveUserTrend(),
            default => $this->dailyActiveUserTrend(),
        };

        $averageSource = $series->filter(function ($item) {
            return empty($item['is_future']);
        });

        $average = round($averageSource->avg('value') ?: 0, 1);

        return [
            'average' => $this->compactNumber($average),
            'raw_average' => $average,
            'mode' => strtoupper($mode),
            'series' => $series->values(),
        ];
    }

    private function dailyActiveUserTrend()
    {
        // 15 hari sebelum hari ini, lalu 14 hari setelah hari ini
        // Jadi hari ini berada di tengah chart
        $startDate = now()->subDays(15)->startOfDay();
        $endDate = now()->addDays(14)->endOfDay();

        $days = collect();
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $isFutureDay = $dayStart->gt(now()->endOfDay());

            $days->push([
                'month' => $cursor->format('d M'),
                'value' => $isFutureDay
                    ? 0
                    : count($this->activeUserIds($dayStart, $dayEnd)),
                'date_label' => $cursor->format('j M Y'),
                'is_future' => $isFutureDay,
                'is_current' => $cursor->isSameDay(now()),
            ]);

            $cursor->addDay();
        }

        return $days;
    }

    private function weeklyActiveUserTrend()
    {
        // 6 minggu sebelum minggu ini, lalu 5 minggu setelah minggu ini
        // Jadi minggu ini berada di tengah chart
        $startDate = now()->subWeeks(6)->startOfWeek();
        $endDate = now()->addWeeks(5)->endOfWeek();

        $weeks = collect();
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            $weekStart = $cursor->copy()->startOfWeek();
            $weekEnd = $cursor->copy()->endOfWeek();

            $isFutureWeek = $weekStart->gt(now()->endOfWeek());

            $weeks->push([
                'month' => $weekStart->format('d M'),
                'value' => $isFutureWeek
                    ? 0
                    : count($this->activeUserIds($weekStart, $weekEnd)),
                'date_label' => $weekStart->format('j M') . ' - ' . $weekEnd->format('j M'),
                'is_future' => $isFutureWeek,
                'is_current' => now()->between($weekStart, $weekEnd),
            ]);

            $cursor->addWeek();
        }

        return $weeks;
    }

    private function monthlyActiveUserTrend()
    {
        return collect(range(1, 12))->map(function (int $month) {
            $start = Carbon::create(now()->year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            return [
                'month' => $start->format('M'),
                'value' => count($this->activeUserIds($start, $end)),
            ];
        });
    }
    
    private function averageDailyActiveUsers(Carbon $startDate, Carbon $endDate): float
    {
        $days = 0;
        $total = 0;

        $cursor = $startDate->copy()->startOfDay();

        while ($cursor->lte($endDate)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $total += count($this->activeUserIds($dayStart, $dayEnd));
            $days++;

            $cursor->addDay();
        }

        return $days > 0 ? $total / $days : 0;
    }

    private function averageWeeklyActiveUsers(Carbon $startDate, Carbon $endDate): float
    {
        $weeks = 0;
        $total = 0;

        $cursor = $startDate->copy()->startOfWeek();

        while ($cursor->lte($endDate)) {
            $weekStart = $cursor->copy()->startOfWeek()->max($startDate);
            $weekEnd = $cursor->copy()->endOfWeek()->min($endDate);

            $total += count($this->activeUserIds($weekStart, $weekEnd));
            $weeks++;

            $cursor->addWeek();
        }

        return $weeks > 0 ? $total / $weeks : 0;
    }

    private function calculateGrowth(float|int $current, float|int $previous): float
    {
        if ((float) $previous <= 0) {
            return (float) $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function compactNumber(float|int $value): string
    {
        $value = (float) $value;

        if ($value >= 1000000) {
            return round($value / 1000000, 1) . 'M';
        }

        if ($value >= 1000) {
            return round($value / 1000, 1) . 'K';
        }

        return (string) round($value, 1);
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

    public function csatDetail(Request $request): InertiaResponse
    {
        $period = (string) $request->query('period', 'day');

        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $date = (string) $request->query('date', now()->toDateString());
        $rating = $request->query('rating');
        $search = trim((string) $request->query('search', ''));

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);

        $summary = $this->csatDetailSummary($startDate, $endDate);
        $distribution = $this->csatRatingDistribution($startDate, $endDate);
        $reviews = $this->csatReviews($startDate, $endDate, $rating, $search);

        return Inertia::render('Admin/Users/CsatDetail', [
            'summary' => $summary,
            'distribution' => $distribution,
            'reviews' => $reviews,
            'selectedReview' => $reviews[0] ?? null,
            'filters' => [
                'period' => $period,
                'date' => $date,
                'rating' => $rating,
                'search' => $search,
                'date_label' => $this->chartDateLabel($startDate, $endDate),
            ],
            'periodOptions' => [
                ['label' => 'Day', 'value' => 'day'],
                ['label' => 'Week', 'value' => 'week'],
                ['label' => 'Month', 'value' => 'month'],
            ],
            'ratingOptions' => [
                ['label' => 'All ratings', 'value' => ''],
                ['label' => '5.0 ratings', 'value' => '5'],
                ['label' => '4.0 ratings', 'value' => '4'],
                ['label' => '3.0 ratings', 'value' => '3'],
                ['label' => '2.0 ratings', 'value' => '2'],
                ['label' => '1.0 ratings', 'value' => '1'],
            ],
        ]);
    }

    private function csatDetailSummary(Carbon $startDate, Carbon $endDate): array
    {
        $rows = DB::table('csat_feedbacks')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalRatings = $rows->count();

        $averageRating = $totalRatings > 0
            ? round($rows->avg('rating'), 1)
            : 0;

        return [
            'average' => $averageRating,
            'total_ratings' => $totalRatings,
            'total_ratings_label' => number_format($totalRatings) . ' ratings',
        ];
    }

    private function csatRatingDistribution(Carbon $startDate, Carbon $endDate): array
    {
        $total = DB::table('csat_feedbacks')
            ->join('users', 'csat_feedbacks.user_id', '=', 'users.id')
            ->where('users.role', 'user')
            ->whereBetween('csat_feedbacks.created_at', [$startDate, $endDate])
            ->count();

        return collect([5, 4, 3, 2, 1])->map(function (int $rating) use ($startDate, $endDate, $total) {
            $count = DB::table('csat_feedbacks')
                ->join('users', 'csat_feedbacks.user_id', '=', 'users.id')
                ->where('users.role', 'user')
                ->whereBetween('csat_feedbacks.created_at', [$startDate, $endDate])
                ->where('csat_feedbacks.rating', $rating)
                ->count();

            return [
                'rating' => number_format($rating, 1),
                'value' => $rating,
                'count' => $count,
                'count_label' => $this->compactNumber($count) . ' reviews',
                'percentage' => $total > 0 ? round(($count / $total) * 100) : 0,
            ];
        })->values()->all();
    }

    private function csatReviews(Carbon $startDate, Carbon $endDate, mixed $rating, string $search): array
    {
        $rows = DB::table('csat_feedbacks as cf')
            ->join('users as u', 'cf.user_id', '=', 'u.id')
            ->select([
                'cf.id',
                'cf.rating',
                'cf.review',
                'cf.created_at',
                'u.name',
                'u.email',
                'u.avatar',
            ])
            ->where('u.role', 'user')
            ->whereBetween('cf.created_at', [$startDate, $endDate])
            ->when($rating !== null && $rating !== '', function ($query) use ($rating) {
                $query->where('cf.rating', (int) $rating);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('u.name', 'like', "%{$search}%")
                        ->orWhere('u.email', 'like', "%{$search}%")
                        ->orWhere('cf.review', 'like', "%{$search}%")
                        ->orWhere('cf.rating', 'like', "%{$search}%");
                });
            })
            ->latest('cf.created_at')
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'avatar' => $this->avatarUrl($row->avatar, $row->name),
                'rating' => (float) $row->rating,
                'review' => $row->review ?: 'Belum ada review CSAT.',
                'date' => $row->created_at
                    ? Carbon::parse($row->created_at)->translatedFormat('F d, Y')
                    : '-',
            ];
        })->values()->all();
    }

    public function exportCsat(Request $request)
    {
        $period = (string) $request->query('period', 'day');

        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $date = (string) $request->query('date', now()->toDateString());
        $rating = $request->query('rating');
        $search = trim((string) $request->query('search', ''));

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);

        $rows = $this->csatReviews($startDate, $endDate, $rating, $search);

        $filename = 'csat-feedback-' . now()->format('Ymd-His') . '.csv';

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'User',
                'Email',
                'CSAT Rating',
                'Review',
                'Date',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['email'],
                    $row['rating'],
                    $row['review'],
                    $row['date'],
                ]);
            }

            fclose($handle);
        };

        return Response::streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function npsDetail(Request $request): InertiaResponse
    {
        $period = (string) $request->query('period', 'day');

        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $date = (string) $request->query('date', now()->toDateString());
        $search = trim((string) $request->query('search', ''));

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);

        return Inertia::render('Admin/Users/NpsDetail', [
            'columns' => [
                'detractors' => [
                    'title' => 'Detractors',
                    'description' => 'Score 0 - 6',
                    'items' => $this->npsReviewsByType($startDate, $endDate, 'detractor', $search),
                ],
                'passives' => [
                    'title' => 'Passive',
                    'description' => 'Score 7 - 8',
                    'items' => $this->npsReviewsByType($startDate, $endDate, 'passive', $search),
                ],
                'promoters' => [
                    'title' => 'Promoter',
                    'description' => 'Score 9 - 10',
                    'items' => $this->npsReviewsByType($startDate, $endDate, 'promoter', $search),
                ],
            ],
            'summary' => $this->npsDetailSummary($startDate, $endDate),
            'filters' => [
                'period' => $period,
                'date' => $date,
                'search' => $search,
                'date_label' => $this->chartDateLabel($startDate, $endDate),
            ],
            'periodOptions' => [
                ['label' => 'Day', 'value' => 'day'],
                ['label' => 'Week', 'value' => 'week'],
                ['label' => 'Month', 'value' => 'month'],
            ],
        ]);
    }

    private function npsDetailSummary(Carbon $startDate, Carbon $endDate): array
    {
        $rows = DB::table('nps_feedbacks')
            ->join('users', 'nps_feedbacks.user_id', '=', 'users.id')
            ->where('users.role', 'user')
            ->whereBetween('nps_feedbacks.created_at', [$startDate, $endDate])
            ->select([
                'nps_feedbacks.id',
                'nps_feedbacks.score',
                'nps_feedbacks.type',
                'nps_feedbacks.feedback',
                'nps_feedbacks.created_at',
                'nps_feedbacks.user_id',
            ])
            ->get();

        $total = $rows->count();

        if ($total === 0) {
            return [
                'average_score' => 0,
                'nps_score' => 0,
                'total_responses' => 0,
                'promoters' => 0,
                'passives' => 0,
                'detractors' => 0,
            ];
        }

        $summary = $this->calculateNpsBucket($rows);

        return [
            'average_score' => round($rows->avg('score'), 1),
            'nps_score' => $summary['score'],
            'total_responses' => $total,
            'promoters' => $summary['promoters'],
            'passives' => $summary['passives'],
            'detractors' => $summary['detractors'],
        ];
    }

    private function npsReviewsByType(Carbon $startDate, Carbon $endDate, string $type, string $search = ''): array
    {
        $query = DB::table('nps_feedbacks as nf')
            ->join('users as u', 'nf.user_id', '=', 'u.id')
            ->where('u.role', 'user')
            ->select([
                'nf.id',
                'nf.score',
                'nf.type',
                'nf.feedback',
                'nf.created_at',
                'u.name',
                'u.email',
                'u.avatar',
            ])
            ->whereBetween('nf.created_at', [$startDate, $endDate]);

        if ($type === 'promoter') {
            $query->where('nf.score', '>=', 9);
        }

        if ($type === 'passive') {
            $query->whereBetween('nf.score', [7, 8]);
        }

        if ($type === 'detractor') {
            $query->where('nf.score', '<=', 6);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                    ->orWhere('u.email', 'like', "%{$search}%")
                    ->orWhere('nf.feedback', 'like', "%{$search}%")
                    ->orWhere('nf.score', 'like', "%{$search}%");
            });
        }

        return $query
            ->latest('nf.created_at')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'avatar' => $this->avatarUrl($row->avatar, $row->name),
                    'score' => (int) $row->score,
                    'type' => $row->type,
                    'feedback' => $row->feedback ?: 'Belum ada feedback NPS.',
                    'date' => $row->created_at
                        ? Carbon::parse($row->created_at)->translatedFormat('F d, Y')
                        : '-',
                    'time' => $row->created_at
                        ? Carbon::parse($row->created_at)->format('h:i A')
                        : '-',
                ];
            })
            ->values()
            ->all();
    }

    public function exportNps(Request $request)
    {
        $period = (string) $request->query('period', 'day');

        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $date = (string) $request->query('date', now()->toDateString());
        $search = trim((string) $request->query('search', ''));

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);

        $rows = collect([
            ...$this->npsReviewsByType($startDate, $endDate, 'detractor', $search),
            ...$this->npsReviewsByType($startDate, $endDate, 'passive', $search),
            ...$this->npsReviewsByType($startDate, $endDate, 'promoter', $search),
        ])->sortByDesc('id')->values();

        $filename = 'nps-feedback-' . now()->format('Ymd-His') . '.csv';

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'User',
                'Email',
                'Score',
                'Type',
                'Feedback',
                'Date',
                'Time',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['email'],
                    $row['score'],
                    $row['type'],
                    $row['feedback'],
                    $row['date'],
                    $row['time'],
                ]);
            }

            fclose($handle);
        };

        return Response::streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}