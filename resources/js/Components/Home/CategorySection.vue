<script setup>
import { Carousel, Slide } from "vue3-carousel";
import "vue3-carousel/dist/carousel.css";
import { Link } from "@inertiajs/vue3";
import PromoEventCard from "@/Components/Home/PromoEventCard.vue";
import StandardEventCard from "@/Components/Home/StandardEventCard.vue";
import promoLeftBg from "@/assets/promo-left-bg.png";
import { computed, onMounted, onBeforeUnmount, ref } from "vue";

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    slug: {
        type: String,
        default: "",
    },
    subtitle: {
        type: String,
        default: "",
    },
    cardStyle: {
        type: String,
        default: "standard",
    },
    promoLeftBgUrl: {
        type: String,
        default: "",
    },
    categoryGlowColor: {
        type: String,
        default: "#F66455",
    },
    categoryGlowPosition: {
        type: String,
        default: "right",
    },
    promoStartsAt: {
        type: String,
        default: "",
    },
    promoEndsAt: {
        type: String,
        default: "",
    },
    events: {
        type: Array,
        default: () => [],
    },
});

const isPromoSection = computed(() => props.cardStyle === "promo");

const resolvedPromoLeftBg = computed(() => {
    return props.promoLeftBgUrl || promoLeftBg;
});

const resolvedCategoryGlowColor = computed(() => {
    const color = String(props.categoryGlowColor || "").trim();

    if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
        return color;
    }

    return "#F66455";
});

const resolvedCategoryGlowPosition = computed(() => {
    return props.categoryGlowPosition === "left" ? "left" : "right";
});

const categoryGlowStyle = computed(() => ({
    "--category-glow-color": resolvedCategoryGlowColor.value,
}));

const isMobileOrTablet = ref(false);

const checkIsMobileOrTablet = () => {
    isMobileOrTablet.value = window.innerWidth < 1024;
};

const now = ref(Date.now());
let countdownTimer = null;

const promoEndTime = computed(() => {
    const time = new Date(props.promoEndsAt).getTime();

    return Number.isFinite(time) ? time : null;
});

const hasPromoCountdown = computed(() => {
    if (!isPromoSection.value) return false;

    const start = String(props.promoStartsAt || "").trim();
    const end = String(props.promoEndsAt || "").trim();

    if (!start || !end) return false;

    const endTime = new Date(end).getTime();

    return Number.isFinite(endTime);
});

const promoRemainingMs = computed(() => {
    if (!hasPromoCountdown.value || !promoEndTime.value) return 0;

    return Math.max(0, promoEndTime.value - now.value);
});

const promoCountdown = computed(() => {
    const totalSeconds = Math.floor(promoRemainingMs.value / 1000);

    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    if (days > 0) {
        return {
            first: String(days).padStart(2, "0"),
            second: String(hours).padStart(2, "0"),
            third: String(minutes).padStart(2, "0"),
            firstLabel: "Days",
            secondLabel: "Hours",
            thirdLabel: "Mins",
        };
    }

    return {
        first: String(hours).padStart(2, "0"),
        second: String(minutes).padStart(2, "0"),
        third: String(seconds).padStart(2, "0"),
        firstLabel: "Hours",
        secondLabel: "Mins",
        thirdLabel: "Secs",
    };
});

onMounted(() => {
    checkIsMobileOrTablet();
    window.addEventListener("resize", checkIsMobileOrTablet);

    countdownTimer = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    window.removeEventListener("resize", checkIsMobileOrTablet);

    if (countdownTimer) {
        clearInterval(countdownTimer);
    }
});

const shouldEnableCarousel = computed(() => {
    if (isMobileOrTablet.value) return props.events.length > 0;
    return props.events.length > 3;
});

const mobileItemsToShow = computed(() => {
    const count = props.events.length;

    if (count <= 1) return 1.15;
    if (count === 2) return 2.15;
    if (count === 3) return 3.15;

    return 3.15;
});

const carouselBreakpoints = {
    0: {
        itemsToShow: 2.2,
        snapAlign: "start",
    },
    640: {
        itemsToShow: 2.3,
        snapAlign: "start",
    },
    768: {
        itemsToShow: 2.5,
        snapAlign: "start",
    },
    1024: {
        itemsToShow: 3.2,
        snapAlign: "start",
    },
    1280: {
        itemsToShow: 3.5,
        snapAlign: "start",
    },
};

