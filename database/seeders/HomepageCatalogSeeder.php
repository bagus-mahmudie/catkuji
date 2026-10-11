<?php

namespace Database\Seeders;

use App\Models\GachaCategory;
use App\Models\GachaEvent;
use App\Models\GachaEventPrize;
use App\Models\GachaPrize;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class HomepageCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'user-3965079a@example.test')->first();

        $categories = [
            [
                'name' => 'Promo Spesial',
                'slug' => 'promo-spesial',
                'description' => 'Promo pilihan dengan hadiah spesial!',
                'sort_order' => 1,
                'code_prefix' => 'event-promo',
                'event_type' => 'promo',
                'images' => [
                    'promo-kuuga.png',
                    'pika-parade.png',
                    'honor-of-kings.png',
                    'voucher-pack.png',
                    'promo-kuuga.png',
                ],
                'event_names' => [
                    'Promo Spesial 001',
                    'Promo Spesial 002',
                    'Promo Spesial 003',
                    'Promo Spesial 004',
                    'Promo Spesial 005',
                ],
                'prize_names' => [
                    'Voucher Promo',
                    'Bundle Promo',
                    'Lucky Bonus',
                    'Special Pack',
                    'Collector Bonus',
                    'Reward Card',
                    'Exclusive Item',
                    'Limited Gift',
                    'Promo Figure',
                    'Premium Voucher',
                ],
            ],
            [
                'name' => 'Gadget',
                'slug' => 'gadget',
                'description' => 'Hadiah gadget keren terkini!',
                'sort_order' => 2,
                'code_prefix' => 'event-gadget',
                'event_type' => 'gadget',
                'images' => [
                    'xiaomi-15t.png',
                    'galaxy-s25.png',
                    'iphone-17-pro.png',
                    'redmi-note.png',
                    'xiaomi-15t.png',
                ],
                'event_names' => [
                    'Gadget Event 001',
                    'Gadget Event 002',
                    'Gadget Event 003',
                    'Gadget Event 004',
                    'Gadget Event 005',
                ],
                'prize_names' => [
                    'Smartphone Prize',
                    'Tablet Prize',
                    'Earbuds Prize',
                    'Smartwatch Prize',
                    'Powerbank Prize',
                    'Keyboard Prize',
                    'Mouse Prize',
                    'Gaming Headset Prize',
                    'Monitor Prize',
                    'Speaker Prize',
                ],
            ],
            [
                'name' => 'TCG',
                'slug' => 'tcg',
                'description' => 'Koleksi kartu TCG favorit',
                'sort_order' => 3,
                'code_prefix' => 'event-tcg',
                'event_type' => 'tcg',
                'images' => [
                    'tcg-pika.png',
                    'tcg-charizard.png',
                    'tcg-brothers.png',
                    'pika-parade.png',
                    'tcg-charizard.png',
                ],
                'event_names' => [
                    'TCG Event 001',
                    'TCG Event 002',
                    'TCG Event 003',
                    'TCG Event 004',
                    'TCG Event 005',
                ],
                'prize_names' => [
                    'Booster Pack',
                    'Rare Card',
                    'Promo Card',
                    'Collector Card',
                    'Deck Box',
                    'Sleeve Pack',
                    'Playmat',
                    'Graded Card',
                    'Special Edition Card',
                    'TCG Bundle',
                ],
            ],
            [
                'name' => 'Ichiban Kuji',
                'slug' => 'ichiban-kuji',
                'description' => 'Koleksi ichiban kuji dan figure anime',
                'sort_order' => 4,
                'code_prefix' => 'event-ichiban',
                'event_type' => 'ichiban-kuji',
                'images' => [
                    'fate-grand-order.png',
                    'demon-thunder.png',
                    'fate-grand-order.png',
                    'demon-thunder.png',
                    'fate-grand-order.png',
                ],
                'event_names' => [
                    'Ichiban Kuji Event 001',
                    'Ichiban Kuji Event 002',
                    'Ichiban Kuji Event 003',
                    'Ichiban Kuji Event 004',
                    'Ichiban Kuji Event 005',
                ],
                'prize_names' => [
                    'Figure Prize',
                    'Acrylic Stand',
                    'Poster Prize',
                    'Mini Figure',
                    'Character Board',
                    'Keychain Prize',
                    'Towel Prize',
                    'Mug Prize',
                    'Premium Figure',
                    'Last One Prize',
                ],
            ],
            [
                'name' => 'Gaming',
                'slug' => 'gaming',
                'description' => 'Surganya para gamers!',
                'sort_order' => 5,
                'code_prefix' => 'event-gaming',
                'event_type' => 'gaming',
                'images' => [
                    'mlbb-diamonds.png',
                    'yakuza-steam.png',
                    'hok-pack.png',
                    'honor-of-kings.png',
                    'mlbb-diamonds.png',
                ],
                'event_names' => [
                    'Gaming Event 001',
                    'Gaming Event 002',
                    'Gaming Event 003',
                    'Gaming Event 004',
                    'Gaming Event 005',
                ],
                'prize_names' => [
                    'Steam Wallet',
                    'Diamond Pack',
                    'Game Voucher',
                    'Console Reward',
                    'Gaming Mouse',
                    'Gaming Keyboard',
                    'Controller Prize',
                    'Gaming Headset',
                    'Battle Pass Prize',
                    'Exclusive Skin Reward',
                ],
            ],
        ];

        foreach ($categories as $categoryIndex => $categoryConfig) {
            $category = GachaCategory::updateOrCreate(
                ['slug' => $categoryConfig['slug']],
                [
                    'name' => $categoryConfig['name'],
                    'slug' => $categoryConfig['slug'],
                    'description' => $categoryConfig['description'],
                    'sort_order' => $categoryConfig['sort_order'],
                ]
            );

            for ($i = 1; $i <= 5; $i++) {
                $code = $categoryConfig['code_prefix'] . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $slug = Str::slug($code);

                $stockTotal = 100 + ($i * 10);
                $stockLeft = 40 + ($i * 5);
                $pricePerDraw = match ($categoryConfig['slug']) {
                    'gadget' => 98,
                    'gaming' => 24,
                    'ichiban-kuji' => 35,
                    default => 15,
                };

                $coinRate = (int) config('gacha.coin_rate', 1000);

                $imagePath = 'events/' . basename($categoryConfig['images'][$i - 1]);

                $event = GachaEvent::updateOrCreate(
                    ['code' => $code],
                    [
                        'category_id' => $category->id,
                        'code' => $code,
                        'slug' => $slug,
                        'name' => $categoryConfig['event_names'][$i - 1],
                        'description' => 'Event ' . $categoryConfig['name'] . ' nomor ' . $i,
                        'banner_image' => $imagePath,
                        'thumbnail_image' => $imagePath,

                        'price_per_draw' => (int) $pricePerDraw,
                        'price_per_multi_draw' => (int) $pricePerDraw * 10,

                        // biarkan 0 atau isi sama, tapi jangan dipakai lagi
                        'price_coin' => 0,
                        'price_token' => 1,

                        'badge_label' => $categoryConfig['slug'] === 'promo-spesial' ? 'Silver' : null,
                        'opened_text' => 'Dibuka ' . (10 + $i) . ' Hari Ini',
                        'stock_left' => $stockLeft,
                        'stock_total' => $stockTotal,
                        'draw_mode' => 'both',
                        'event_type' => $categoryConfig['event_type'],
                        'start_at' => now()->subDays(5),
                        'end_at' => now()->addDays(30),
                        'status' => 'published',
                        'sellback_enabled' => true,
                        'shipping_enabled' => true,
                        'max_draw_per_user_per_day' => null,
                        'sort_order' => $i,
                        'created_by' => $admin?->id,
                        'updated_by' => $admin?->id,
                    ]
                );

                $this->seedPrizesForEvent($event, $categoryConfig['slug'], $categoryConfig['prize_names']);
            }
        }
    }

    protected function seedPrizesForEvent(GachaEvent $event, string $categorySlug, array $prizeNames): void
    {
        $rarities = ['UR', 'SSR', 'SR', 'SR', 'R', 'R', 'C', 'C', 'C', 'C'];
        $weights = [10, 25, 60, 80, 150, 200, 400, 500, 650, 800];

        for ($i = 1; $i <= 10; $i++) {
            $prizeCode = strtolower($event->code) . '-prize-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $slug = Str::slug($prizeCode);
            $rarityCode = strtoupper($rarities[$i - 1]);

            $pricing = $this->priceConfigByRarity($rarityCode, $categorySlug);

            $prize = GachaPrize::updateOrCreate(
                ['sku' => strtoupper($prizeCode)],
                [
                    'sku' => strtoupper($prizeCode),
                    'name' => $prizeNames[$i - 1],
                    'slug' => $slug,
                    'description' => $prizeNames[$i - 1] . ' untuk event ' . $event->name,
                    'image' => 'prizes/thumbnail-prize.png',
                    'category' => $categorySlug,
                    'prize_type' => 'physical',
                    'rarity_code' => $rarityCode,
                    'market_price' => $pricing['market_price'],
                    'sellback_price' => $pricing['sellback_price'],
                    'metadata_json' => [
                        'thumbnail_label' => 'prizes/thumbnail-prize.png',
                    ],
                    'status' => 'active',
                ]
            );

            GachaEventPrize::updateOrCreate(
                [
                    'gacha_event_id' => $event->id,
                    'gacha_prize_id' => $prize->id,
                ],
                [
                    'display_name' => $prize->name,
                    'rarity_code' => $rarityCode,
                    'draw_weight' => $weights[$i - 1],
                    'initial_stock' => 5 + $i,
                    'current_stock' => 5 + $i,
                    'reserved_stock' => 0,
                    'sellback_price_override' => null,
                    'coin_price' => $pricing['coin_price'],
                    'token_reward' => 1,
                    'is_featured' => $i <= 3,
                    'sort_order' => $i,
                ]
            );
        }
    }

    protected function priceConfigByRarity(string $rarityCode, ?string $categorySlug = null): array
    {
        $rarityCode = strtoupper($rarityCode);

        $base = match ($rarityCode) {
            'UR' => [
                'market_price' => 250,
                'sellback_price' => 50,
                'coin_price' => 25,
            ],
            'SSR' => [
                'market_price' => 150,
                'sellback_price' => 30,
                'coin_price' => 15,
            ],
            'SR' => [
                'market_price' => 80,
                'sellback_price' => 15,
                'coin_price' => 8,
            ],
            'R' => [
                'market_price' => 40,
                'sellback_price' => 8,
                'coin_price' => 4,
            ],
            default => [
                'market_price' => 20,
                'sellback_price' => 5,
                'coin_price' => 2,
            ],
        };

        $multiplier = $this->priceMultiplierByCategory($categorySlug);

        return [
            'market_price' => max(1, (int) round($base['market_price'] * $multiplier)),
            'sellback_price' => max(1, (int) round($base['sellback_price'] * $multiplier)),
            'coin_price' => max(1, (int) round($base['coin_price'] * $multiplier)),
        ];
    }

    protected function priceMultiplierByCategory(?string $categorySlug): float
    {
        return match ($categorySlug) {
            'gadget' => 1.40,
            'gaming' => 1.20,
            'ichiban-kuji' => 1.10,
            'promo-spesial' => 0.90,
            'tcg' => 1.00,
            default => 1.00,
        };
    }
}