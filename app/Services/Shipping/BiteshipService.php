<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BiteshipService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.biteship.base_url'), '/');
        $this->apiKey = (string) config('services.biteship.api_key');

        if ($this->apiKey === '') {
            throw new RuntimeException('BITESHIP_API_KEY belum di-set.');
        }
    }

    protected function client()
    {
        return Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->connectTimeout(10)->timeout(30)->baseUrl($this->baseUrl);
    }

    public function getRates(string $destinationPostalCode, array $items): array
    {
        $payload = [
            'origin_postal_code' => (string) config('services.biteship.origin_postal_code'),
            'destination_postal_code' => (string) $destinationPostalCode,
            'couriers' => (string) config('services.biteship.couriers'),
            'items' => $items,
        ];

        $response = $this->client()->post('/v1/rates/couriers', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'Gagal mengambil ongkir dari Biteship: ' . $response->body()
            );
        }

        return $response->json();
    }

    public function createOrder(array $payload): array
    {
        $response = $this->client()->post('/v1/orders', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'Gagal membuat order Biteship: ' . $response->body()
            );
        }

        return $response->json();
    }

    public function getOrder(string $orderId): array
    {
        $response = $this->client()->get('/v1/orders/' . $orderId);

        if ($response->failed()) {
            throw new RuntimeException(
                'Gagal mengambil detail order Biteship: ' . $response->body()
            );
        }

        return $response->json();
    }
}