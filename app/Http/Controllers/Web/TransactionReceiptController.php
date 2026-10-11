<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TransactionReceiptController extends Controller
{
    public function topup(Request $request, PaymentTransaction $paymentTransaction)
    {
        abort_unless($paymentTransaction->user_id === $request->user()->id, 403);

        abort_unless(
            $paymentTransaction->purpose === 'topup' &&
            strtolower((string) $paymentTransaction->status) === 'paid',
            404
        );

        $coins = (int) floor((float) $paymentTransaction->gross_amount / 1000);

        $pdf = Pdf::loadView('pdf.topup-receipt', [
            'transaction' => $paymentTransaction,
            'coins' => $coins,
            'customer' => $request->user(),
            'paymentMethodLabel' => $this->paymentMethodLabel($paymentTransaction->payment_type),
        ])->setPaper('a4');

        return $pdf->download('receipt-' . $paymentTransaction->order_no . '.pdf');
    }

    protected function paymentMethodLabel(?string $value): string
    {
        return match (strtolower((string) $value)) {
            'gopay' => 'GoPay',
            'qris' => 'QRIS',
            'bank_transfer' => 'Bank Transfer',
            'credit_card' => 'Credit Card',
            'echannel' => 'Mandiri Bill',
            'cstore' => 'Convenience Store',
            default => $value ?: '-',
        };
    }
}