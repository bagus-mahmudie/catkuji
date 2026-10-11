<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\UserInventory;
use App\Models\UserPack;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\UserAddress;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $rarity = strtoupper(trim($request->string('rarity')->toString()));

        $addresses = UserAddress::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map(function ($address) {
                return [
                    'id' => $address->id,
                    'label' => $address->label,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->recipient_phone,
                    'full_address' => $address->full_address,
                    'postal_code' => $address->postal_code,
                    'is_default' => (bool) ($address->is_default ?? false),
                ];
            })
            ->values();

        $baseQuery = UserInventory::query()
            ->where('user_inventories.user_id', $user->id)
            ->where('user_inventories.status', 'stored')
            ->join('gacha_prizes', 'gacha_prizes.id', '=', 'user_inventories.gacha_prize_id');

        $applyRarityFilter = function ($query) use ($rarity) {
            if ($rarity === '') {
                return;
            }

            if ($rarity === 'GOLD') {
                $query->whereIn('gacha_prizes.rarity_code', ['UR', 'SSR', 'GOLD']);
                return;
            }

            if ($rarity === 'SILVER') {
                $query->whereIn('gacha_prizes.rarity_code', ['SR', 'SILVER']);
                return;
            }

            if ($rarity === 'BRONZE') {
                $query->whereNotIn('gacha_prizes.rarity_code', ['UR', 'SSR', 'GOLD', 'SR', 'SILVER']);
                return;
            }

            $query->where('gacha_prizes.rarity_code', $rarity);
        };

        $inventories = (clone $baseQuery)
            ->when($rarity !== '', $applyRarityFilter)
            ->selectRaw('
                MIN(user_inventories.id) as id,
                user_inventories.gacha_prize_id,
                MIN(user_inventories.prize_name_snapshot) as name,
                MIN(user_inventories.prize_image_snapshot) as image,
                MAX(user_inventories.sellback_price) as sellback_price,
                MIN(gacha_prizes.market_price) as market_price,
                MIN(user_inventories.prize_type) as prize_type,
                MIN(user_inventories.source_event_id) as source_event_id,
                MIN(user_inventories.status) as status,
                MIN(gacha_prizes.rarity_code) as rarity_code,
                COUNT(*) as quantity,
                MAX(user_inventories.created_at) as latest_created_at
            ')
            ->groupBy('user_inventories.gacha_prize_id')
            ->orderByDesc('latest_created_at')
            ->paginate(12)
            ->withQueryString()
            ->through(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'gacha_prize_id' => (int) $item->gacha_prize_id,
                    'name' => $item->name,
                    'image' => $item->image,
                    'sellback_price' => (float) $item->sellback_price,
                    'market_price' => (float) ($item->market_price ?? 0),
                    'prize_type' => $item->prize_type,
                    'status' => $item->status,
                    'rarity_code' => strtoupper($item->rarity_code ?? 'BRONZE'),
                    'quantity' => (int) $item->quantity,
                    'can_sellback' => (float) $item->sellback_price > 0,
                    'can_claim' => $item->status === 'stored' && $item->prize_type === 'physical' && (int) $item->quantity > 0,
                    'claim_disabled_reason' => $item->prize_type !== 'physical'
                        ? 'Hanya hadiah fisik yang bisa dikirim.'
                        : ($item->status !== 'stored' ? 'Hadiah ini sedang diproses atau sudah tidak tersedia untuk dikirim.' : null),
                ];
            });

        $raritySummaryRows = (clone $baseQuery)
            ->selectRaw('gacha_prizes.rarity_code, COUNT(*) as total')
            ->groupBy('gacha_prizes.rarity_code')
            ->get();

        $summary = [
            'all' => (clone $baseQuery)->count(),
            'gold' => 0,
            'silver' => 0,
            'bronze' => 0,
        ];

        foreach ($raritySummaryRows as $row) {
            $code = strtoupper($row->rarity_code ?? '');

            if (in_array($code, ['UR', 'SSR', 'GOLD'])) {
                $summary['gold'] += (int) $row->total;
            } elseif (in_array($code, ['SR', 'SILVER'])) {
                $summary['silver'] += (int) $row->total;
            } else {
                $summary['bronze'] += (int) $row->total;
            }
        }

        $claimSummaryRows = (clone $baseQuery)
            ->selectRaw('user_inventories.prize_type, COUNT(*) as total')
            ->groupBy('user_inventories.prize_type')
            ->get();

        $claimSummary = [
            'physical' => 0,
            'digital' => 0,
        ];

        foreach ($claimSummaryRows as $row) {
            $type = strtolower((string) ($row->prize_type ?? ''));

            if ($type === 'physical') {
                $claimSummary['physical'] += (int) $row->total;
            }

            if ($type === 'digital') {
                $claimSummary['digital'] += (int) $row->total;
            }
        }

        return Inertia::render('Inventory/Index', [
            'inventories' => $inventories,
            'addresses' => $addresses,
            'summary' => $summary,
            'claimSummary' => $claimSummary,
            'filters' => [
                'rarity' => $rarity,
            ],
            'unopenedPackCount' => UserPack::query()
                ->where('user_id', $user->id)
                ->where('status', 'unopened')
                ->count(),
        ]);
    }
}