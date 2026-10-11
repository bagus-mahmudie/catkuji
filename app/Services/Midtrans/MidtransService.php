<?php

namespace App\Services\Midtrans;

use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransService
{
    public function createSnapTransaction(array $payload)
    {
        return Snap::createTransaction($payload);
    }

    public function getSnapToken(array $payload): string
    {
        return Snap::getSnapToken($payload);
    }

    public function parseNotification(): Notification
    {
        return new Notification();
    }

    public function getTransactionStatus(string $orderId)
    {
        return Transaction::status($orderId);
    }

    public function __construct()
    {
        $serverKey = config('midtrans.server_key');
        $clientKey = config('midtrans.client_key');

        if (blank($serverKey) || blank($clientKey)) {
            throw new \RuntimeException('Midtrans server key / client key belum di-set di file .env');
        }

        Config::$isProduction = (bool) config('midtrans.is_production');
        Config::$serverKey = $serverKey;
        Config::$clientKey = $clientKey;
        Config::$isSanitized = (bool) config('midtrans.is_sanitized');
        Config::$is3ds = (bool) config('midtrans.is_3ds');
    }

    public function verifySignature(
        string $orderId,
        string $statusCode,
        string $grossAmount,
        string $signatureKey
    ): bool {
        $serverKey = (string) config('midtrans.server_key');

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        return hash_equals($expected, $signatureKey);
    }

    public function mapTransactionStatus(string $transactionStatus, ?string $fraudStatus = null): string
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'challenge' ? 'pending' : 'paid',
            'settlement' => 'paid',
            'pending' => 'pending',
            'deny', 'failure' => 'failed',
            'expire' => 'expired',
            'cancel' => 'cancelled',
            'refund', 'partial_refund', 'chargeback', 'partial_chargeback' => 'refunded',
            'authorize' => 'pending',
            default => 'pending',
        };
    }

    public function shouldMarkAsPaid(string $transactionStatus, ?string $fraudStatus = null): bool
    {
        return $this->mapTransactionStatus($transactionStatus, $fraudStatus) === 'paid';
    }
}