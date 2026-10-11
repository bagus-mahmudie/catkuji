<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\UserInventory;
use App\Models\UserPack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryPackController extends Controller
{
    public function index(Request $request)
    {
        $packs = UserPack::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'unopened')
            ->withCount('items')
            ->latest()
            ->get()
            ->map(function ($pack) {
                return [
                    'id' => $pack->id,
                    'pack_no' => $pack->pack_no,
                    'pack_count' => (int) $pack->pack_count,
                    'status' => $pack->status,
                    'items_count' => (int) $pack->items_count,
                    'created_at' => optional($pack->created_at)?->toDateTimeString(),
                ];
            });

        return Inertia::render('Inventory/PackIndex', [
            'packs' => $packs,
        ]);
    }

    public function open(Request $request, UserPack $pack)
    {
        if ((int) $pack->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        if ($pack->status !== 'unopened') {
            return response()->json([
                'message' => 'Pack sudah dibuka.',
            ], 422);
        }

        $result = DB::transaction(function () use ($pack, $request) {
            $pack = UserPack::query()
                ->whereKey($pack->id)
                ->with(['items.drawResult', 'items.prize', 'drawTransaction'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($pack->status !== 'unopened') {
                throw new \Exception('Pack sudah dibuka.');
            }

            $revealedItems = [];

            foreach ($pack->items as $item) {
                $inventory = UserInventory::firstOrCreate(
                    [
                        'draw_result_id' => $item->draw_result_id,
                    ],
                    [
                        'user_id' => $request->user()->id,
                        'gacha_prize_id' => $item->gacha_prize_id,
                        'source_event_id' => $pack->drawTransaction?->gacha_event_id,
                        'prize_name_snapshot' => $item->prize_name_snapshot,
                        'prize_image_snapshot' => $item->prize_image_snapshot,
                        'rarity_code_snapshot' => $item->rarity_code
                            ?? $item->drawResult?->rarity_code
                            ?? $item->prize?->rarity_code
                            ?? null,
                        'prize_type' => $item->prize_type,
                        'sellback_price' => (int) $item->sellback_price,
                        'status' => 'stored',
                    ]
                );

                $item->update([
                    'revealed_at' => now(),
                ]);

                $revealedItems[] = [
                    'draw_result_id' => $item->draw_result_id,
                    'inventory_id' => $inventory->id,
                    'position' => count($revealedItems) + 1,
                    'name' => $item->prize_name_snapshot,
                    'image' => $item->prize_image_snapshot,
                    'rarity_code' => $item->rarity_code,
                    'sellback_price' => (int) $item->sellback_price,
                    'token_reward' => (int) ($item->token_reward ?? 0),
                    'prize_type' => $item->prize_type,
                    'inventory_status' => $inventory->status,
                ];
            }

            $pack->update([
                'status' => 'opened',
                'opened_at' => now(),
            ]);

            return [
                'pack' => [
                    'id' => $pack->id,
                    'pack_no' => $pack->pack_no,
                    'status' => $pack->status,
                ],
                'results' => $revealedItems,
            ];
        });

        return response()->json([
            'message' => 'Pack berhasil dibuka.',
            'data' => $result,
        ]);
    }
}