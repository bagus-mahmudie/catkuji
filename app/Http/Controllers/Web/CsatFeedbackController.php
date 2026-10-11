<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CsatFeedback;
use Illuminate\Http\Request;

class CsatFeedbackController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'shipment_order_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('shipment_orders', 'id')->where('user_id', $user->id)],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:2000'],
            'agree_terms' => ['required', 'accepted'],
        ]);

        $feedback = CsatFeedback::query()->updateOrCreate(
            [
                'user_id' => $user->id,
            ],
            [
                'shipment_order_id' => $validated['shipment_order_id'] ?? null,
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
                'agree_terms' => true,
            ],
        );

        return response()->json([
            'message' => 'Review berhasil disimpan.',
            'data' => $feedback,
        ]);
    }

    public function eligibility(Request $request)
    {
        $hasSubmitted = CsatFeedback::query()
            ->where('user_id', $request->user()->id)
            ->exists();

        return response()->json([
            'eligible' => ! $hasSubmitted,
            'has_submitted' => $hasSubmitted,
        ]);
    }
}