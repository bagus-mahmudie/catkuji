<script setup>
import { resolvePrizeImagePath } from "@/Utils/resolvePrizeImage";
import catKoinImage from "@/assets/cat-koin.png";

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const formatCoin = (value) => {
    return Number(value ?? 0).toLocaleString("id-ID");
};

const rarityLabel = (rarity) => {
    const raw = String(rarity ?? "")
        .toUpperCase()
        .trim();

    switch (raw) {
        case "UR":
            return "Last One";
        case "SSR":
            return "Gold";
        case "SR":
            return "Silver";
        case "R":
        case "C":
        default:
            return "Bronze";
    }
};

const rarityClass = (rarity) => {
    const label = rarityLabel(rarity);

    switch (label) {
        case "Last One":
            return "text-[#FF7A66]";
        case "Gold":
            return "text-[#F4B000]";
        case "Silver":
            return "text-[#B9C2D0]";
        default:
            return "text-[#A56A43]";
    }
};

const handleImageError = (event) => {
    event.target.src = "/images/placeholder-prize.png";
};
</script>

<template>
    <div class="flex gap-4 rounded-xl bg-[#0C0F19] p-4">
        <div class="h-32 w-auto shrink-0 overflow-hidden rounded-xl">
            <img
                :src="resolvePrizeImagePath(product.image)"
                :alt="product.name"
                class="h-full w-full object-cover"
                @error="handleImageError"
            />
        </div>

        <div class="flex min-w-0 flex-1 flex-col justify-between">
            <div>
                <div
                    :class="[
                        'text-sm font-medium sm:text-base',
                        rarityClass(product.rarity),
                    ]"
                >
                    {{ rarityLabel(product.rarity) }}
                </div>

                <h3
                    class="mt-1 line-clamp-2 text-base leading-tight text-white sm:text-lg sm:leading-6"
                >
                    {{ product.name }}
                </h3>
            </div>

            <div>
                <div class="text-sm text-white/55 sm:text-base">Harga Jual</div>

                <div
                    class="mt-1 flex items-center gap-3 text-base font-medium text-white sm:text-lg"
                >
                    <span>{{ product.qty }}X</span>
                    <span class="flex items-center gap-1 text-white">
                        <img
                            :src="catKoinImage"
                            alt="Cat Koin"
                            class="h-4 w-4 object-contain sm:h-5 sm:w-5"
                        />

                        <span>{{ formatCoin(product.sellback_price) }}</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="shrink-0 text-sm font-medium text-[#FF5A5A] sm:text-base">
            {{ product.qty }}X
        </div>
    </div>
</template>
