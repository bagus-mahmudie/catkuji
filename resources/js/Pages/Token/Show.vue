<script setup>
import { computed, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";
import axios from "axios";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import tokenImage from "@/assets/token-silver.png";
import { getPrizeImage } from "@/Utils/prizeImages";
import ShareModal from "@/Components/Share/ShareModal.vue";

const props = defineProps({
    tokenBalance: {
        type: Number,
        default: 0,
    },
    item: {
        type: Object,
        required: true,
    },
});

const isRedeeming = ref(false);
const isRedeemConfirmModalOpen = ref(false);
const isRedeemSuccessModalOpen = ref(false);
const redeemSuccessData = ref(null);
const showRedeemPrize = ref(false);
const shouldRefreshTokenAfterRedeem = ref(false);

const openRedeemConfirmModal = () => {
    if (!canRedeem.value || isRedeeming.value) return;

    isRedeemConfirmModalOpen.value = true;
};

const refreshTokenData = () => {
    router.reload({
        preserveScroll: true,
        preserveState: true,
    });
};

const closeRedeemConfirmModal = () => {
    if (isRedeeming.value) return;

    isRedeemConfirmModalOpen.value = false;
};

const closeRedeemSuccessModal = () => {
    isRedeemSuccessModalOpen.value = false;
    showRedeemPrize.value = false;

    if (shouldRefreshTokenAfterRedeem.value) {
        shouldRefreshTokenAfterRedeem.value = false;
        refreshTokenData();
    }
};

const goToInventory = () => {
    isRedeemSuccessModalOpen.value = false;
    showRedeemPrize.value = false;
    shouldRefreshTokenAfterRedeem.value = false;
    router.visit(route("inventory.index"));
};

const isHowToRedeemModalOpen = ref(false);

const openHowToRedeemModal = () => {
    isHowToRedeemModalOpen.value = true;
};

const closeHowToRedeemModal = () => {
    isHowToRedeemModalOpen.value = false;
};

const redeemSteps = [
    "Pilih hadiah yang kamu inginkan",
    "Klik Redeem, lalu konfirmasi",
    "Redeem berhasil",
    "Cek hadiah di inventori kamu",
];

const imageSrc = computed(() => {
    return getPrizeImage(props.item?.image);
});

const redeemSuccessImageSrc = computed(() => {
    return getPrizeImage(redeemSuccessData.value?.image ?? props.item?.image);
});

const redeemSunburstStyle = {
    backgroundImage: `
        repeating-conic-gradient(
            from 0deg,
            rgba(255,255,255,0.14) 0deg,
            rgba(255,255,255,0.14) 2deg,
            transparent 2deg,
            transparent 18deg
        )
    `,
};

const formattedTokenPrice = computed(() =>
    Number(props.item?.token_price ?? 0).toLocaleString("id-ID"),
);

const formattedTokenBalance = computed(() =>
    Number(props.tokenBalance ?? 0).toLocaleString("id-ID"),
);

const hasEnoughToken = computed(() => {
    return (
        Number(props.tokenBalance ?? 0) >= Number(props.item?.token_price ?? 0)
    );
});

const isOutOfStock = computed(() => {
    return Number(props.item?.stock_left ?? 0) <= 0;
});

const canRedeem = computed(() => {
    return hasEnoughToken.value && !isOutOfStock.value;
});

const goBack = () => {
    router.visit(route("token.index"));
};

const redeemNow = async () => {
    if (!canRedeem.value || isRedeeming.value) return;

    try {
        isRedeeming.value = true;

        const response = await axios.post(
            route("token.redeem", props.item.slug),
        );

        isRedeemConfirmModalOpen.value = false;

        redeemSuccessData.value = {
            message:
                response.data?.message ??
                "Redeem token berhasil. Item sudah masuk ke inventory.",
            item_name:
                response.data?.data?.inventory?.name ??
                props.item?.name ??
                "Hadiah",
            image:
                response.data?.data?.inventory?.image ??
                props.item?.image ??
                null,
            token_cost:
                response.data?.data?.redeem_transaction?.token_cost ??
                props.item?.token_price ??
                0,
            inventory_id: response.data?.data?.inventory?.id ?? null,
        };

        showRedeemPrize.value = false;
        shouldRefreshTokenAfterRedeem.value = true;
        isRedeemSuccessModalOpen.value = true;

        window.setTimeout(() => {
            showRedeemPrize.value = true;
        }, 80);
    } catch (error) {
        alert(
            error?.response?.data?.message ?? "Gagal melakukan redeem token.",
        );
    } finally {
        isRedeeming.value = false;
    }
};

const formattedRedeemedTokenCost = computed(() =>
    Number(redeemSuccessData.value?.token_cost ?? 0).toLocaleString("id-ID"),
);

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
    <Head :title="item.name" />

    <div class="min-h-screen bg-[#0C0F19] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-8 sm:px-6 lg:px-8">
            <AppNavbar />

            <main class="mt-8">
                <button
                    type="button"
                    class="mb-5 inline-flex items-center gap-2 text-base font-medium bg-white/5 px-4 py-3 rounded-xl text-white/60 transition hover:text-white"
                    @click="goBack"
                >
                    <i class="bi bi-arrow-left"></i>
                    <span>Kembali</span>
                </button>

                <section
                    class="grid gap-5 rounded-xl lg:grid-cols-[1fr_1.15fr]"
                >
                    <!-- Image -->
                    <div
                        class="relative min-h-[360px] overflow-hidden rounded-xl bg-[#101521] sm:min-h-[460px]"
                    >
                        <template v-if="imageSrc">
                            <img
                                :src="imageSrc"
                                :alt="item.name"
                                class="pointer-events-none absolute inset-0 z-0 h-full w-full scale-125 object-cover opacity-45 blur-sm"
                                @error="
                                    $event.target.src =
                                        '/images/placeholder-prize.png'
                                "
                            />
                        </template>

                        <div class="absolute inset-0 z-0 bg-[#0D1322]/40" />

                        <div
                            class="relative z-10 flex h-full min-h-[360px] items-center justify-center p-5 sm:min-h-[460px]"
                        >
                            <img
                                v-if="imageSrc"
                                :src="imageSrc"
                                :alt="item.name"
                                class="max-h-[360px] max-w-full rounded-xl object-contain sm:max-h-[420px]"
                                @error="
                                    $event.target.src =
                                        '/images/placeholder-prize.png'
                                "
                            />

                            <div
                                v-else
                                class="flex h-[320px] w-full items-center justify-center rounded-xl bg-white/[0.04] text-white/40"
                            >
                                Gambar belum tersedia
                            </div>
                        </div>
                    </div>

                    <!-- Detail -->
                    <div class="rounded-xl bg-[#101521] p-5 sm:p-6 lg:p-7">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-medium text-[#FF6B5F]">
                                    <span v-if="isOutOfStock"
                                        >Sisa stok habis</span
                                    >
                                    <span v-else>
                                        Sisa stok :
                                        {{
                                            Number(
                                                item.stock_left ?? 0,
                                            ).toLocaleString("id-ID")
                                        }}
                                    </span>
                                </div>

                                <h1
                                    class="mt-3 max-w-[620px] text-3xl font-medium leading-tight text-white sm:text-4xl"
                                >
                                    {{ item.name }}
                                </h1>
                            </div>

                            <button
                                type="button"
                                class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white/70 transition hover:bg-white/5 hover:text-white"
                                @click="openShareModal"
                            >
                                <i class="bi bi-share text-lg"></i>
                            </button>
                        </div>

                        <p
                            class="mt-4 max-w-[620px] whitespace-pre-line text-sm leading-6 text-white/60 sm:text-base"
                        >
                            {{
                                item.description ||
                                "Item ini bisa diredeem menggunakan token yang kamu kumpulkan dari reward draw."
                            }}
                        </p>

                        <div class="mt-7 flex items-center gap-3">
                            <img
                                :src="tokenImage"
                                alt="Token"
                                class="h-7 w-7 object-contain"
                            />

                            <div class="text-2xl font-semibold text-white">
                                {{ formattedTokenPrice }}
                            </div>
                        </div>

                        <div class="mt-6 border-t border-white/10 pt-5">
                            <button
                                type="button"
                                class="flex h-[50px] w-full items-center justify-center rounded-xl bg-[#F66455] px-6 text-base font-medium text-white transition hover:bg-[#ff7d70] disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!canRedeem || isRedeeming"
                                @click="openRedeemConfirmModal"
                            >
                                <span v-if="isRedeeming">Memproses...</span>
                                <span v-else-if="isOutOfStock">Stok habis</span>
                                <span v-else-if="!hasEnoughToken"
                                    >Token tidak cukup</span
                                >
                                <span v-else>Redeem sekarang</span>
                            </button>

                            <button
                                type="button"
                                class="mt-4 flex h-[50px] w-full items-center justify-center gap-3 rounded-xl bg-white/[0.05] px-6 text-base font-medium text-white transition hover:bg-white/[0.08]"
                                @click="openHowToRedeemModal"
                            >
                                <span>Cara redeem</span>
                                <i class="bi bi-arrow-right text-base"></i>
                            </button>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <Teleport to="body">
            <div
                v-if="isHowToRedeemModalOpen"
                class="fixed inset-0 z-[110] flex items-center justify-center px-4 py-8"
            >
                <div
                    class="absolute inset-0 bg-black/70 backdrop-blur-[2px]"
                    @click="closeHowToRedeemModal"
                />

                <div
                    class="relative z-10 flex w-full max-w-[560px] flex-col items-center"
                >
                    <div class="w-full overflow-hidden rounded-xl bg-[#101521]">
                        <div class="border-b border-white/8 px-6 py-4">
                            <h3
                                class="text-lg font-medium text-white sm:text-xl"
                            >
                                Cara redeem token
                            </h3>
                        </div>

                        <div class="space-y-4 px-6 py-5">
                            <div
                                v-for="(step, i) in redeemSteps"
                                :key="i"
                                class="rounded-xl bg-white/5 px-5 py-4 text-base text-white/90"
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
                        @click="closeHowToRedeemModal"
                    >
                        <i class="bi bi-x text-[24px] leading-none" />
                    </button>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="isRedeemSuccessModalOpen"
                class="fixed inset-0 z-[120] overflow-y-auto bg-black/75 px-4 py-8 backdrop-blur-[3px]"
                @click="closeRedeemSuccessModal"
            >
                <div class="flex min-h-full items-center justify-center">
                    <div
                        class="relative z-10 flex w-full flex-col items-center"
                    >
                        <div
                            class="relative w-full max-w-[520px] overflow-hidden rounded-xl bg-[#101521] px-3 py-3 shadow-[0_24px_80px_rgba(0,0,0,0.55)] sm:px-5 sm:py-5"
                            @click.stop
                        >
                            <button
                                type="button"
                                class="absolute right-6 top-6 z-20 inline-flex h-[42px] w-[42px] items-center justify-center rounded-full bg-white/10 text-white transition hover:scale-[1.03] hover:bg-white/15"
                                @click="closeRedeemSuccessModal"
                            >
                                <i class="bi bi-x-lg" />
                            </button>

                            <div
                                class="pointer-events-none absolute inset-0 overflow-hidden rounded-xl"
                            >
                                <div
                                    class="absolute inset-0 bg-[linear-gradient(180deg,rgba(7,19,39,0.02),rgba(7,19,39,0.24))]"
                                />
                            </div>

                            <div class="relative z-10">
                                <div
                                    class="relative flex w-full items-center justify-center overflow-hidden rounded-xl px-3 py-8 min-h-[380px] sm:min-h-[460px]"
                                >
                                    <div
                                        class="pointer-events-none absolute left-1/2 top-1/2 z-0 h-[150%] w-[150%] -translate-x-1/2 -translate-y-1/2 opacity-0"
                                        :class="{
                                            'animate-redeem-sunburst-in-custom':
                                                showRedeemPrize,
                                        }"
                                        :style="redeemSunburstStyle"
                                    />

                                    <div
                                        class="relative z-[2] flex w-full flex-col items-center"
                                    >
                                        <div
                                            class="pointer-events-none absolute left-1/2 top-[38%] z-0 h-[230px] w-[230px] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-0 blur-[14px]"
                                            style="
                                                background: radial-gradient(
                                                    circle,
                                                    rgba(255, 255, 255, 0.24) 0%,
                                                    rgba(255, 214, 107, 0.14)
                                                        20%,
                                                    rgba(255, 162, 65, 0.08) 38%,
                                                    rgba(255, 255, 255, 0) 72%
                                                );
                                            "
                                            :class="{
                                                'animate-redeem-glow-in-custom':
                                                    showRedeemPrize,
                                            }"
                                        />

                                        <div
                                            class="relative z-[2] opacity-0 [transform-style:preserve-3d] [backface-visibility:hidden] [filter:blur(10px)]"
                                            :class="{
                                                'animate-redeem-item-in-custom':
                                                    showRedeemPrize,
                                            }"
                                        >
                                            <div
                                                class="flex h-[230px] w-[230px] items-center justify-center overflow-hidden rounded-xl bg-white/[0.03] sm:h-[260px] sm:w-[260px]"
                                            >
                                                <img
                                                    v-if="redeemSuccessImageSrc"
                                                    :src="redeemSuccessImageSrc"
                                                    :alt="
                                                        redeemSuccessData?.item_name ??
                                                        'Hadiah redeem'
                                                    "
                                                    class="h-full w-full rounded-xl object-cover"
                                                    @error="
                                                        $event.target.src =
                                                            '/images/placeholder-prize.png'
                                                    "
                                                />

                                                <div
                                                    v-else
                                                    class="flex h-full w-full items-center justify-center px-4 text-center text-sm text-white/45"
                                                >
                                                    Gambar belum tersedia
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-5 translate-y-[10px] text-center opacity-0"
                                            :class="{
                                                'animate-redeem-caption-in-custom':
                                                    showRedeemPrize,
                                            }"
                                        >
                                            <div
                                                class="mx-auto mb-3 inline-flex items-center gap-2 rounded-full bg-[#FB6758]/15 px-4 py-2 text-sm font-medium text-[#FF9A90]"
                                            >
                                                <i class="bi bi-stars" />
                                                <span>Redeem berhasil</span>
                                            </div>

                                            <h2
                                                class="mx-auto max-w-[360px] text-2xl font-medium leading-tight text-white sm:text-3xl"
                                            >
                                                {{
                                                    redeemSuccessData?.item_name ??
                                                    "Hadiah"
                                                }}
                                            </h2>

                                            <p
                                                class="mx-auto mt-3 max-w-[360px] text-sm leading-6 text-white/65 sm:text-base"
                                            >
                                                Hadiah berhasil ditambahkan ke
                                                inventory kamu.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="mt-6 inline-flex h-[50px] w-full max-w-[520px] items-center justify-center rounded-xl bg-[#FB6758] px-7 text-base font-medium text-white transition hover:scale-[1.03] hover:brightness-105"
                            @click.stop="goToInventory"
                        >
                            Lihat hadiah di inventory
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="isRedeemConfirmModalOpen"
                class="fixed inset-0 z-[115] flex items-center justify-center px-4 py-8"
            >
                <div
                    class="absolute inset-0 bg-black/75 backdrop-blur-[3px]"
                    @click="closeRedeemConfirmModal"
                />

                <div
                    class="relative z-10 w-full max-w-[450px] overflow-hidden rounded-2xl bg-[#101521] px-3 py-3 text-center shadow-[0_24px_80px_rgba(0,0,0,0.55)] sm:px-5 sm:py-5"
                >
                    <div
                        class="mx-auto flex h-20 w-20 items-center justify-center rounded-xl bg-[#2A1F2A]"
                    >
                        <div
                            class="flex h-16 w-16 items-center justify-center rounded-xl bg-[#FF6D5A] text-white"
                        >
                            <i class="bi bi-question-circle text-3xl" />
                        </div>
                    </div>

                    <h2 class="mt-4 text-2xl font-medium text-white">
                        Redeem hadiah
                    </h2>

                    <p
                        class="mx-auto mt-3 text-sm sm:text-base leading-5 text-white/70"
                    >
                        Yakin ingin menukar
                        <span class="font-medium text-white">
                            {{ formattedTokenPrice }} Token
                        </span>
                        untuk item ini ?
                    </p>

                    <div class="mt-8 space-y-3">
                        <button
                            type="button"
                            class="inline-flex h-[50px] w-full items-center justify-center rounded-xl bg-[#FB6758] px-6 text-base font-medium text-white transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isRedeeming"
                            @click="redeemNow"
                        >
                            <span v-if="isRedeeming">Memproses...</span>
                            <span v-else>Ya, saya yakin</span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-[50px] w-full items-center justify-center rounded-xl bg-white/[0.05] px-6 text-base font-medium text-white transition hover:bg-white/[0.08] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isRedeeming"
                            @click="closeRedeemConfirmModal"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <ShareModal
            :show="isShareModalOpen"
            :url="currentPageUrl"
            :title="item.name"
            @close="closeShareModal"
        />

        <AppFooter />
    </div>
