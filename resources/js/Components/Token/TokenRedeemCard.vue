<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import tokenImage from "@/assets/token-silver.png";
import { getPrizeImage } from "@/Utils/prizeImages";

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
});

const imageSrc = computed(() => {
    return getPrizeImage(props.item?.image);
});

const formattedTokenPrice = computed(() =>
    Number(props.item?.token_price ?? 0).toLocaleString("id-ID"),
);

const stockText = computed(() => {
    return `${props.item?.stock_left ?? 0}/${props.item?.stock_total ?? 0}`;
});
</script>

<template>
    <Link
        :href="route('token.show', item.slug)"
        class="group block w-full overflow-hidden rounded-xl bg-[#101521] shadow-[0_10px_30px_rgba(0,0,0,0.2)] transition duration-200 hover:-translate-y-1 hover:bg-[#101A2D]"
    >
        <div class="w-full rounded-xl p-2 sm:rounded-xl sm:p-3">
            <div class="relative overflow-hidden">
                <img
                    v-if="imageSrc"
                    :src="imageSrc"
                    :alt="item.name"
                    class="h-[150px] w-full rounded-[12px] object-cover sm:h-[220px] sm:rounded-xl lg:h-[240px]"
                    loading="lazy"
                    @error="$event.target.src = '/images/placeholder-prize.png'"
                />

                <div
                    v-else
                    class="flex h-[150px] items-center justify-center rounded-xl bg-white/[0.04] text-sm text-white/45 sm:h-[220px] lg:h-[240px]"
                >
                    Gambar belum tersedia
                </div>
            </div>
        </div>

        <div class="space-y-2 px-2 pb-4 sm:px-4">
            <div
                v-if="item.event_name"
                class="line-clamp-1 text-xs text-white/45"
            >
                {{ item.event_name }}
            </div>

            <h3
                class="line-clamp-2 min-h-[40px] text-sm font-medium leading-5 text-white sm:min-h-[56px] sm:text-lg sm:leading-7"
            >
                {{ item.name }}
            </h3>

            <div class="text-xs text-white/60 sm:text-sm">
                Sisa : {{ stockText }}
            </div>

            <div
                class="inline-flex items-center gap-2 text-sm font-semibold text-white sm:text-lg"
            >
                <img
                    :src="tokenImage"
                    alt="Token"
                    class="h-4 w-4 object-contain sm:h-5 sm:w-5"
                />

                <span>{{ formattedTokenPrice }}</span>
            </div>
        </div>
    </Link>
</template>
