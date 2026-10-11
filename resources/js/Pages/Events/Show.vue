<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import catKoinImage from "@/assets/cat-koin.png";
import tokenImage from "@/assets/token-silver.png";
import { getEventImage } from "@/Utils/eventImages";
import axios from "axios";
import DrawResultModal from "@/Components/Draw/DrawResultModal.vue";
import { getPrizeImage } from "@/Utils/prizeImages";
import { openAuthModal } from "@/Utils/authModal";
import { useWishlist } from "@/Utils/useWishlist";
import ShareModal from "@/Components/Share/ShareModal.vue";
import NpsFeedbackModal from "@/Components/Nps/NpsFeedbackModal.vue";

const isDrawResultModalOpen = ref(false);
const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    prizes: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            current_page: 1,
            last_page: 1,
            from: null,
            to: null,
            total: 0,
        }),
    },
    rules: {
        type: Array,
        default: () => [],
    },
    tokenBalance: {
        type: Number,
        default: 0,
    },
    catKoinBalance: {
        type: Number,
        default: 0,
    },
    walletBalance: {
        type: Number,
        default: 0,
    },
});

const localPrizeData = ref(
    (props.prizes?.data ?? []).map((item) => ({ ...item })),
);

watch(
    () => props.prizes?.data,
    (items) => {
        localPrizeData.value = (items ?? []).map((item) => ({ ...item }));
    },
    { deep: true },
);

const prizeList = computed(() => localPrizeData.value);

const prizePaginationLinks = computed(() => props.prizes?.links ?? []);

const hasPrizePagination = computed(() => {
    return Number(props.prizes?.last_page ?? 1) > 1;
});

const { isWishlisted, toggleWishlist } = useWishlist(() => props.event?.id);

const page = usePage();

const isLoggedIn = computed(() => {
    return !!page.props.auth?.user;
});

const skipNpsModal = () => {
    isNpsModalOpen.value = false;
    hasCheckedNpsThisSession.value = false;
};

const isNpsModalOpen = ref(false);
const isCheckingNps = ref(false);
const hasCheckedNpsThisSession = ref(false);

const checkNpsEligibility = async () => {
    if (!isLoggedIn.value) return;
    if (isCheckingNps.value) return;
    if (hasCheckedNpsThisSession.value) return;

    try {
        isCheckingNps.value = true;
        hasCheckedNpsThisSession.value = true;

        const response = await axios.get(route("nps-feedback.eligibility"));

        if (response.data?.eligible) {
            isNpsModalOpen.value = true;
        }
    } catch (error) {
        console.error("Gagal cek NPS:", error);
        hasCheckedNpsThisSession.value = false;
    } finally {
        isCheckingNps.value = false;
    }
};

const closeNpsModal = () => {
    isNpsModalOpen.value = false;
};

const continuePlayingFromNps = () => {
    isNpsModalOpen.value = false;
};

const quantity = ref(1);
const isExpanded = ref(false);

const pendingPack = ref(null);
const hasOpenedCurrentPack = ref(false);

const isPrizeModalOpen = ref(false);
const activePrize = ref(null);

const resolveEventImagePath = (image) => {
    return getEventImage(image);
};

const resolvePrizeImagePath = (image) => {
    return getPrizeImage(image);
};

const selectedImage = computed(() => {
    return resolveEventImagePath(props.event?.thumbnail_image);
});

const isHowToPlayModalOpen = ref(false);

const openHowToPlayModal = () => {
    isHowToPlayModalOpen.value = true;
};

const closeHowToPlayModal = () => {
    isHowToPlayModalOpen.value = false;
};

const activePrizeImage = computed(() => {
    if (!activePrize.value?.image) return null;
    return resolvePrizeImagePath(activePrize.value.image);
});

const stockPercentage = computed(() => {
    const left = Number(props.event?.stock_left ?? 0);
    const total = Number(props.event?.stock_total ?? 0);

    if (total <= 0) return 0;
    return Math.max(0, Math.min(100, (left / total) * 100));
});

const formattedWalletBalance = computed(() =>
    Number(props.walletBalance ?? 0).toLocaleString("id-ID"),
);

const MAX_DRAW = 10;

const canIncrease = computed(() => quantity.value < MAX_DRAW);
const canDecrease = computed(() => quantity.value > 1);

const increaseQty = () => {
    if (quantity.value < MAX_DRAW) quantity.value += 1;
};

const decreaseQty = () => {
    if (canDecrease.value) quantity.value -= 1;
};

