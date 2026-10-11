<?php

namespace App\Http\Controllers\Web;

use App\Actions\Gacha\ExecuteDrawAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gacha\DrawRequest;
use App\Models\GachaEvent;

class DrawController extends Controller
{

    public function store(DrawRequest $request, GachaEvent $event, ExecuteDrawAction $action)
    {
        try {
            $result = $action->execute(
                $request->user(),
                $event,
                (int) $request->draw_count,
                (string) $request->request_idempotency_key
            );

            return response()->json([
                'message' => 'Draw berhasil',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Gagal draw',
            ], 422);
        }
    }
}