const fallbackSubtitle = computed(() => {
    if (props.subtitle) return props.subtitle;

    const subtitles = {
        gaming: "Surganya para gamers!",
        tcg: "Koleksi kartu TCG favorit",
        gadget: "Hadiah gadget keren terkini!",
        "ichiban-kuji": "Gacha resmi dari Bandai Jepang",
    };

    return subtitles[props.slug] || "";
});
</script>

<template>
    <section
        class="category-section-shell-custom relative isolate mt-10"
        :style="categoryGlowStyle"
    >
        <div
            class="category-section-bottom-glow-wrap-custom"
            aria-hidden="true"
        >
            <div
                class="category-section-bottom-glow-custom"
                :class="
                    resolvedCategoryGlowPosition === 'left'
                        ? 'category-section-bottom-glow-left-custom'
                        : 'category-section-bottom-glow-right-custom'
                "
            ></div>
        </div>
        <div class="relative z-10 mb-5 flex items-center justify-between gap-4">
            <div>
                <h2
                    class="sm:text-3xl text-xl font-medium leading-tight text-white"
                >
                    {{ title }}
                </h2>

                <p
                    v-if="fallbackSubtitle"
                    class="mt-1 text-sm break-words sm:text-base text-white/55"
                >
                    {{ fallbackSubtitle }}
                </p>
            </div>

            <!-- <div
        v-if="isPromoSection"
        class="flex items-center gap-2"
      >
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/[0.05] text-[18px] font-semibold text-white">
          {{ promoCountdown.hour }}
        </div>
        <span class="text-xl font-semibold text-white/70">:</span>
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/[0.05] text-[18px] font-semibold text-white">
          {{ promoCountdown.minute }}
        </div>
        <span class="text-xl font-semibold text-white/70">:</span>
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/[0.05] text-[18px] font-semibold text-white">
          {{ promoCountdown.second }}
        </div>
      </div> -->

            <div
                v-if="hasPromoCountdown"
                class="flex shrink-0 items-center gap-1.5 sm:gap-2"
            >
                <div
                    class="flex h-10 w-10 flex-col items-center justify-center rounded-full bg-white/[0.05] text-sm font-semibold leading-none text-white sm:h-12 sm:w-12 sm:text-[18px]"
                >
                    {{ promoCountdown.first }}
                </div>

                <span class="text-lg font-semibold text-white/70 sm:text-xl"
                    >:</span
                >

                <div
                    class="flex h-10 w-10 flex-col items-center justify-center rounded-full bg-white/[0.05] text-sm font-semibold leading-none text-white sm:h-12 sm:w-12 sm:text-[18px]"
                >
                    {{ promoCountdown.second }}
                </div>

                <span class="text-lg font-semibold text-white/70 sm:text-xl"
                    >:</span
                >

                <div
                    class="flex h-10 w-10 flex-col items-center justify-center rounded-full bg-white/[0.05] text-sm font-semibold leading-none text-white sm:h-12 sm:w-12 sm:text-[18px]"
                >
                    {{ promoCountdown.third }}
                </div>
            </div>

            <Link
                v-else-if="!isPromoSection"
                :href="route('categories.show', slug)"
                class="inline-flex items-center gap-2 rounded-lg sm:rounded-xl bg-white/[0.04] px-3 py-2 sm:px-5 sm:py-3 text-xs sm:text-base font-medium text-white transition hover:bg-white/[0.08]"
            >
                <span>Selengkapnya</span>
                <i class="bi bi-chevron-right text-xs sm:text-sm" />
            </Link>
        </div>

        <div
            v-if="events.length"
            :class="[
                'relative z-10 max-w-full ',
                isPromoSection ? 'promo-section-shell rounded-xl' : '',
            ]"
        >
            <img
                v-if="isPromoSection"
                :src="resolvedPromoLeftBg"
                alt="Promo Accent"
                class="promo-fixed-accent"
            />

            <div
                :class="[
                    'relative z-10',
                    isPromoSection
                        ? 'sm:pl-[80px] sm:pt-[40px] pl-[40px] pt-[20px] pr-0'
                        : 'pl-0 pr-0',
                ]"
                @wheel.passive
            >
                <Carousel
                    v-if="shouldEnableCarousel"
                    :items-to-show="mobileItemsToShow"
                    :wrap-around="false"
                    :transition="500"
                    snap-align="start"
                    :breakpoints="carouselBreakpoints"
                    :autoplay="0"
                    :mouse-drag="true"
                    :touch-drag="true"
                    class="event-carousel"
                >
                    <Slide v-for="event in events" :key="event.id">
                        <div class="w-full">
                            <PromoEventCard
                                v-if="isPromoSection"
                                :event="event"
                                :glow-color="resolvedCategoryGlowColor"
                            />
                            <StandardEventCard
                                v-else
                                :event="event"
                                :glow-color="resolvedCategoryGlowColor"
                            />
                        </div>
                    </Slide>
                </Carousel>

                <div
                    v-else
                    :class="[
                        'grid gap-4',
                        isPromoSection
                            ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3'
                            : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
                    ]"
                >
                    <div v-for="event in events" :key="event.id">
                        <PromoEventCard
                            v-if="isPromoSection"
                            :event="event"
                            :glow-color="resolvedCategoryGlowColor"
                        />
                        <StandardEventCard
                            v-else
                            :event="event"
                            :glow-color="resolvedCategoryGlowColor"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="relative z-10 rounded-xl border border-dashed border-white/10 bg-white/[0.03] px-6 py-8 text-sm text-white/50"
        >
            Belum ada item pada kategori ini.
        </div>
    </section>
