<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DrawTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $orders = DrawTransaction::query()
            ->with(['event', 'results'])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
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
                    'id' => 'purchase-' . $item->id,
                    'service' => 'purchase',
                    'service_label' => 'Pembelian',
                    'icon' => 'bi bi-cart3',
                    'status_key' => $item->status === 'success'
                        ? 'success'
                        : strtolower((string) $item->status),
                    'status_label' => $item->status === 'success'
                        ? 'Berhasil'
                        : ucfirst((string) $item->status),
                    'created_at' => optional($item->created_at)?->toDateTimeString(),
                    'amount' => (float) $item->total_cost,
                    'amount_prefix' => '-',
                    'order_no' => $item->draw_no,
                    'summary' => $item->status === 'success'
                        ? 'Berhasil'
                        : ucfirst((string) $item->status),
                    'sections' => [
                        'purchase' => [
                            'items' => $groupedItems,
                            'rows' => [
                                [
                                    'label' => 'Total Pembelian',
                                    'value' => $item->draw_count . ' Pack',
                                ],
                                [
                                    'label' => 'Total Harga',
                                    'value' => 'Rp ' . number_format((float) $item->total_cost * 1000, 0, ',', '.'),
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
            })
            ->values();

        return Inertia::render('Profile/Orders', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ?? null,
            ],
            'tokenBalance' => (int) ($user->token_balance ?? 0),
            'catKoinBalance' => (int) ($user->wallet?->balance ?? 0),
            'transactions' => $orders,
        ]);
    }

    protected function imageUrl(?string $path, string $placeholder = 'placeholder-prize.png'): string
    {
        $placeholderUrl = asset('images/' . $placeholder);

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
            return '/' . $path;
        }

        if (str_starts_with($path, '/images/')) {
            $filename = basename($path);

            if ($filename === 'placeholder-prize.png') {
                return $path;
            }

            return asset('storage/prizes/' . $filename);
        }

        if (str_starts_with($path, 'images/')) {
            $filename = basename($path);

            if ($filename === 'placeholder-prize.png') {
                return asset($path);
            }

            return asset('storage/prizes/' . $filename);
        }

        if (! str_contains($path, '/')) {
            return asset('storage/prizes/' . $path);
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}