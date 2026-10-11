<?php

namespace App\Http\Controllers\Web;

use App\Actions\Inventory\BulkSellbackInventoryAction;
use App\Http\Controllers\Controller;
use App\Models\UserInventory;
use App\Models\UserPack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InventorySellbackController extends Controller
{
    public function preview(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.gacha_prize_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $previewItems = $this->buildPreviewItems($request->user()->id, $validated['items']);

        if (empty($previewItems)) {
            return redirect()
                ->route('inventory.index')
                ->with('error', 'Pilih minimal 1 hadiah untuk dijual kembali.');
        }

        session([
            'inventory_sellback_preview' => $previewItems,
        ]);

        return redirect()->route('inventory.sellback.confirm');
    }

    public function confirm(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $previewItems = session('inventory_sellback_preview', []);
        $successResult = session('inventory_sellback_success');

        if (empty($previewItems) && empty($successResult)) {
            return redirect()
                ->route('inventory.index')
                ->with('error', 'Belum ada item yang dipilih untuk dijual kembali.');
        }

        return Inertia::render('Inventory/SellbackConfirm', [
            'items' => $previewItems,
            'successResult' => $successResult,
            ...$this->inventoryPageSharedData($user),
        ]);
    }

    public function checkout(Request $request, BulkSellbackInventoryAction $bulkSellbackInventoryAction): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.gacha_prize_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $bulkSellbackInventoryAction->execute(
                $request->user(),
                $validated['items']
            );

            session()->forget('inventory_sellback_preview');

            return redirect()
                ->route('inventory.sellback.confirm')
                ->with('inventory_sellback_success', $result);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Terjadi kesalahan saat memproses sellback.');
        }
    }

    protected function buildPreviewItems(int $userId, array $requestedItems): array
    {
        $result = [];

        foreach ($requestedItems as $requestedItem) {
            $gachaPrizeId = (int) ($requestedItem['gacha_prize_id'] ?? 0);
            $quantity = (int) ($requestedItem['quantity'] ?? 0);

            if ($gachaPrizeId <= 0 || $quantity <= 0) {
                continue;
            }

            $baseItem = UserInventory::query()
                ->join('gacha_prizes', 'gacha_prizes.id', '=', 'user_inventories.gacha_prize_id')
                ->where('user_inventories.user_id', $userId)
                ->where('user_inventories.status', 'stored')
                ->where('user_inventories.gacha_prize_id', $gachaPrizeId)
                ->where('user_inventories.sellback_price', '>', 0)
                ->selectRaw('
                    user_inventories.gacha_prize_id,
                    MIN(user_inventories.prize_name_snapshot) as name,
                    MIN(user_inventories.prize_image_snapshot) as image,
                    MIN(user_inventories.prize_type) as prize_type,
                    MAX(user_inventories.sellback_price) as sellback_price,
                    MIN(gacha_prizes.market_price) as market_price,
                    MIN(gacha_prizes.rarity_code) as rarity_code,
                    COUNT(*) as available_quantity
                ')
                ->groupBy('user_inventories.gacha_prize_id')
                ->first();

            if (! $baseItem) {
                continue;
            }

            $availableQuantity = (int) $baseItem->available_quantity;
            $selectedQuantity = min($quantity, $availableQuantity);

            if ($selectedQuantity <= 0) {
                continue;
            }

            $sellbackPrice = (float) $baseItem->sellback_price;

            if ($sellbackPrice <= 0) {
                continue;
            }

            $result[] = [
                'gacha_prize_id' => (int) $baseItem->gacha_prize_id,
                'name' => $baseItem->name,
                'image' => $baseItem->image,
                'prize_type' => $baseItem->prize_type,
                'rarity_code' => strtoupper($baseItem->rarity_code ?? 'BRONZE'),
                'sellback_price' => $sellbackPrice,
                'market_price' => (float) ($baseItem->market_price ?? 0),
                'quantity' => $selectedQuantity,
                'max_quantity' => $availableQuantity,
            ];
        }

        return $result;
    }

    protected function inventoryPageSharedData($user): array
    {
        $wallet = $user?->wallet;

        $walletBalance = (int) ($wallet?->balance ?? 0);
        $tokenBalance = (int) ($user?->token_balance ?? 0);
        $catKoinBalance = $walletBalance;

        return [
            'walletBalance' => $walletBalance,
            'tokenBalance' => $tokenBalance,
            'catKoinBalance' => $catKoinBalance,
            'unopenedPackCount' => UserPack::query()
                ->where('user_id', $user->id)
                ->where('status', 'unopened')
                ->count(),
        ];
    }
}