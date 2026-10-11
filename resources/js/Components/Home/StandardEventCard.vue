<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import catKoinImage from "@/assets/cat-koin.png";
import { getEventImage } from "@/Utils/eventImages";
import { useWishlist } from "@/Utils/useWishlist";

const props = defineProps({
    event: Object,
    glowColor: {
        type: String,
        default: "#F66455",
    },
});

const { isWishlisted, toggleWishlist } = useWishlist(() => props.event?.id);
const COIN_RATE = 1000;

const thumbnailSrc = computed(() =>
    getEventImage(props.event?.thumbnail_image),
);

const formattedCoin = computed(() =>
    Number(props.event?.price_per_draw ?? 0).toLocaleString("id-ID"),
);

const formattedPrice = computed(() =>
    Number((props.event?.price_per_draw ?? 0) * COIN_RATE).toLocaleString(
        "id-ID",
    ),
);

const getStockPercentage = (stockLeft, stockTotal) => {
    const left = Number(stockLeft ?? 0);
    const total = Number(stockTotal ?? 0);

    if (total <= 0) return 0;

    return Math.max(0, Math.min(100, (left / total) * 100));
};

const openedText = computed(
    () => props.event?.opened_text ?? "Dibuka 24 Hari Ini",
);

const resolvedGlowColor = computed(() => {
    const color = String(props.glowColor || "").trim();

    if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
        return color;
    }

    return "#F66455";
});

const cardGlowStyle = computed(() => ({
    "--card-glow-color": resolvedGlowColor.value,
}));
</script>

<template>
    <Link
        :href="route('events.show', event.slug)"
        class="event-card-glow-shell group relative isolate block w-full overflow-hidden rounded-xl bg-[#0D1423] transition duration-200 hover:-translate-y-1"
        :style="cardGlowStyle"
    >
        <div class="event-card-glow-layer"></div>
        <div class="relative z-10">
            <div class="w-full rounded-xl p-2 sm:rounded-xl sm:p-3">
                <div class="relative overflow-hidden">
                    <img
                        v-if="thumbnailSrc"
                        :src="thumbnailSrc"
                        :alt="event.name"
                        class="h-[150px] w-full rounded-[12px] sm:rounded-xl object-cover sm:h-[220px] lg:h-[240px]"
                    />
                    <div
                        v-else
                        class="flex h-[296px] items-center justify-center text-base text-white/45"
                    >
                        Thumbnail belum tersedia
                    </div>

                    <!-- <div class="absolute left-1.5 top-1.5 sm:left-3 sm:top-3">
                    <span
                        v-if="
                            event.badge_label && event.badge_label.trim() !== ''
                        "
                        class="inline-flex items-center rounded-full bg-[#F56B5C] px-2 py-0.5 text-[11px] font-medium text-white sm:px-4 sm:py-1 sm:text-base"
                    >
                        {{ event.badge_label }}
                    </span>
                </div> -->

                    <button
                        type="button"
                        @click="toggleWishlist"
                        :aria-pressed="isWishlisted"
                        class="absolute right-1.5 top-1.5 inline-flex h-6 w-6 items-center justify-center rounded-full backdrop-blur-sm transition sm:right-3 sm:top-3 sm:h-8 sm:w-8"
                        :class="
                            isWishlisted
                                ? 'bg-white text-[#F56B5C]'
                                : 'bg-[#111827]/70 text-white hover:bg-[#1b2435]'
                        "
                    >
                        <i
                            :class="[
                                isWishlisted
                                    ? 'bi bi-heart-fill'
                                    : 'bi bi-heart',
                                'text-[11px] sm:text-sm',
                            ]"
                        />
                    </button>
                </div>
            </div>

            <div class="space-y-2 px-2 sm:px-4 pb-4">
                <div
                    class="flex items-center gap-1 text-[10px] text-[#F56B5C] sm:gap-2 sm:text-sm"
                >
                    <i class="bi bi-unlock sm:text-sm text-[10px]" />
                    <span>{{ openedText }}</span>
                </div>

                <div>
                    <h3
                        class="line-clamp-2 min-h-[32px] text-sm font-medium leading-4 text-white sm:min-h-[56px] sm:text-lg sm:leading-7"
                    >
                        {{ event.name }}
                    </h3>
                </div>

                <div
                    class="flex items-center justify-between text-xs text-white/60 sm:text-base"
                >
                    <span>Sisa : {{ event.stock_left ?? 0 }}</span>
                    <span>{{ event.stock_total ?? 0 }}</span>
                </div>

                <div
                    class="h-[6px] overflow-hidden rounded-full bg-white/10 sm:h-[8px]"
                >
                    <div
                        class="h-full rounded-full transition-all"
                        :style="{
                            width: `${getStockPercentage(event.stock_left, event.stock_total)}%`,
                            backgroundImage:
                                'repeating-linear-gradient(-45deg, rgba(255,255,255,0.12) 0, rgba(255,255,255,0.12) 8px, transparent 8px, transparent 16px), linear-gradient(to right, #37E0C2, #37E0C2)',
                        }"
                    />
                </div>

                <div class="grid grid-cols-2 sm:gap-4 gap-1">
                    <div>
                        <div
                            class="text-[12px] mt-2 sm:text-base text-white/55"
                        >
                            Harga
                        </div>
                        <div
                            class="mt-1 text-[11px] font-medium text-white sm:text-lg"
                        >
                            Rp {{ formattedPrice }}
                        </div>
                    </div>

                    <div class="text-right">
                        <div
                            class="text-[12px] mt-2 sm:text-base text-white/55"
                        >
                            Koin
                        </div>
                        <div
                            class="mt-1 inline-flex items-center gap-1 sm:gap-2 text-[12px] sm:text-lg font-medium text-white"
                        >
                            <img
                                :src="catKoinImage"
                                alt="Coin"
                                class="h-3 w-3 sm:h-5 sm:w-5 object-contain"
                            />
                            <span>{{ formattedCoin }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Link>
</template>

<style scoped>
.event-card-glow-shell {
    touch-action: manipulation;
    box-shadow:
        0 10px 30px rgba(0, 0, 0, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.03);
}

.event-card-glow-layer {
    position: absolute;
    inset: 0;
    border-radius: 0.75rem;
    pointer-events: none;
    z-index: 0;
    background:
        radial-gradient(
            circle at left top,
            color-mix(in srgb, var(--card-glow-color) 54%, transparent) 0%,
            color-mix(in srgb, var(--card-glow-color) 24%, transparent) 34%,
            transparent 50%
        ),
        linear-gradient(
            to right,
            color-mix(in srgb, var(--card-glow-color) 95%, transparent) 0%,
            color-mix(in srgb, var(--card-glow-color) 45%, transparent) 22%,
            transparent 42%
        ),
        linear-gradient(
            to bottom,
            color-mix(in srgb, var(--card-glow-color) 95%, transparent) 0%,
            color-mix(in srgb, var(--card-glow-color) 45%, transparent) 22%,
            transparent 42%
        );
    background-size:
        360px 300px,
        100% 0px,
        0px 100%;
    background-repeat: no-repeat;
    background-position:
        left top,
        left top,
        left top;
    filter: drop-shadow(
        0 0 14px color-mix(in srgb, var(--card-glow-color) 45%, transparent)
    );
    opacity: 1;
}
</style>