const unitCoinPrice = computed(() => {
    return Number(props.event?.price_per_draw ?? 0);
});

const totalCoinPrice = computed(() => {
    return unitCoinPrice.value * Number(quantity.value);
});

const hasEnoughCoin = computed(() => {
    return Number(props.catKoinBalance ?? 0) >= Number(totalCoinPrice.value);
});

const formattedTotalCoinPrice = computed(() =>
    Number(totalCoinPrice.value).toLocaleString("id-ID"),
);

const canBuy = computed(() => {
    return (
        props.event?.status === "published" &&
        Number(props.event?.stock_left ?? 0) > 0
    );
});

const openTopupPage = () => {
    window.location.href = route("wallet.index");
};

const isDrawing = ref(false);
const drawResult = ref(null);
const pendingDrawPayload = ref(null);

const executeDraw = async (drawCount) => {
    if (!isLoggedIn.value) {
        openAuthModal("login");
        return;
    }

    if (!canBuy.value || isDrawing.value) return;

    const normalizedCount = Number(drawCount || quantity.value);

    if (Number(props.event?.stock_left ?? 0) < normalizedCount) {
        alert("Stok event tidak cukup");
        return;
    }

    if (
        Number(props.catKoinBalance ?? 0) <
        Number(unitCoinPrice.value) * normalizedCount
    ) {
        alert("Saldo tidak cukup");
        return;
    }

    currentIdempotencyKey.value = generateIdempotencyKey();

    pendingDrawPayload.value = {
        draw_count: normalizedCount,
        request_idempotency_key: currentIdempotencyKey.value,
    };

    /**
     * Ini hanya data sementara untuk menjalankan capsule machine.
     * Belum ada pembelian, belum ada pengurangan coin, belum ada pengurangan stock.
     */
    drawResult.value = {
        event_slug: props.event.slug,
        is_pending_draw: true,
        transaction: {
            draw_count: normalizedCount,
            total_cost: Number(unitCoinPrice.value) * normalizedCount,
            event_name: props.event?.name,
        },
        pack: {
            id: null,
            pack_count: normalizedCount,
            status: "pending",
        },
        results: [],
    };

    pendingPack.value = null;
    hasOpenedCurrentPack.value = false;
    isDrawResultModalOpen.value = true;
};

const confirmPendingDraw = async ({ onSuccess, onError } = {}) => {
    if (!pendingDrawPayload.value || isDrawing.value) return;

    try {
        isDrawing.value = true;

        const res = await axios.post(
            route("events.draw.store", props.event.slug),
            pendingDrawPayload.value,
        );

        currentIdempotencyKey.value = null;
        pendingDrawPayload.value = null;

        const confirmedResult = {
            ...res.data.data,
            event_slug: props.event.slug,
        };

        drawResult.value = confirmedResult;

        applyDrawStockUpdates(res.data.data?.results ?? []);

        pendingPack.value = res.data.data.pack || null;
        hasOpenedCurrentPack.value = false;

        router.reload({
            only: ["event", "walletBalance", "catKoinBalance", "tokenBalance"],
            preserveScroll: true,
            preserveState: true,
            headers: {
                "X-Skip-Global-Loader": "true",
            },
        });

        onSuccess?.(confirmedResult);
    } catch (err) {
        const message = err.response?.data?.message || "Gagal draw";

        pendingDrawPayload.value = null;
        currentIdempotencyKey.value = null;

        onError?.(message);

        isDrawResultModalOpen.value = false;
        drawResult.value = null;
        pendingPack.value = null;
        hasOpenedCurrentPack.value = false;
    } finally {
        isDrawing.value = false;
    }
};

const buyNow = async () => {
    await executeDraw(quantity.value);
};

const drawAgainFromModal = async (payload) => {
    const drawCount = Number(
        payload?.draw_count ?? drawResult.value?.transaction?.draw_count ?? 1,
    );

    isDrawResultModalOpen.value = false;
    drawResult.value = null;
    pendingPack.value = null;
    hasOpenedCurrentPack.value = false;

    await executeDraw(drawCount);
};

