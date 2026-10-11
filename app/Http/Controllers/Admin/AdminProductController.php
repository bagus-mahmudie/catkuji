<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GachaCategory;
use App\Models\GachaEvent;
use App\Models\GachaEventPrize;
use App\Models\GachaPrize;
use App\Models\ShipmentOrder;
use App\Models\UserPackItem;
use App\Models\DrawResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\DrawTransaction;
use App\Models\UserInventory;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class AdminProductController extends Controller
{
    public function index(Request $request): Response
    {
        GachaCategory::archiveExpiredPromos();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $categoryId = (string) $request->query('category_id', 'all');
        $eventType = (string) $request->query('event_type', 'all');
        $stockStatus = (string) $request->query('stock_status', 'all');
        $sort = (string) $request->query('sort', 'all');

        if (! in_array($sort, [
            'all',
            'latest',
            'oldest',
            'stock_highest',
            'stock_lowest',
            'price_highest',
            'price_lowest',
            'name_az',
            'name_za',
        ], true)) {
            $sort = 'all';
        }

        $productsQuery = GachaEvent::query()
            ->with([
                'category:id,name,slug',
                'eventPrizes.prize:id,name,image,rarity_code',
            ])
            ->withCount('eventPrizes')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('badge_label', 'like', "%{$search}%");
                });
            })
            ->when($status !== 'all' && $status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($categoryId !== 'all' && $categoryId !== '', function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($eventType !== 'all' && $eventType !== '', function ($query) use ($eventType) {
                $query->where('event_type', $eventType);
            })
            ->when($stockStatus !== 'all' && $stockStatus !== '', function ($query) use ($stockStatus) {
                if ($stockStatus === 'available') {
                    $query->where('stock_left', '>', 10);
                }

                if ($stockStatus === 'low') {
                    $query->where('stock_left', '>', 0)
                        ->where('stock_left', '<=', 10);
                }

                if ($stockStatus === 'sold_out') {
                    $query->where('stock_left', '<=', 0);
                }
            });

        match ($sort) {
            'latest' => $productsQuery->latest(),
            'oldest' => $productsQuery->oldest(),
            'stock_highest' => $productsQuery->orderByDesc('stock_left'),
            'stock_lowest' => $productsQuery->orderBy('stock_left'),
            'price_highest' => $productsQuery->orderByDesc('price_per_draw'),
            'price_lowest' => $productsQuery->orderBy('price_per_draw'),
            'name_az' => $productsQuery->orderBy('name'),
            'name_za' => $productsQuery->orderByDesc('name'),
            default => $productsQuery->orderByDesc('created_at'),
        };

        $products = $productsQuery
            ->paginate(12)
            ->withQueryString()
            ->through(fn (GachaEvent $event) => $this->mapProductCard($event));

        $categories = GachaCategory::query()
        ->active()
        ->withCount('events')
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get([
            'id',
            'name',
            'slug',
            'description',
            'card_style',
            'promo_left_bg',
            'category_glow_color',
            'category_glow_position',
            'promo_starts_at',
            'promo_ends_at',
            'sort_order',
        ])
        ->map(fn (GachaCategory $category) => [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'card_style' => $category->card_style ?: 'standard',
            'promo_left_bg' => $category->promo_left_bg,
            'promo_left_bg_url' => $category->promo_left_bg
                ? Storage::disk('public')->url($category->promo_left_bg)
                : null,
            'category_glow_color' => $category->category_glow_color ?: '#6D5BFF',
            'category_glow_position' => $category->category_glow_position ?: 'right',
            'promo_starts_at' => optional($category->promo_starts_at)->format('Y-m-d\TH:i'),
            'promo_ends_at' => optional($category->promo_ends_at)->format('Y-m-d\TH:i'),
            'sort_order' => (int) $category->sort_order,
            'events_count' => (int) $category->events_count,
        ]);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category_id' => $categoryId,
                'event_type' => $eventType,
                'stock_status' => $stockStatus,
                'sort' => $sort,
            ],
            'statusOptions' => $this->productStatusOptions(),
            'eventTypeOptions' => $this->eventTypeOptions(),
            'stockStatusOptions' => $this->stockStatusOptions(),
            'sortOptions' => $this->sortOptions(),
        ]);
    }

    public function analytics(Request $request): Response
    {
        $startDate = now()->copy()->startOfMonth()->startOfDay();
        $endDate = now()->copy()->endOfMonth()->endOfDay();
        $search = trim((string) $request->query('search', ''));

        $previousStart = $startDate->copy()->subDays($startDate->diffInDays($endDate) + 1)->startOfDay();
        $previousEnd = $startDate->copy()->subSecond();

        /**
         * Growth card: this week vs last week
         */
        $thisWeekStart = now()->copy()->startOfWeek()->startOfDay();
        $thisWeekEnd = now()->copy()->endOfWeek()->endOfDay();

        $lastWeekStart = $thisWeekStart->copy()->subWeek()->startOfDay();
        $lastWeekEnd = $thisWeekEnd->copy()->subWeek()->endOfDay();

        $currentActiveProducts = GachaEvent::query()
            ->where('status', 'published')
            ->whereBetween('created_at', [$thisWeekStart, $thisWeekEnd])
            ->count();

        $previousActiveProducts = GachaEvent::query()
            ->where('status', 'published')
            ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
            ->count();

        $currentCategories = GachaCategory::query()
            ->whereBetween('created_at', [$thisWeekStart, $thisWeekEnd])
            ->count();

        $previousCategories = GachaCategory::query()
            ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
            ->count();

        $successDraws = DrawTransaction::query()
            ->where('status', 'success');

        $currentDraws = (clone $successDraws)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $previousDraws = (clone $successDraws)
            ->whereBetween('created_at', [$previousStart, $previousEnd]);

        $currentRevenue = (float) (clone $currentDraws)->sum('total_cost');
        $previousRevenue = (float) (clone $previousDraws)->sum('total_cost');

        $currentPlayed = (int) (clone $currentDraws)->sum('draw_count');
        $previousPlayed = (int) (clone $previousDraws)->sum('draw_count');

        $totalProducts = GachaEvent::query()
            ->where('status', 'published')
            ->count();
        $totalCategories = GachaCategory::query()->count();

        $stockTotal = (int) GachaEvent::query()->sum('stock_total');
        $stockLeft = (int) GachaEvent::query()->sum('stock_left');

        $lowStockCount = GachaEvent::query()
            ->where('stock_left', '>', 0)
            ->where('stock_left', '<=', 10)
            ->count();

        $expiredCount = GachaEvent::query()
            ->whereNotNull('end_at')
            ->where('end_at', '<', now())
            ->count();

        $topCategories = GachaCategory::query()
            ->leftJoin('gacha_events', 'gacha_categories.id', '=', 'gacha_events.category_id')
            ->leftJoin('draw_transactions', function ($join) use ($startDate, $endDate) {
                $join->on('gacha_events.id', '=', 'draw_transactions.gacha_event_id')
                    ->where('draw_transactions.status', '=', 'success')
                    ->whereBetween('draw_transactions.created_at', [$startDate, $endDate]);
            })
            ->select([
                'gacha_categories.id',
                'gacha_categories.name',
                DB::raw('COALESCE(SUM(draw_transactions.draw_count), 0) as total_played'),
                DB::raw('COALESCE(SUM(draw_transactions.total_cost), 0) * 1000 as total_revenue'),
            ])
            ->groupBy('gacha_categories.id', 'gacha_categories.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'played' => (int) $category->total_played,
                'revenue' => (float) $category->total_revenue,
            ])
            ->values();

        $totalCategoryRevenue = max(1, (float) $topCategories->sum('revenue'));

        $topCategories = $topCategories
            ->map(fn ($category) => [
                ...$category,
                'percentage' => round(((float) $category['revenue'] / $totalCategoryRevenue) * 100),
            ])
            ->values();

        if ($topCategories->isEmpty()) {
            $topCategories = collect([
                [
                    'id' => 0,
                    'name' => 'Belum ada kategori',
                    'played' => 0,
                    'revenue' => 0,
                    'percentage' => 0,
                ],
            ]);
        }

        $lowStockItems = GachaEvent::query()
            ->where('stock_left', '>', 0)
            ->where('stock_left', '<=', 10)
            ->orderBy('stock_left')
            ->limit(4)
            ->get(['id', 'name', 'stock_left', 'stock_total'])
            ->map(fn (GachaEvent $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'stock_left' => (int) $event->stock_left,
                'stock_total' => (int) $event->stock_total,
                'status' => 'Low Stock',
            ])
            ->values();

        $weekStart = now()->copy()->subDays(6)->startOfDay();
        $weekEnd = now()->copy()->endOfDay();

        $pendingOrderStatuses = [
            'requested',
            'pending',
            'pending_payment',
            'paid',
            'confirmed',
        ];

        $processOrderStatuses = [
            'processing',
            'allocated',
            'packed',
            'shipped',
            'picking_up',
            'picked',
            'dropping_off',
            'on_delivery',
        ];

        $doneOrderStatuses = [
            'success',
            'delivered',
            'completed',
            'done',
        ];

        $weeklyOrderRows = ShipmentOrder::query()
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw(
                "SUM(CASE WHEN status IN ('" . implode("','", $pendingOrderStatuses) . "') THEN 1 ELSE 0 END) as pending_total"
            )
            ->selectRaw(
                "SUM(CASE WHEN status IN ('" . implode("','", $processOrderStatuses) . "') THEN 1 ELSE 0 END) as process_total"
            )
            ->selectRaw(
                "SUM(CASE WHEN status IN ('" . implode("','", $doneOrderStatuses) . "') THEN 1 ELSE 0 END) as done_total"
            )
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->keyBy('date');

        $weeklyOrderChart = collect();

        $cursor = $weekStart->copy();

        while ($cursor->lte($weekEnd)) {
            $dateKey = $cursor->toDateString();
            $row = $weeklyOrderRows->get($dateKey);

            $weeklyOrderChart->push([
                'date' => $dateKey,
                'label' => $cursor->translatedFormat('d M'),
                'pending' => (int) ($row?->pending_total ?? 0),
                'process' => (int) ($row?->process_total ?? 0),
                'done' => (int) ($row?->done_total ?? 0),
            ]);

            $cursor->addDay();
        }

        $orderSummary = [
            'pending' => ShipmentOrder::query()
                ->whereHas('user', fn ($query) => $query->where('role', 'user'))
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->whereIn('status', $pendingOrderStatuses)
                ->count(),

            'process' => ShipmentOrder::query()
                ->whereHas('user', fn ($query) => $query->where('role', 'user'))
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->whereIn('status', $pendingOrderStatuses)
                ->count(),

            'done' => ShipmentOrder::query()
                ->whereHas('user', fn ($query) => $query->where('role', 'user'))
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->whereIn('status', $pendingOrderStatuses)
                ->count(),

            'chart' => $weeklyOrderChart->values(),
        ];

        $recentOrders = ShipmentOrder::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->with('user:id,name,email,avatar')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (ShipmentOrder $order) use ($pendingOrderStatuses, $processOrderStatuses, $doneOrderStatuses) {
                $status = strtolower((string) $order->status);

                if (in_array($status, $doneOrderStatuses, true)) {
                    $uiStatus = 'done';
                    $statusLabel = 'Done';
                } elseif (in_array($status, $processOrderStatuses, true)) {
                    $uiStatus = 'process';
                    $statusLabel = 'Process';
                } else {
                    $uiStatus = 'pending';
                    $statusLabel = 'Pending';
                }

                return [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'customer_name' => $order->recipient_name_snapshot
                        ?: $order->user?->name
                        ?: 'Unknown Customer',
                    'customer_email' => $order->user?->email,
                    'status' => $uiStatus,
                    'status_raw' => $order->status,
                    'status_label' => $statusLabel,
                ];
            })
            ->values();

        $salesChart = $this->salesChartData($startDate, $endDate);

        $topProducts = GachaEvent::query()
        ->withSum([
            'drawTransactions as played_sum' => fn ($query) => $query
                ->where('status', 'success')
                ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'user'))
                ->whereBetween('created_at', [$startDate, $endDate]),
        ], 'draw_count')
        ->withSum([
            'drawTransactions as revenue_sum' => fn ($query) => $query
                ->where('status', 'success')
                ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'user'))
                ->whereBetween('created_at', [$startDate, $endDate]),
        ], 'total_cost')
        ->orderByDesc('played_sum')
        ->limit(5)
        ->get()
        ->map(fn (GachaEvent $event) => [
            'id' => $event->id,
            'name' => $event->name,
            'image_url' => $this->imageUrl($event->thumbnail_image),
            'played' => (int) ($event->played_sum ?? 0),
            'stock_left' => (int) $event->stock_left,
            'stock_total' => (int) $event->stock_total,
            'revenue' => (float) ($event->revenue_sum ?? 0),
        ])
        ->values();

        $performanceQuery = GachaEvent::query()
            ->with('category:id,name')
            ->withCount([
                'drawTransactions as played_count' => fn ($query) => $query
                    ->where('status', 'success')
                    ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'user'))
                    ->whereBetween('created_at', [$startDate, $endDate]),
            ])
            ->withSum([
                'drawTransactions as revenue_sum' => fn ($query) => $query
                    ->where('status', 'success')
                    ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'user'))
                    ->whereBetween('created_at', [$startDate, $endDate]),
            ], 'total_cost')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('played_count');

        $performances = $performanceQuery
            ->paginate(8)
            ->withQueryString()
            ->through(fn (GachaEvent $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'category' => $event->category?->name,
                'image_url' => $this->imageUrl($event->thumbnail_image),
                'price' => (int) $event->price_per_draw,
                'played' => (int) $event->played_count,
                'revenue' => (float) ($event->revenue_sum ?? 0) *1000,
                'stock_left' => (int) $event->stock_left,
                'stock_total' => (int) $event->stock_total,
                'status' => $event->status,
            ]);

        return Inertia::render('Admin/Products/Analytics', [
            'summary' => [
                'total_products' => $totalProducts,
                'total_categories' => $totalCategories,
                'total_stock' => $stockTotal,
                'stock_left' => $stockLeft,
                'low_stock_count' => $lowStockCount,
                'expired_count' => $expiredCount,
                'current_revenue' => $currentRevenue,
                'previous_revenue' => $previousRevenue,
                'current_played' => $currentPlayed,
                'previous_played' => $previousPlayed,
                'current_active_products' => $currentActiveProducts,
                'previous_active_products' => $previousActiveProducts,
                'active_products_growth' => $this->growthPercentage($currentActiveProducts, $previousActiveProducts),

                'current_categories' => $currentCategories,
                'previous_categories' => $previousCategories,
                'category_growth' => $this->growthPercentage($currentCategories, $previousCategories),

                'revenue_growth' => $this->growthPercentage($currentRevenue, $previousRevenue),
                'played_growth' => $this->growthPercentage($currentPlayed, $previousPlayed),
            ],
            'topCategories' => $topCategories,
            'lowStockItems' => $lowStockItems,
            'orderSummary' => $orderSummary,
            'recentOrders' => $recentOrders,
            'salesChart' => $salesChart,
            'topProducts' => $topProducts,
            'performances' => $performances,
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'search' => $search,
                'date_label' => $startDate->translatedFormat('j M') . ' - ' . $endDate->translatedFormat('j M Y'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'mode' => 'create',
            'categories' => $this->categoryOptions(),
            'product' => $this->emptyProductPayload(),
            'rarityOptions' => $this->rarityOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        DB::transaction(function () use ($request, $validated) {
            $categoryId = $validated['category_id'] ?? null;

            if (! empty($validated['new_category_name'])) {
                $category = GachaCategory::query()->firstOrCreate(
                    ['slug' => $this->uniqueSlug(GachaCategory::class, $validated['new_category_name'])],
                        [
                            'name' => $validated['new_category_name'],
                            'description' => null,
                            'card_style' => 'standard',
                            'sort_order' => 0,
                        ]                );

                $categoryId = $category->id;
            }

            $thumbnailPath = $this->storeImage($request, 'thumbnail_image', 'events');
            $bannerPath = $this->storeImage($request, 'banner_image', 'events') ?: $thumbnailPath;

            $prizes = collect($validated['prizes'] ?? [])
                ->filter(fn ($prize) => filled($prize['item_name'] ?? null))
                ->values();

            $stockTotal = (int) $prizes->sum(fn ($prize) => (int) ($prize['stock'] ?? 0));

            $event = GachaEvent::query()->create([
                'code' => $this->generateEventCode(),
                'slug' => $this->uniqueSlug(GachaEvent::class, $validated['name']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category_id' => $categoryId,
                'banner_image' => $bannerPath,
                'thumbnail_image' => $thumbnailPath,
                'badge_label' => $validated['badge_label'] ?? null,
                'opened_text' => $validated['opened_text'] ?? null,
                'stock_total' => $stockTotal,
                'stock_left' => $stockTotal,
                'price_per_draw' => (int) $validated['price_per_draw'],
                'price_per_multi_draw' => $validated['price_per_multi_draw'] ?? null,
                'price_coin' => (int) $validated['price_per_draw'],
                'price_token' => 0,
                'draw_mode' => $validated['draw_mode'],
                'event_type' => filled($validated['event_type'] ?? null)
                    ? trim($validated['event_type'])
                    : null,
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'] ?? null,
                'status' => $validated['status'],
                'sellback_enabled' => (bool) $validated['sellback_enabled'],
                'shipping_enabled' => (bool) $validated['shipping_enabled'],
                'max_draw_per_user_per_day' => $validated['max_draw_per_user_per_day'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $totalWeight = max(1, (int) $prizes->sum(fn ($prize) => (int) ($prize['draw_weight'] ?? 0)));

            foreach ($prizes as $index => $prizePayload) {
                $imagePath = $request->hasFile("prizes.{$index}.image")
                    ? $request->file("prizes.{$index}.image")->store('prizes', 'public')
                    : null;

                $prize = GachaPrize::query()->create([
                    'sku' => $this->generatePrizeSku(),
                    'name' => $prizePayload['item_name'],
                    'slug' => $this->uniqueSlug(GachaPrize::class, $prizePayload['item_name']),
                    'description' => $prizePayload['description'] ?? null,
                    'image' => $imagePath,
                    'category' => $event->category?->name,
                    'prize_type' => $prizePayload['prize_type'] ?? 'physical',
                    'rarity_code' => $prizePayload['rarity_code'],
                    'market_price' => $prizePayload['market_price'] ?? 0,
                    'sellback_price' => (int) ($prizePayload['sellback_price'] ?? 0),
                    'metadata_json' => [
                        'created_from_admin_product_form' => true,
                        'event_id' => $event->id,
                    ],
                    'status' => 'active',
                ]);

                $stock = (int) $prizePayload['stock'];
                $weight = (int) $prizePayload['draw_weight'];

                GachaEventPrize::query()->create([
                    'gacha_event_id' => $event->id,
                    'gacha_prize_id' => $prize->id,
                    'display_name' => $prizePayload['item_name'],
                    'rarity_code' => $prizePayload['rarity_code'],
                    'draw_weight' => $weight,
                    'initial_stock' => $stock,
                    'current_stock' => $stock,
                    'reserved_stock' => 0,
                    'sellback_price_override' => (int) ($prizePayload['sellback_price'] ?? 0),
                    'coin_price' => $prizePayload['coin_price'] ?? 0,
                    'token_reward' => $prizePayload['token_reward'] ?? 0,
                    'is_token_redeemable' => (bool) ($prizePayload['is_token_redeemable'] ?? false),
                    'token_redeem_price' => $prizePayload['token_redeem_price'] ?? 0,
                    'redeem_stock_total' => $prizePayload['redeem_stock_total'] ?? 0,
                    'redeem_stock_left' => $prizePayload['redeem_stock_total'] ?? 0,
                    'is_featured' => (bool) ($prizePayload['is_featured'] ?? false),
                    'sort_order' => $index,
                ]);
            }
        });

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Product/event berhasil dibuat.');
    }

    public function edit(GachaEvent $product): Response
    {
        $product->load(['eventPrizes.prize', 'category']);

        return Inertia::render('Admin/Products/Form', [
            'mode' => 'edit',
            'categories' => $this->categoryOptions(),
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'category_id' => $product->category_id,
                'status' => $product->status,
                'stock_total' => $product->stock_total,
                'price_per_draw' => (int) $product->price_per_draw,
                'price_per_multi_draw' => $product->price_per_multi_draw,
                'draw_mode' => $product->draw_mode,
                'event_type' => $product->event_type,
                'badge_label' => $product->badge_label,
                'opened_text' => $product->opened_text,
                'start_at' => optional($product->start_at)->format('Y-m-d\TH:i'),
                'end_at' => optional($product->end_at)->format('Y-m-d\TH:i'),
                'sellback_enabled' => $product->sellback_enabled,
                'shipping_enabled' => $product->shipping_enabled,
                'max_draw_per_user_per_day' => $product->max_draw_per_user_per_day,
                'sort_order' => $product->sort_order,
                'thumbnail_url' => $this->imageUrl($product->thumbnail_image),
                'banner_url' => $this->imageUrl($product->banner_image),
                'prizes' => $product->eventPrizes
                                ->filter(fn (GachaEventPrize $eventPrize) => (int) $eventPrize->sort_order < 999999)
                                ->sortBy('sort_order')
                                ->values()
                                ->map(fn (GachaEventPrize $eventPrize) => [
                                'event_prize_id' => $eventPrize->id,
                                'gacha_prize_id' => $eventPrize->gacha_prize_id,
                                'id' => $eventPrize->id,
                                'item_name' => $eventPrize->display_name ?: $eventPrize->prize?->name,
                                'description' => $eventPrize->prize?->description,
                                'rarity_code' => $eventPrize->rarity_code,
                                'prize_type' => $eventPrize->prize?->prize_type ?? 'physical',
                                'stock' => $eventPrize->initial_stock,
                                'draw_weight' => $eventPrize->draw_weight,
                                'sellback_price' => $eventPrize->sellback_price_override,
                                'market_price' => $eventPrize->prize?->market_price,
                                'coin_price' => $eventPrize->coin_price,
                                'token_reward' => $eventPrize->token_reward,
                                'is_token_redeemable' => $eventPrize->is_token_redeemable,
                                'token_redeem_price' => $eventPrize->token_redeem_price,
                                'redeem_stock_total' => $eventPrize->redeem_stock_total,
                                'is_featured' => $eventPrize->is_featured,
                                'image_url' => $this->imageUrl($eventPrize->prize?->image, 'placeholder-prize.png'),
                            ]),
            ],
            'rarityOptions' => $this->rarityOptions(),
        ]);
    }

    public function update(Request $request, GachaEvent $product): RedirectResponse
    {
        $validated = $this->validateProduct($request, true);

        DB::transaction(function () use ($request, $validated, $product) {
            $categoryId = array_key_exists('category_id', $validated) ? $validated['category_id'] : $product->category_id;

            if (! empty($validated['new_category_name'])) {
                $category = GachaCategory::query()->firstOrCreate(
                    [
                        'slug' => $this->uniqueSlug(
                            GachaCategory::class,
                            $validated['new_category_name']
                        ),
                    ],
                    [
                        'name' => $validated['new_category_name'],
                        'description' => null,
                        'card_style' => 'standard',
                        'sort_order' => 0,
                    ]
                );

                $categoryId = $category->id;
            }

            $thumbnailPath = $this->storeImage($request, 'thumbnail_image', 'events')
                ?: $product->thumbnail_image;

            $bannerPath = $this->storeImage($request, 'banner_image', 'events')
                ?: $thumbnailPath;

            $prizes = collect($validated['prizes'] ?? [])->values();

            $activePrizes = $prizes
                ->filter(fn ($prize) => ! (bool) ($prize['_delete'] ?? false))
                ->filter(fn ($prize) => filled($prize['item_name'] ?? null))
                ->values();

            // $stockTotalFromPrizes = (int) $activePrizes->sum(
            //     fn ($prize) => (int) ($prize['stock'] ?? 0)
            // );

            // $stockTotal = (int) (
            //     $validated['stock_total']
            //     ?: ($stockTotalFromPrizes ?: $product->stock_total)
            // );

            $product->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category_id' => $categoryId,
                'banner_image' => $bannerPath,
                'thumbnail_image' => $thumbnailPath,
                'badge_label' => $validated['badge_label'] ?? null,
                'opened_text' => $validated['opened_text'] ?? null,

                // 'stock_total' => $stockTotal,
                // 'stock_left' => min((int) $product->stock_left, $stockTotal),

                'price_per_draw' => (int) $validated['price_per_draw'],
                'price_per_multi_draw' => $validated['price_per_multi_draw'] ?? null,
                'price_coin' => (int) $validated['price_per_draw'],

                'draw_mode' => $validated['draw_mode'],
                'event_type' => filled($validated['event_type'] ?? null)
                    ? trim($validated['event_type'])
                    : null,
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'] ?? null,
                'status' => $validated['status'],

                'sellback_enabled' => (bool) $validated['sellback_enabled'],
                'shipping_enabled' => (bool) $validated['shipping_enabled'],
                'max_draw_per_user_per_day' => $validated['max_draw_per_user_per_day'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'updated_by' => $request->user()->id,
            ]);

            foreach ($prizes as $index => $prizePayload) {
                $eventPrizeId = $prizePayload['event_prize_id']
                    ?? $prizePayload['id']
                    ?? null;

                $gachaPrizeId = $prizePayload['gacha_prize_id'] ?? null;

                $eventPrize = null;

                if ($eventPrizeId) {
                    $eventPrize = GachaEventPrize::query()
                        ->where('gacha_event_id', $product->id)
                        ->where('id', $eventPrizeId)
                        ->first();
                }

                /*
                * HAPUS PRIZE DARI EVENT
                * Kita tidak menghapus gacha_prizes master.
                * Yang dihapus hanya relasi prize ke event.
                */
                if ((bool) ($prizePayload['_delete'] ?? false)) {
                    if ($eventPrize) {
                        $usedStock = max(
                            0,
                            (int) $eventPrize->initial_stock - (int) $eventPrize->current_stock
                        );

                        if ($usedStock > 0 || $eventPrize->results()->exists()) {
                            $eventPrize->update([
                                'initial_stock' => $usedStock,
                                'current_stock' => 0,
                                'draw_weight' => 0,
                                'sort_order' => 999999,
                            ]);
                        } else {
                            $eventPrize->delete();
                        }
                    }

                    continue;
                }

                if (! filled($prizePayload['item_name'] ?? null)) {
                    continue;
                }

                $gachaPrize = null;

                if ($eventPrize?->gacha_prize_id) {
                    $gachaPrize = GachaPrize::query()->find($eventPrize->gacha_prize_id);
                } elseif ($gachaPrizeId) {
                    $gachaPrize = GachaPrize::query()->find($gachaPrizeId);
                }

                $imagePath = null;

                $uploadedFile = $request->file("prizes.{$index}.image");

                if ($uploadedFile && $uploadedFile->isValid()) {
                    $imagePath = $uploadedFile->store('prizes', 'public');
                }

                if (! $gachaPrize) {
                    $gachaPrize = GachaPrize::query()->create([
                        'sku' => $this->generatePrizeSku(),
                        'name' => $prizePayload['item_name'],
                        'slug' => $this->uniqueSlug(GachaPrize::class, $prizePayload['item_name']),
                        'description' => $prizePayload['description'] ?? null,
                        'image' => $imagePath,
                        'category' => $product->category?->name,
                        'prize_type' => $prizePayload['prize_type'] ?? 'physical',
                        'rarity_code' => $prizePayload['rarity_code'],
                        'market_price' => $prizePayload['market_price'] ?? 0,
                        'sellback_price' => (int) ($prizePayload['sellback_price'] ?? 0),
                        'metadata_json' => [
                            'created_from_admin_product_form' => true,
                            'event_id' => $product->id,
                        ],
                        'status' => 'active',
                    ]);
                } else {
                    $gachaPrize->update([
                        'name' => $prizePayload['item_name'],
                        'description' => $prizePayload['description'] ?? null,
                        'image' => $imagePath ?: $gachaPrize->image,
                        'category' => $product->category?->name,
                        'prize_type' => $prizePayload['prize_type'] ?? 'physical',
                        'rarity_code' => $prizePayload['rarity_code'],
                        'market_price' => $prizePayload['market_price'] ?? 0,
                        'sellback_price' => (int) ($prizePayload['sellback_price'] ?? 0),
                        'status' => 'active',
                    ]);
                }

                $stock = (int) ($prizePayload['stock'] ?? 1);
                $weight = (int) ($prizePayload['draw_weight'] ?? 1);
                $newSellbackPrice = (int) ($prizePayload['sellback_price'] ?? 0);

                if ($eventPrize) {
                    /* Kalau prize sudah pernah didraw, current_stock jangan reset penuh. Hitung stok yang sudah terpakai.*/
                    $usedStock = max(
                        0,
                        (int) $eventPrize->initial_stock - (int) $eventPrize->current_stock
                    );

                    if ($stock < $usedStock) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'prizes' => "Stock {$prizePayload['item_name']} tidak boleh lebih kecil dari jumlah yang sudah keluar ({$usedStock}).",
                        ]);
                    }

                    $currentStock = max(0, $stock - $usedStock);

                    $newRedeemStockTotal = (int) ($prizePayload['redeem_stock_total'] ?? 0);

                    $usedRedeemStock = max(
                        0,
                        (int) $eventPrize->redeem_stock_total - (int) $eventPrize->redeem_stock_left
                    );

                    if ($newRedeemStockTotal < $usedRedeemStock) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'prizes' => "Redeem stock {$prizePayload['item_name']} tidak boleh lebih kecil dari jumlah yang sudah diredeem ({$usedRedeemStock}).",
                        ]);
                    }

                    $newRedeemStockLeft = max(0, $newRedeemStockTotal - $usedRedeemStock);

                    $eventPrize->update([
                        'gacha_prize_id' => $gachaPrize->id,
                        'display_name' => $prizePayload['item_name'],
                        'rarity_code' => $prizePayload['rarity_code'],
                        'draw_weight' => $weight,
                        'initial_stock' => $stock,
                        'current_stock' => $currentStock,
                        'sellback_price_override' => $newSellbackPrice,
                        'coin_price' => $prizePayload['coin_price'] ?? 0,
                        'token_reward' => $prizePayload['token_reward'] ?? 0,
                        'is_token_redeemable' => (bool) ($prizePayload['is_token_redeemable'] ?? false),
                        'token_redeem_price' => $prizePayload['token_redeem_price'] ?? 0,
                        'redeem_stock_total' => $newRedeemStockTotal,
                        'redeem_stock_left' => $newRedeemStockLeft,
                        'is_featured' => (bool) ($prizePayload['is_featured'] ?? false),
                        'sort_order' => $index,
                    ]);

                    UserInventory::query()
                        ->where('source_event_id', $product->id)
                        ->where('gacha_prize_id', $gachaPrize->id)
                        ->where('status', 'stored')
                        ->update([
                            'sellback_price' => $newSellbackPrice,
                        ]);

                    UserPackItem::query()
                        ->whereHas('drawResult', function ($query) use ($product, $eventPrize, $gachaPrize) {
                            $query->where('gacha_event_id', $product->id)
                                ->where('gacha_event_prize_id', $eventPrize->id)
                                ->where('gacha_prize_id', $gachaPrize->id);
                        })
                        ->update([
                            'sellback_price' => $newSellbackPrice,
                        ]);

                    DrawResult::query()
                        ->where('gacha_event_id', $product->id)
                        ->where('gacha_event_prize_id', $eventPrize->id)
                        ->where('gacha_prize_id', $gachaPrize->id)
                        ->update([
                            'sellback_price_snapshot' => $newSellbackPrice,
                        ]);
                } else {
                    $product->eventPrizes()->create([
                        'gacha_prize_id' => $gachaPrize->id,
                        'display_name' => $prizePayload['item_name'],
                        'rarity_code' => $prizePayload['rarity_code'],
                        'draw_weight' => $weight,
                        'initial_stock' => $stock,
                        'current_stock' => $stock,
                        'sellback_price_override' => $newSellbackPrice,
                        'coin_price' => $prizePayload['coin_price'] ?? 0,
                        'token_reward' => $prizePayload['token_reward'] ?? 0,
                        'is_token_redeemable' => (bool) ($prizePayload['is_token_redeemable'] ?? false),
                        'token_redeem_price' => $prizePayload['token_redeem_price'] ?? 0,
                        'redeem_stock_total' => (int) ($prizePayload['redeem_stock_total'] ?? 0),
                        'redeem_stock_left' => (int) ($prizePayload['redeem_stock_total'] ?? 0),
                        'is_featured' => (bool) ($prizePayload['is_featured'] ?? false),
                        'sort_order' => $index,
                    ]);
                }
            }

            /*
            * Sync stok event berdasarkan prize aktif.
            */
            $freshStock = GachaEventPrize::query()
                ->where('gacha_event_id', $product->id)
                ->where('sort_order', '<', 999999)
                ->sum('current_stock');

            $freshTotalStock = GachaEventPrize::query()
                ->where('gacha_event_id', $product->id)
                ->where('sort_order', '<', 999999)
                ->sum('initial_stock');

            $product->update([
                'stock_left' => (int) $freshStock,
                'stock_total' => (int) $freshTotalStock,
            ]);
        });

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Product/event berhasil diperbarui.');
    }

    public function destroy(GachaEvent $product)
    {
        try {
            DB::transaction(function () use ($product) {
                $hasDrawResults = $product->drawResults()->exists();
                $hasDrawTransactions = $product->drawTransactions()->exists();

                if ($hasDrawResults || $hasDrawTransactions) {
                    $product->update([
                        'status' => 'hidden',
                    ]);

                    return;
                }

                $product->eventPrizes()->delete();
                $product->delete();
            });

            return redirect()
                ->route('admin.products.index')
                ->with('success', 'Product berhasil dihapus.');
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'message' => 'Product gagal dihapus. Product mungkin masih memiliki data transaksi, inventory, atau relasi lain.',
            ]);
        }
    }

    private function validateProduct(Request $request, bool $isEdit = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => [
                'nullable',
                Rule::exists('gacha_categories', 'id')->where(function ($query) {
                    $query->where('is_archived', false);
                }),
            ],
            'new_category_name' => ['nullable', 'string', 'max:120'],

            'thumbnail_image' => [$isEdit ? 'nullable' : 'required', 'image', 'max:51200'],
            'banner_image' => ['nullable', 'image', 'max:51200'],

            'badge_label' => ['nullable', 'string', 'max:80'],
            'opened_text' => ['nullable', 'string', 'max:120'],

            'stock_total' => ['nullable', 'integer', 'min:0'],
            'price_per_draw' => ['required', 'integer', 'min:1'],
            'price_per_multi_draw' => ['nullable', 'integer', 'min:1'],
            'draw_mode' => ['required', Rule::in(['single', 'multi', 'both'])],
            'event_type' => ['nullable', 'string', 'max:80'],

            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'status' => ['required', Rule::in(['draft', 'published', 'ended', 'hidden'])],

            'sellback_enabled' => ['required', 'boolean'],
            'shipping_enabled' => ['required', 'boolean'],
            'max_draw_per_user_per_day' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'prizes' => [$isEdit ? 'nullable' : 'required', 'array', $isEdit ? '' : 'min:1'],
            'prizes.*.id' => ['nullable'],
            'prizes.*.event_prize_id' => ['nullable', 'exists:gacha_event_prizes,id'],
            'prizes.*.gacha_prize_id' => ['nullable', 'exists:gacha_prizes,id'],
            'prizes.*._delete' => ['nullable', 'boolean'],

            'prizes.*.image' => ['nullable', 'image', 'max:51200'],
            'prizes.*.item_name' => [$isEdit ? 'nullable' : 'required', 'string', 'max:180'],
            'prizes.*.description' => ['nullable', 'string', 'max:1000'],
            'prizes.*.rarity_code' => ['required_with:prizes.*.item_name', Rule::in(['UR', 'SSR', 'SR', 'R', 'C'])],
            'prizes.*.prize_type' => ['nullable', Rule::in(['physical', 'digital', 'voucher'])],
            'prizes.*.stock' => ['required_with:prizes.*.item_name', 'integer', 'min:1'],
            'prizes.*.draw_weight' => ['required_with:prizes.*.item_name', 'integer', 'min:1'],
            'prizes.*.market_price' => ['nullable', 'numeric', 'min:0'],
            'prizes.*.sellback_price' => ['nullable', 'numeric', 'min:0'],
            'prizes.*.coin_price' => ['nullable', 'integer', 'min:0'],
            'prizes.*.token_reward' => ['nullable', 'integer', 'min:0'],
            'prizes.*.is_token_redeemable' => ['nullable', 'boolean'],
            'prizes.*.token_redeem_price' => ['nullable', 'integer', 'min:0'],
            'prizes.*.redeem_stock_total' => ['nullable', 'integer', 'min:0'],
            'prizes.*.is_featured' => ['nullable', 'boolean'],
        ]);
    }

    private function mapProductCard(GachaEvent $event): array
    {
        $tierStock = $event->eventPrizes
            ->where('sort_order', '<', 999999)
            ->groupBy('rarity_code')
            ->map(fn ($items) => (int) $items->sum('current_stock'));

        $stockLeft = (int) $event->stock_left;
        $stockTotal = (int) $event->stock_total;

        $stockStatus = 'available';

        if ($stockLeft <= 0) {
            $stockStatus = 'sold_out';
        } elseif ($stockLeft <= 10) {
            $stockStatus = 'low';
        }

        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'code' => $event->code,
            'status' => $event->status,
            'category' => $event->category?->name,
            'category_id' => $event->category_id,
            'event_type' => $event->event_type,
            'draw_mode' => $event->draw_mode,
            'thumbnail_url' => $this->imageUrl($event->thumbnail_image),
            'banner_url' => $this->imageUrl($event->banner_image),
            'badge_label' => $event->badge_label,
            'stock_left' => $stockLeft,
            'stock_total' => $stockTotal,
            'stock_status' => $stockStatus,
            'price_per_draw' => (int) $event->price_per_draw,
            'price_coin' => (int) $event->price_coin,
            'event_prizes_count' => $event->event_prizes_count,
            'tier_stock' => [
                'gold' => (int) (
                    $tierStock->get('UR', 0)
                    + $tierStock->get('SSR', 0)
                ),
                'silver' => (int) $tierStock->get('SR', 0),
                'bronze' => (int) (
                    $tierStock->get('R', 0)
                    + $tierStock->get('C', 0)
                ),
            ],
            'start_at' => $event->start_at?->translatedFormat('d M Y H:i'),
            'end_at' => $event->end_at?->translatedFormat('d M Y H:i'),
            'created_at' => $event->created_at?->translatedFormat('d M Y'),
            'edit_url' => route('admin.products.edit', $event),
        ];
    }

    private function categoryOptions()
    {
        return GachaCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'card_style']);
    }

    private function productStatusOptions(): array
    {
        return [
            ['label' => 'All', 'value' => 'all'],
            ['label' => 'Draft', 'value' => 'draft'],
            ['label' => 'Published', 'value' => 'published'],
            ['label' => 'Ended', 'value' => 'ended'],
            ['label' => 'Hidden', 'value' => 'hidden'],
        ];
    }

    private function eventTypeOptions(): array
    {
        return [
            ['label' => 'All Types', 'value' => 'all'],
            ['label' => 'Standard', 'value' => 'standard'],
            ['label' => 'Promo', 'value' => 'promo'],
            ['label' => 'Limited', 'value' => 'limited'],
            ['label' => 'Collaboration', 'value' => 'collaboration'],
        ];
    }

    private function stockStatusOptions(): array
    {
        return [
            ['label' => 'All Stock', 'value' => 'all'],
            ['label' => 'Available', 'value' => 'available'],
            ['label' => 'Low Stock', 'value' => 'low'],
            ['label' => 'Sold Out', 'value' => 'sold_out'],
        ];
    }

    private function sortOptions(): array
    {
        return [
            ['label' => 'Newest', 'value' => 'latest'],
            ['label' => 'Oldest', 'value' => 'oldest'],
            ['label' => 'Stock Highest', 'value' => 'stock_highest'],
            ['label' => 'Stock Lowest', 'value' => 'stock_lowest'],
            ['label' => 'Price Highest', 'value' => 'price_highest'],
            ['label' => 'Price Lowest', 'value' => 'price_lowest'],
            ['label' => 'Name A-Z', 'value' => 'name_az'],
            ['label' => 'Name Z-A', 'value' => 'name_za'],
        ];
    }

    private function emptyProductPayload(): array
    {
        return [
            'id' => null,
            'name' => '',
            'description' => '',
            'category_id' => null,
            'new_category_name' => '',
            'status' => 'draft',
            'stock_total' => null,
            'price_per_draw' => 50,
            'price_per_multi_draw' => null,
            'draw_mode' => 'both',
            'event_type' => '',
            'badge_label' => 'Aktif',
            'opened_text' => 'Dibuka',
            'start_at' => now()->format('Y-m-d\TH:i'),
            'end_at' => null,
            'sellback_enabled' => true,
            'shipping_enabled' => true,
            'max_draw_per_user_per_day' => null,
            'sort_order' => 0,
            'thumbnail_url' => null,
            'banner_url' => null,
            'prizes' => [
                $this->emptyPrize('SSR'),
                $this->emptyPrize('SR'),
                $this->emptyPrize('R'),
            ],
        ];
    }

    private function emptyPrize(string $rarity): array
    {
        return [
            'image' => null,
            'image_url' => null,
            'item_name' => '',
            'description' => '',
            'rarity_code' => $rarity,
            'prize_type' => 'physical',
            'stock' => 1,
            'draw_weight' => match ($rarity) {
                'SSR' => 10,
                'SR' => 50,
                'R' => 200,
                default => 100,
            },
            'market_price' => 0,
            'sellback_price' => 0,
            'coin_price' => 0,
            'token_reward' => 0,
            'is_token_redeemable' => false,
            'token_redeem_price' => 0,
            'redeem_stock_total' => 0,
            'is_featured' => false,
        ];
    }

    private function rarityOptions(): array
    {
        return [
            ['label' => 'Last One / UR', 'value' => 'UR'],
            ['label' => 'Gold / SSR', 'value' => 'SSR'],
            ['label' => 'Silver / SR', 'value' => 'SR'],
            ['label' => 'Bronze / R', 'value' => 'R'],
            ['label' => 'Common / C', 'value' => 'C'],
        ];
    }

    private function storeImage(Request $request, string $field, string $folder): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        return $request->file($field)->store($folder, 'public');
    }

    private function imageUrl(?string $path, string $placeholder = 'placeholder-product.png'): ?string
    {
        if (! $path) {
            return asset('images/' . $placeholder);
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, ['/storage/'])) {
            return $path;
        }

        if (Str::startsWith($path, ['storage/'])) {
            return asset($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        if (file_exists(public_path('images/' . $path))) {
            return asset('images/' . $path);
        }

        return asset('images/' . $placeholder);
    }

    private function generateEventCode(): string
    {
        do {
            $code = 'EV-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
        } while (GachaEvent::query()->where('code', $code)->exists());

        return $code;
    }

    private function generatePrizeSku(): string
    {
        do {
            $sku = 'PRZ-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
        } while (GachaPrize::query()->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $modelClass, string $value): string
    {
        $baseSlug = Str::slug($value);
        $slug = $baseSlug;
        $counter = 2;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function growthPercentage(float|int $current, float|int $previous): float
    {
        if ((float) $previous <= 0) {
            return (float) $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function salesChartData(Carbon $startDate, Carbon $endDate): array
    {
        $days = collect();
        $cursor = $startDate->copy()->startOfDay();

        while ($cursor->lte($endDate)) {
            $days->push($cursor->copy());
            $cursor->addDay();
        }

        $revenueRows = DrawTransaction::query()
            ->where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(total_cost) as revenue, SUM(draw_count) as played')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('date');

        return $days
            ->map(function (Carbon $day) use ($revenueRows) {
                $row = $revenueRows->get($day->toDateString());

                return [
                    'date' => $day->toDateString(),
                    'label' => $day->translatedFormat('j M'),
                    'revenue' => (float) ($row?->revenue ?? 0),
                    'played' => (int) ($row?->played ?? 0),
                ];
            })
            ->values()
            ->all();
    }
}