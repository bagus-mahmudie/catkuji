<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DrawTransaction;
use App\Models\NpsFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NpsFeedbackController extends Controller
{
    public function eligibility(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'eligible' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $drawCount = DrawTransaction::query()
                ->where('user_id', $user->id)
                ->where('status', 'success')
                ->sum('draw_count');

            $hasSubmitted = NpsFeedback::query()
                ->where('user_id', $user->id)
                ->exists();

            return response()->json([
                'eligible' => (int) $drawCount >= 3 && ! $hasSubmitted,
                'draw_count' => (int) $drawCount,
                'has_submitted' => $hasSubmitted,
            ]);
        } catch (\Throwable $e) {
            Log::error('NPS eligibility error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'eligible' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'gacha_event_id' => ['nullable', 'exists:gacha_events,id'],
            'score' => ['required', 'integer', 'min:1', 'max:10'],
            'feedback' => ['nullable', 'string', 'max:2000'],
            'page_url' => ['nullable', 'string', 'max:500'],
            'agree_terms' => ['nullable', 'boolean'],
        ]);

        $score = (int) $validated['score'];

        $type = match (true) {
            $score <= 6 => 'detractor',
            $score <= 8 => 'passive',
            default => 'promoter',
        };

        $feedback = NpsFeedback::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'gacha_event_id' => $validated['gacha_event_id'] ?? null,
                'score' => $score,
                'type' => $type,
                'feedback' => $validated['feedback'] ?? null,
                'page_url' => $validated['page_url'] ?? null,
                'agree_terms' => (bool) ($validated['agree_terms'] ?? false),
            ]
        );

        return response()->json([
            'message' => 'Feedback berhasil disimpan.',
            'data' => $feedback,
        ]);
    }
}