const generateIdempotencyKey = () => {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `draw-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
};

const currentIdempotencyKey = ref(null);

const closeDrawResultModal = () => {
    const hasConfirmedPack = !!pendingPack.value;

    if (pendingPack.value && !hasOpenedCurrentPack.value) {
        window.dispatchEvent(
            new CustomEvent("pack:created", {
                detail: { count: 1 },
            }),
        );
    }

    pendingDrawPayload.value = null;
    currentIdempotencyKey.value = null;
    pendingPack.value = null;
    hasOpenedCurrentPack.value = false;
    isDrawResultModalOpen.value = false;
    drawResult.value = null;

    if (hasConfirmedPack) {
        setTimeout(() => {
            checkNpsEligibility();
        }, 300);
    }
};

const goToInventory = () => {
    hasOpenedCurrentPack.value = true;
    pendingPack.value = null;
    isDrawResultModalOpen.value = false;
    window.location.href = route("inventory.index");
};

const stockText = (item) => {
    return `${item.stock_left ?? 0}/${item.stock_total ?? 0}`;
};

const applyDrawStockUpdates = (results = []) => {
    results.forEach((result) => {
        const eventPrizeId = Number(result.gacha_event_prize_id ?? 0);
        const gachaPrizeId = Number(result.gacha_prize_id ?? 0);

        const target = localPrizeData.value.find((item) => {
            return (
                Number(item.id) === eventPrizeId ||
                (gachaPrizeId && Number(item.gacha_prize_id) === gachaPrizeId)
            );
        });

        if (!target) return;

        target.stock_left = Math.max(0, Number(target.stock_left ?? 0) - 1);

        if (
            activePrize.value &&
            Number(activePrize.value.id) === Number(target.id)
        ) {
            activePrize.value = {
                ...activePrize.value,
                stock_left: target.stock_left,
            };
        }
    });
};

const openPrizeModal = (item) => {
    activePrize.value = item;
    isPrizeModalOpen.value = true;
};

const closePrizeModal = () => {
    isPrizeModalOpen.value = false;
    activePrize.value = null;
};

const handleKeydown = (event) => {
    if (event.key !== "Escape") return;

    if (isPrizeModalOpen.value) {
        closePrizeModal();
    }

    if (isHowToPlayModalOpen.value) {
        closeHowToPlayModal();
    }
};

onMounted(() => {
    window.addEventListener("keydown", handleKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener("keydown", handleKeydown);
});

const orderedPrizes = computed(() => {
    const rarityOrder = ["UR", "SSR", "SR", "R", "C"];

    // console.log(
    //     "PRIZES DEBUG:",
    //     props.prizes.map((p) => ({
    //         name: p.name,
    //         rarity: p.rarity_code,
    //     })),
    // );

    return [...prizeList.value].sort((a, b) => {
        const rarityA = rarityOrder.indexOf(
            String(a.rarity_code ?? "").toUpperCase(),
        );
        const rarityB = rarityOrder.indexOf(
            String(b.rarity_code ?? "").toUpperCase(),
        );

        if (rarityA !== rarityB) return rarityA - rarityB;
        return (a.sort_order ?? 0) - (b.sort_order ?? 0);
    });
});

function normalizeRarityLabel(rarity) {
    switch (
        String(rarity ?? "")
            .toUpperCase()
            .trim()
    ) {
        case "UR":
            return "Last One";
        case "SSR":
            return "Gold";
        case "SR":
            return "Silver";
        default:
            return "Bronze";
    }
}

const groupedPrizes = computed(() => {
    const grouped = {
        "Last One": [],
        Gold: [],
        Silver: [],
        Bronze: [],
    };

    orderedPrizes.value.forEach((item) => {
        const label = normalizeRarityLabel(item.rarity_code);
        grouped[label].push(item);
    });

    return Object.fromEntries(
        Object.entries(grouped).filter(([, items]) => items.length > 0),
    );
});

const getSectionTheme = (label) => {
    switch (label) {
        case "Last One":
            return {
                title: "text-[#FF7A66]",
                line: "bg-[#FF7A66]",
                border: "border-[#FF7A66]",
                badge: "bg-[#FF7A66] text-white",
            };
        case "Gold":
            return {
                title: "text-[#F4B000]",
                line: "bg-[#F4B000]",
                border: "border-[#F4B000]",
                badge: "bg-[#F4B000] text-white",
            };
        case "Silver":
            return {
                title: "text-[#B9C2D0]",
                line: "bg-[#B9C2D0]",
                border: "border-[#B9C2D0]",
                badge: "bg-[#B9C2D0] text-[#13203A]",
            };
        default:
            return {
                title: "text-[#A56A43]",
                line: "bg-[#A56A43]",
                border: "border-[#A56A43]",
                badge: "bg-[#A56A43] text-white",
            };
    }
};

const prizeTierLabel = computed(() => {
    return normalizeRarityLabel(activePrize.value?.rarity_code);
});

const prizeRibbonClass = computed(() => {
    const tier = prizeTierLabel.value;

    if (tier === "Last One") {
        return "bg-[#FF7A66] text-white shadow-[0_10px_24px_rgba(255,122,102,0.30)]";
    }

    if (tier === "Gold") {
        return "bg-[#F4B000] text-white shadow-[0_10px_24px_rgba(244,176,0,0.28)]";
    }

    if (tier === "Silver") {
        return "bg-[#B9C2D0] text-[#13203A] shadow-[0_10px_24px_rgba(185,194,208,0.22)]";
    }

    return "bg-[#A56A43] text-white shadow-[0_10px_24px_rgba(165,106,67,0.24)]";
});

const steps = [
    "Buat akun dan pilih kategori atau koleksi yang ingin dimainkan.",
    "Top up koin lalu beli/gacha item yang diinginkan.",
    "Sistem akan mengacak dan langsung menampilkan hadiah yang kamu dapat.",
    "Pilih aksi untuk hadiah: kirim ke alamat, klaim (jika digital), atau jual kembali jadi koin.",
];

const isShareModalOpen = ref(false);

const currentPageUrl = computed(() => {
    if (typeof window === "undefined") return "";

    return window.location.href;
});

const openShareModal = () => {
    isShareModalOpen.value = true;
};

const closeShareModal = () => {
    isShareModalOpen.value = false;
};
</script>

<template>
    <Head :title="event.name" />

    <div class="min-h-screen bg-[#060B16] text-white">
        <div class="mx-auto max-w-[1280px] px-4 pb-0 pt-6 sm:px-6 lg:px-8">
            <AppNavbar @topup-click="openTopupPage" />

            <main class="mt-8">
                <section class="grid gap-5 lg:grid-cols-[1fr_1fr]">
                    <div
                        class="relative min-h-[420px] overflow-hidden rounded-xl bg-[#0D1322] lg:min-h-[640px]"
                    >
                        <template v-if="selectedImage">
                            <img
                                :src="selectedImage"
                                :alt="event.name"
                                class="pointer-events-none absolute inset-0 z-0 h-full w-full scale-125 object-cover blur-sm opacity-60"
                            />
                        </template>

                        <div class="absolute inset-0 z-0 bg-[#0D1322]/20" />

                        <button
                            type="button"
                            @click="toggleWishlist"
                            :aria-pressed="isWishlisted"
                            class="absolute right-4 top-4 z-20 inline-flex h-11 w-11 items-center justify-center rounded-full backdrop-blur transition"
                            :class="
                                isWishlisted
                                    ? 'bg-white text-[#F56B5C]'
                                    : 'bg-white/10 text-white/90 hover:bg-white/15'
                            "
                        >
                            <i
                                :class="[
                                    isWishlisted
                                        ? 'bi bi-heart-fill'
                                        : 'bi bi-heart',
                                    'text-lg',
                                ]"
                            />
                        </button>

                        <div
                            class="relative z-10 flex h-full items-center justify-center p-5 sm:p-6"
                        >
                            <div
                                class="flex w-full items-center justify-center"
                            >
                                <template v-if="selectedImage">
                                    <img
                                        :src="selectedImage"
                                        :alt="event.name"
                                        class="max-h-[420px] max-w-[300px] rounded-xl object-contain sm:min-w-[450px]"
                                    />
                                </template>

                                <template v-else>
                                    <div
                                        class="flex h-[360px] w-full items-center justify-center rounded-xl bg-[linear-gradient(135deg,#1c2438,#0d1322)] text-center text-base text-white/40 sm:h-[500px] lg:h-[620px]"
                                    >
                                        Gambar belum tersedia
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-[#101521] p-5 sm:p-6 lg:p-7">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div
                                    class="flex items-center gap-2 text-sm text-[#FF5A4D]"
                                >
                                    <i class="bi bi-unlock" />
                                    <span>{{
                                        event.opened_text ||
                                        "Dibuka 21 Hari Ini"
                                    }}</span>
                                </div>

                                <h1
                                    class="mt-1 sm:mt-3 max-w-[560px] text-[30px] font-medium leading-[1.12] text-white sm:text-[40px] lg:text-3xl"
                                >
                                    {{ event.name }}
                                </h1>
                            </div>

                            <button
                                type="button"
                                class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white/80 transition hover:bg-white/5 hover:text-white"
                                @click="openShareModal"
                            >
                                <i class="bi bi-share text-lg" />
                            </button>
                        </div>

                        <div>
                            <p
                                :class="[
                                    'mt-3 max-w-[620px]  whitespace-pre-line break-words text-sm leading-6 text-white/75 sm:text-base transition-all',
                                    isExpanded ? '' : 'line-clamp-3',
                                ]"
                            >
                                {{ event.description }}
                            </p>

                            <button
                                v-if="
                                    event.description &&
                                    event.description.length > 120
                                "
                                @click="isExpanded = !isExpanded"
                                class="mt-0 text-sm text-[#42E8E0] hover:underline"
                            >
                                {{
                                    isExpanded
                                        ? "Tampilkan lebih sedikit"
                                        : "Lihat Semua"
                                }}
                            </button>
                        </div>

                        <div
                            class="mt-4 sm:mt-8 flex items-end justify-between text-white"
                        >
                            <div
                                class="text-base font-medium leading-none sm:text-xl"
                            >
                                Sisa : {{ event.stock_left ?? 0 }}
                            </div>
                            <div
                                class="text-base font-medium leading-none text-white/95 sm:text-xl"
                            >
                                {{ event.stock_total ?? 0 }}
                            </div>
                        </div>

                        <div
                            class="mt-3 h-3 w-full overflow-hidden rounded-full bg-white/10"
                        >
                            <div
                                class="h-full rounded-full bg-[repeating-linear-gradient(135deg,#42E8E0_0px,#42E8E0_10px,#58EFE6_10px,#58EFE6_20px)] transition-all duration-300"
                                :style="{ width: `${stockPercentage}%` }"
                            />
                        </div>

                        <div class="mt-6 sm:mt-8">
                            <div
                                class="text-base font-medium text-white sm:text-xl"
                            >
                                Kuantitas
                            </div>

                            <div class="mt-3">
                                <div
                                    class="inline-flex h-10 w-full max-w-[120px] items-center justify-between rounded-xl bg-white/5 px-4"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-xl text-white/80 transition hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                                        :disabled="!canDecrease"
                                        @click="decreaseQty"
                                    >
                                        <i
                                            class="bi bi-dash-lg text-lg font-semibold"
                                        />
                                    </button>

                                    <div
                                        class="text-xl font-medium leading-none text-white"
                                    >
                                        {{ quantity }}
                                    </div>

                                    <button
                                        type="button"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-xl text-white/80 transition hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                                        :disabled="!canIncrease"
                                        @click="increaseQty"
                                    >
                                        <i
                                            class="bi bi-plus-lg text-lg font-semibold"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-white/12 pt-8">
                            <button
                                type="button"
                                class="flex h-[58px] w-full items-center justify-center gap-2 rounded-xl bg-[#F36A5B] px-6 text-lg font-semibold text-white transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="
                                    !isLoggedIn ||
                                    !canBuy ||
                                    !hasEnoughCoin ||
                                    isDrawing
                                "
                                @click="buyNow"
                            >
                                <span>{{
                                    isDrawing ? "Memproses..." : "Beli"
                                }}</span>
                                <img
                                    :src="catKoinImage"
                                    alt="Cat Koin"
                                    class="h-5 w-5 object-contain"
                                />
                                <span>{{ formattedTotalCoinPrice }}</span>
                            </button>

                            <button
                                type="button"
                                class="mt-4 flex h-[56px] w-full items-center justify-center gap-3 rounded-xl bg-[#0E1830] px-6 text-base font-semibold text-white transition hover:bg-[#13203C]"
                                @click="openHowToPlayModal"
                            >
                                <span>Cara Main Catkuji</span>
                                <i class="bi bi-arrow-right text-base" />
                            </button>

                            <p
                                v-if="
                                    Number(walletBalance) <
                                    Number(totalCoinPrice)
                                "
                                class="mt-4 text-sm text-[#FF8B7E]"
                            >
                                Saldo wallet kamu saat ini tersisa
                                {{ formattedWalletBalance }} Cat Koin. Silakan
                                top up dulu untuk melanjutkan.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="mt-10 space-y-10">
                    <template
                        v-for="(items, label) in groupedPrizes"
                        :key="label"
                    >
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <!-- <h2
                                    class="text-lg font-semibold sm:text-xl"
                                    :class="getSectionTheme(label).title"
                                >
                                    {{ label }}
                                </h2> -->

                                <div
                                    class="h-px flex-1 opacity-70"
                                    :class="getSectionTheme(label).line"
                                />
                            </div>

                            <div
                                :class="
                                    label === 'Last One'
                                        ? 'grid grid-cols-1'
                                        : 'grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4'
                                "
                            >
                                <article
                                    v-for="item in items"
                                    :key="item.id"
                                    :class="[
                                        'cursor-pointer overflow-hidden rounded-xl bg-[#101521] transition hover:bg-[#131a29] border-b-4',
                                        getSectionTheme(label).border,
                                    ]"
                                    @click="openPrizeModal(item)"
                                >
                                    <template v-if="label === 'Last One'">
                                        <div
                                            class="grid items-start gap-5 p-4 sm:grid-cols-[260px_1fr] sm:p-5"
                                        >
                                            <div
                                                class="overflow-hidden rounded-xl bg-[#0B1324]"
                                            >
                                                <template
                                                    v-if="
                                                        resolvePrizeImagePath(
                                                            item.image,
                                                        )
                                                    "
                                                >
                                                    <img
                                                        :src="
                                                            resolvePrizeImagePath(
                                                                item.image,
                                                            )
                                                        "
                                                        :alt="item.name"
                                                        class="h-[210px] w-full object-cover sm:h-[230px]"
                                                    />
                                                </template>

                                                <template v-else>
                                                    <div
                                                        class="flex h-[260px] sm:h-[230px] w-full items-center justify-center bg-[linear-gradient(135deg,#1b2438,#10182b)] px-5 text-center text-sm text-white/35"
                                                    >
                                                        Gambar hadiah belum
                                                        tersedia
                                                    </div>
                                                </template>
                                            </div>

                                            <div
                                                class="flex flex-col justify-between"
                                            >
                                                <div>
                                                    <div
                                                        class="text-base font-medium"
                                                        :class="
                                                            getSectionTheme(
                                                                label,
                                                            ).title
                                                        "
                                                    >
                                                        {{ label }}
                                                    </div>

                                                    <h3
                                                        class="mt-3 text-lg font-medium text-white sm:text-xl"
                                                    >
                                                        {{ item.name }}
                                                    </h3>

                                                    <p
                                                        class="mt-2 line-clamp-3 text-sm lg:text-base leading-6 text-white/65"
                                                    >
                                                        {{
                                                            item.description ||
                                                            "Hadiah spesial kategori Last One."
                                                        }}
                                                    </p>
                                                </div>

                                                <div
                                                    class="mt-5 flex items-center justify-between pt-4 sm:max-w-[80%]"
                                                >
                                                    <!-- Harga -->
                                                    <div class="flex flex-col">
                                                        <div
                                                            class="text-sm text-white/50"
                                                        >
                                                            Harga Jual
                                                        </div>
                                                        <div
                                                            class="mt-1 inline-flex items-center gap-2 text-base font-medium text-white"
                                                        >
                                                            <img
                                                                :src="
                                                                    catKoinImage
                                                                "
                                                                alt="Cat Koin"
                                                                class="h-5 w-5 object-contain"
                                                            />
                                                            <span>
                                                                {{
                                                                    Number(
                                                                        item.sellback_price ??
                                                                            0,
                                                                    ).toLocaleString(
                                                                        "id-ID",
                                                                    )
                                                                }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <!-- Divider -->
                                                    <div
                                                        class="h-6 w-px bg-white/20"
                                                    ></div>

                                                    <!-- Stok -->
                                                    <div
                                                        class="flex flex-col items-center"
                                                    >
                                                        <div
                                                            class="text-sm text-white/50"
                                                        >
                                                            Stok
                                                        </div>
                                                        <div
                                                            class="mt-1 text-base font-medium text-white"
                                                        >
                                                            {{
                                                                stockText(item)
                                                            }}
                                                        </div>
                                                    </div>

                                                    <!-- Divider -->
                                                    <div
                                                        class="h-6 w-px bg-white/20"
                                                    ></div>

                                                    <!-- Token -->
                                                    <div
                                                        class="flex flex-col items-start"
                                                    >
                                                        <div
                                                            class="text-sm text-white/50"
                                                        >
                                                            Hadiah tiket
                                                        </div>
                                                        <div
                                                            class="mt-1 inline-flex items-center gap-2 text-base font-medium text-white"
                                                        >
                                                            <img
                                                                :src="
                                                                    tokenImage
                                                                "
                                                                alt="Token"
                                                                class="h-5 w-5 object-contain"
                                                            />
                                                            <span>
                                                                {{
                                                                    Number(
                                                                        item.token_reward ??
                                                                            0,
                                                                    ).toLocaleString(
                                                                        "id-ID",
                                                                    )
                                                                }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div class="p-4">
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <div
                                                    class="text-base font-medium"
                                                    :class="
                                                        getSectionTheme(label)
                                                            .title
                                                    "
                                                >
                                                    {{ label }}
                                                </div>

                                                <div
                                                    class="text-sm text-white/60"
                                                >
                                                    Stok : {{ stockText(item) }}
                                                </div>
                                            </div>

                                            <div
                                                class="mt-4 overflow-hidden rounded-[14px] bg-[#101521]"
                                            >
                                                <template
                                                    v-if="
                                                        resolvePrizeImagePath(
                                                            item.image,
                                                        )
                                                    "
                                                >
                                                    <img
                                                        :src="
                                                            resolvePrizeImagePath(
                                                                item.image,
                                                            )
                                                        "
                                                        :alt="item.name"
                                                        class="h-[210px] w-full object-cover sm:h-[230px]"
                                                    />
                                                </template>

                                                <template v-else>
                                                    <div
                                                        class="flex h-[260px] w-full items-center justify-center bg-[linear-gradient(135deg,#1b2438,#10182b)] px-5 text-center text-sm text-white/35 sm:h-[230px]"
                                                    >
                                                        Gambar hadiah belum
                                                        tersedia
                                                    </div>
                                                </template>
                                            </div>

                                            <div class="mt-4">
                                                <h3
                                                    class="line-clamp-2 text-lg font-medium leading-8 text-white"
                                                >
                                                    {{ item.name }}
                                                </h3>
                                            </div>

                                            <div
                                                class="mt-4 grid grid-cols-2 gap-4"
                                            >
                                                <div>
                                                    <div
                                                        class="text-sm text-white/55"
                                                    >
                                                        Harga Jual
                                                    </div>
                                                    <div
                                                        class="mt-2 inline-flex items-center gap-2 text-base font-medium text-white"
                                                    >
                                                        <img
                                                            :src="catKoinImage"
                                                            alt="Cat Koin"
                                                            class="h-5 w-5 object-contain"
                                                        />
                                                        <span>{{
                                                            Number(
                                                                item.sellback_price ??
                                                                    0,
                                                            ).toLocaleString(
                                                                "id-ID",
                                                            )
                                                        }}</span>
                                                    </div>
                                                </div>

                                                <div class="text-right">
                                                    <div
                                                        class="text-sm text-white/55"
                                                    >
                                                        Hadiah tiket
                                                    </div>
                                                    <div
                                                        class="mt-2 inline-flex items-center gap-2 text-base font-medium text-white"
                                                    >
                                                        <img
                                                            :src="tokenImage"
                                                            alt="Token"
                                                            class="h-5 w-5 object-contain"
                                                        />
                                                        <span>{{
                                                            Number(
                                                                item.token_reward ??
                                                                    0,
                                                            ).toLocaleString(
                                                                "id-ID",
                                                            )
                                                        }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </article>
                            </div>
                        </div>
                    </template>
                </section>
            </main>
        </div>

        <div
            v-if="hasPrizePagination"
            class="pt-2 flex flex-wrap items-center justify-center gap-2"
        >
            <template
                v-for="(link, index) in prizePaginationLinks"
                :key="index"
            >
                <span
                    v-if="!link.url"
                    class="inline-flex h-10 min-w-10 cursor-not-allowed items-center justify-center rounded-xl bg-white/[0.03] px-3 text-sm font-medium text-white/30"
                    v-html="link.label"
                />

                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl px-3 text-sm font-medium transition"
                    :class="
                        link.active
                            ? 'bg-[#F66455] text-white'
                            : 'bg-white/[0.05] text-white/70 hover:bg-white/[0.09] hover:text-white'
                    "
                    v-html="link.label"
                />
            </template>
        </div>

        <Teleport to="body">
            <div
                v-if="isPrizeModalOpen"
                class="fixed inset-0 z-[100] flex items-center justify-center px-4 py-8"
            >
                <div
                    class="absolute inset-0 bg-black/70 backdrop-blur-[2px]"
                    @click="closePrizeModal"
                />

                <div
                    class="relative z-10 flex w-full max-w-[380px] flex-col items-center sm:max-w-[420px]"
                >
                    <div
                        class="relative w-full overflow-visible rounded-xl bg-[#101521] px-5 pb-8 pt-5 sm:px-6 sm:py-9"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 overflow-hidden rounded-xl"
                        >
                            <div
                                class="absolute left-1/2 top-[44%] h-[280px] w-[280px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(circle,rgba(255,255,255,0.20)_0%,rgba(255,255,255,0.08)_24%,rgba(255,255,255,0.03)_42%,rgba(255,255,255,0)_68%)]"
                            />
                            <div
                                class="absolute left-1/2 top-[44%] h-[360px] w-[360px] -translate-x-1/2 -translate-y-1/2 bg-[repeating-conic-gradient(from_0deg,rgba(255,255,255,0.16)_0deg,rgba(255,255,255,0.16)_2deg,transparent_2deg,transparent_20deg)] opacity-30"
                            />
                            <div
                                class="absolute inset-0 bg-[linear-gradient(180deg,rgba(7,19,39,0.05),rgba(7,19,39,0.24))]"
                            />
                        </div>

                        <div class="relative z-10 flex flex-col items-center">
                            <div
                                :class="[
                                    'absolute left-[-24px] top-0 inline-flex min-w-[86px] items-center justify-center rounded-r-xl px-4 py-2 text-center text-base font-medium',
                                    prizeRibbonClass,
                                ]"
                            >
                                {{ prizeTierLabel }}
                            </div>

                            <div
                                class="mt-2 flex min-h-[300px] w-full items-center justify-center sm:min-h-[340px]"
                            >
                                <template v-if="activePrizeImage">
                                    <img
                                        :src="activePrizeImage"
                                        :alt="
                                            activePrize?.name || 'Prize image'
                                        "
                                        class="max-h-[200px] rounded-xl w-auto max-w-full object-contain"
                                    />
                                </template>

                                <template v-else>
                                    <div
                                        class="flex h-[250px] w-full max-w-[260px] items-center justify-center rounded-[20px] bg-[linear-gradient(135deg,#12203d,#0a1428)] px-6 text-center text-sm text-white/45"
                                    >
                                        Gambar hadiah belum tersedia
                                    </div>
                                </template>
                            </div>

                            <div class="mt-3 max-w-[290px] text-center">
                                <h3
                                    class="text-[16px] font-medium leading-7 text-white sm:text-lg"
                                >
                                    {{ activePrize?.name || "-" }}
                                </h3>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-6 inline-flex h-[58px] w-[58px] items-center justify-center rounded-full bg-[#FB6758] text-white transition hover:scale-[1.03] hover:brightness-105"
                        @click="closePrizeModal"
                    >
                        <i class="bi bi-x text-3xl leading-none" />
                    </button>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="isHowToPlayModalOpen"
                class="fixed inset-0 z-[110] flex items-center justify-center px-4 py-8"
            >
                <div
                    class="absolute inset-0 bg-black/70 backdrop-blur-[2px]"
                    @click="closeHowToPlayModal"
                />

                <div
                    class="relative z-10 flex w-full max-w-[560px] flex-col items-center"
                >
                    <div class="w-full overflow-hidden rounded-xl bg-[#101521]">
                        <div class="border-b border-white/8 px-6 py-4">
                            <h3
                                class="text-lg font-medium text-white sm:text-xl"
                            >
                                Cara main Catkuji
                            </h3>
                        </div>

                        <div class="space-y-4 px-6 py-5">
                            <div
                                v-for="(step, i) in steps"
                                :key="i"
                                class="rounded-xl bg-white/5 px-5 py-4 text-[15px] leading-7 text-white/90"
                            >
                                <div class="flex gap-3">
                                    <div class="w-5 shrink-0 text-white">
                                        {{ i + 1 }}.
                                    </div>
                                    <div class="flex-1 text-base">
                                        {{ step }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-6 inline-flex h-[58px] w-[58px] items-center justify-center rounded-full bg-[#FB6758] text-white transition hover:scale-[1.03] hover:brightness-105"
                        @click="closeHowToPlayModal"
                    >
                        <i class="bi bi-x text-[24px] leading-none" />
                    </button>
                </div>
            </div>
        </Teleport>

        <DrawResultModal
            :show="isDrawResultModalOpen"
            :result="drawResult"
            @close="closeDrawResultModal"
            @go-inventory="goToInventory"
            @draw-again="drawAgainFromModal"
            @confirm-draw="confirmPendingDraw"
        />

        <NpsFeedbackModal
            :show="isNpsModalOpen"
            :event-id="event.id"
            :page-url="currentPageUrl"
            @close="closeNpsModal"
            @skipped="skipNpsModal"
            @play-again="continuePlayingFromNps"
        />

        <ShareModal
            :show="isShareModalOpen"
            :url="currentPageUrl"
            :title="event.name"
            @close="closeShareModal"
        />

        <AppFooter />
    </div>
</template>
