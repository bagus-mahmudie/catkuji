<?php

namespace App\Services\Gacha;

use Illuminate\Support\Collection;

class PrizeSelectorService
{
    public function pick($prizes)
    {
        $available = collect($prizes)
            ->filter(function ($prize) {
                return (int) ($prize->current_stock ?? 0) > 0
                    && (int) ($prize->draw_weight ?? 0) > 0;
            })
            ->values();

        if ($available->isEmpty()) {
            throw new \Exception('Tidak ada hadiah yang tersedia untuk di-draw.');
        }

        $totalWeight = (int) $available->sum(function ($prize) {
            return (int) ($prize->draw_weight ?? 0);
        });

        if ($totalWeight <= 0) {
            throw new \Exception('Konfigurasi peluang hadiah tidak valid.');
        }

        $random = random_int(1, $totalWeight);

        $currentWeight = 0;

        foreach ($available as $prize) {
            $currentWeight += (int) ($prize->draw_weight ?? 0);

            if ($random <= $currentWeight) {
                return $prize;
            }
        }

        throw new \Exception('Gagal memilih hadiah. Silakan coba lagi.');
    }
}