</template>

<style scoped>
.promo-section-shell {
    padding: 0px 0 40px;
}

.promo-fixed-accent {
    position: absolute;
    left: 0;
    height: auto;
    width: 300px;
    object-fit: cover;
    z-index: 1;
    pointer-events: none;
}

:deep(.event-carousel) {
    max-width: 100%;
    overflow: hidden;
}

:deep(.event-carousel .carousel__viewport) {
    overflow: hidden;
    padding-top: 10px;
}

.category-section-shell-custom {
    --category-glow-color: #f66455;
    position: relative;
    max-width: 100%;
    overflow: visible;
}

/* @media (max-width: 767px) {
    .category-section-bottom-glow-custom {
        bottom: -90px;
        width: 320px;
        height: 220px;
        filter: blur(54px);
    }

    .category-section-bottom-glow-left-custom {
        left: -110px;
    }

    .category-section-bottom-glow-right-custom {
        right: -110px;
    }
} */

:deep(.event-carousel .carousel__track) {
    align-items: stretch;
}

:deep(.event-carousel .carousel__slide) {
    padding-right: 10px;
}

@media (max-width: 1279px) {
    .promo-fixed-accent {
        height: auto;
        width: 280px;
    }
}

@media (max-width: 767px) {
    .promo-fixed-accent {
        height: auto;
        width: 175px;
    }

    :deep(.event-carousel .carousel__slide) {
        padding-right: 8px;
    }
}

.category-section-bottom-glow-wrap-custom {
    position: absolute;
    left: 0;
    right: 0;
    bottom: -120px;
    z-index: 0;
    height: 260px;
    pointer-events: none;
    overflow: visible;
}

.category-section-bottom-glow-custom {
    position: absolute;
    bottom: 0;
    width: min(520px, 72vw);
    height: 260px;
    border-radius: 9999px;
    background: radial-gradient(
        circle,
        color-mix(in srgb, var(--category-glow-color) 44%, transparent) 0%,
        color-mix(in srgb, var(--category-glow-color) 22%, transparent) 38%,
        transparent 72%
    );
    filter: blur(58px);
    opacity: 0.75;
    pointer-events: none;
}

.category-section-bottom-glow-left-custom {
    left: 0;
    transform: translateX(-50%);
}

.category-section-bottom-glow-right-custom {
    right: 0;
    transform: translateX(50%);
}

:deep(.event-carousel),
:deep(.event-carousel .carousel__viewport),
:deep(.event-carousel .carousel__track),
:deep(.event-carousel .carousel__slide) {
    touch-action: pan-y;
    overscroll-behavior: auto;
}
</style>