</template>

<style scoped>
.animate-redeem-item-in-custom {
    animation: redeem-item-in 760ms cubic-bezier(0.2, 0.88, 0.22, 1) forwards;
}

.animate-redeem-glow-in-custom {
    animation: redeem-glow-in 760ms cubic-bezier(0.2, 0.88, 0.22, 1) forwards;
}

.animate-redeem-caption-in-custom {
    animation: redeem-caption-in 560ms ease-out forwards;
}

.animate-redeem-sunburst-in-custom {
    animation:
        redeem-sunburst-in 900ms ease-out forwards,
        redeem-sunburst-spin-pulse 10s linear infinite;
}

@keyframes redeem-item-in {
    0% {
        opacity: 0;
        transform: translateY(28px) scale(0.72) rotateX(18deg);
        filter: blur(10px);
    }

    55% {
        opacity: 1;
        transform: translateY(-8px) scale(1.06) rotateX(0deg);
        filter: blur(0);
    }

    100% {
        opacity: 1;
        transform: translateY(0) scale(1) rotateX(0deg);
        filter: blur(0);
    }
}

@keyframes redeem-glow-in {
    0% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.25);
    }

    55% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1.08);
    }

    100% {
        opacity: 0.78;
        transform: translate(-50%, -50%) scale(1);
    }
}

@keyframes redeem-caption-in {
    0% {
        opacity: 0;
        transform: translateY(14px);
    }

    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes redeem-sunburst-in {
    0% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.7) rotate(0deg);
    }

    100% {
        opacity: 0.18;
        transform: translate(-50%, -50%) scale(1) rotate(0deg);
    }
}

@keyframes redeem-sunburst-spin-pulse {
    0% {
        transform: translate(-50%, -50%) scale(1) rotate(0deg);
    }

    50% {
        transform: translate(-50%, -50%) scale(1.04) rotate(180deg);
    }

    100% {
        transform: translate(-50%, -50%) scale(1) rotate(360deg);
    }
}
</style>
