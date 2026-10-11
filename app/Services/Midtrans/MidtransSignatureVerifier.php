<?php

namespace App\Services\Midtrans;

class MidtransSignatureVerifier
{
    public function isValid(array $payload): bool
    {
        $orderId = (string) data_get($payload, 'order_id');
        $statusCode = (string) data_get($payload, 'status_code');
        $grossAmount = (string) data_get($payload, 'gross_amount');
        $signatureKey = (string) data_get($payload, 'signature_key');

        if (
            $orderId === '' ||
            $statusCode === '' ||
            $grossAmount === '' ||
            $signatureKey === ''
        ) {
            return false;
        }

        $serverKey = (string) config('midtrans.server_key');

        if ($serverKey === '') {
            return false;
        }

        $expectedSignature = hash(
            'sha512',
            $orderId . $statusCode . $grossAmount . $serverKey
        );

        return hash_equals($expectedSignature, $signatureKey);
    }
}