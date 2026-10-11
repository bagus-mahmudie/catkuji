<?php

return [
    'coin_rate' => env('GACHA_COIN_RATE', 1000),

    'topup_packages' => [
        [
            'coin' => 10,
            'amount' => 10000,
            'label' => '10 Coin',
        ],
        [
            'coin' => 20,
            'amount' => 20000,
            'label' => '20 Coin',
        ],
        [
            'coin' => 50,
            'amount' => 50000,
            'label' => '50 Coin',
        ],
        [
            'coin' => 100,
            'amount' => 100000,
            'label' => '100 Coin',
        ],
    ],
];