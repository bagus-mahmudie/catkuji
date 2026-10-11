<?php

namespace App\Http\Controllers\Web;

use App\Actions\Payment\CreateTopupTransactionAction;
use App\Actions\Payment\HandleMidtransCallbackAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\TopupRequest;
use App\Models\PaymentTransaction;
use App\Services\Midtrans\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{
    public function store(TopupRequest $request, CreateTopupTransactionAction $action)
    {
        $payment = $action->execute($request->user(), $request->validated('amount'));

        return response()->json([
            'message' => 'Top up transaction created.',
            'data' => [
                'id' => $payment->id,
                'order_no' => $payment->order_no,
                'snap_token' => $payment->snap_token,
                'snap_redirect_url' => $payment->snap_redirect_url,
                'amount' => $payment->gross_amount,
                'status' => $payment->status,
            ],
        ]);
    }

    public function callback(Request $request, HandleMidtransCallbackAction $action)
    {
        $payload = $request->json()->all() ?: $request->all();

        Log::info('Midtrans callback received', [
            'order_id' => $payload['order_id'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'status_code' => $payload['status_code'] ?? null,
            'payment_type' => $payload['payment_type'] ?? null,
        ]);

        try {
            $payment = $action->execute($payload, verifySignature: true);

            Log::info('Midtrans callback processed successfully', [
                'payment_id' => $payment?->id,
                'order_id' => $payment?->midtrans_order_id,
                'status' => $payment?->status,
            ]);

            return response()->json([
                'message' => 'OK',
                'order_id' => $payment?->midtrans_order_id,
                'status' => $payment?->status,
            ]);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Invalid Midtrans signature.') {
                Log::warning('Midtrans callback rejected due to invalid signature', [
                    'order_id' => $payload['order_id'] ?? null,
                    'transaction_status' => $payload['transaction_status'] ?? null,
                    'status_code' => $payload['status_code'] ?? null,
                ]);

                return response()->json([
                    'message' => 'Invalid signature.',
                ], 403);
            }

            Log::error('Midtrans callback runtime error', [
                'message' => $e->getMessage(),
                'order_id' => $payload['order_id'] ?? null,
            ]);

            return response()->json([
                'message' => 'Callback failed.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Midtrans callback error', [
                'message' => $e->getMessage(),
                'order_id' => $payload['order_id'] ?? null,
            ]);

            return response()->json([
                'message' => 'Callback failed.',
            ], 500);
        }
    }

    public function sync(
        Request $request,
        HandleMidtransCallbackAction $action,
        MidtransService $midtransService
    ) {
        $validated = $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        $payment = PaymentTransaction::query()
            ->where('midtrans_order_id', $validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $status = $midtransService->getTransactionStatus($payment->midtrans_order_id);

        $result = $action->execute([
            'order_id' => $payment->midtrans_order_id,
            'transaction_status' => $status->transaction_status ?? null,
            'fraud_status' => $status->fraud_status ?? null,
            'payment_type' => $status->payment_type ?? null,
            'gross_amount' => $status->gross_amount ?? $payment->gross_amount,
            'settlement_time' => $status->settlement_time ?? null,
            'expiry_time' => $status->expiry_time ?? null,
            'transaction_time' => $status->transaction_time ?? null,
        ], verifySignature: false);

        return response()->json([
            'message' => 'Payment synced.',
            'data' => [
                'id' => $result?->id,
                'order_no' => $result?->order_no,
                'status' => $result?->status,
                'amount' => (float) $result?->gross_amount,
                'product' => ((int) floor((float) $result?->gross_amount / 1000)) . ' Cat Koin',
                'payment_type' => $result?->payment_type,
                'paid_at' => $result?->paid_at,
                'created_at' => $result?->created_at,
            ],
        ]);
    }
}