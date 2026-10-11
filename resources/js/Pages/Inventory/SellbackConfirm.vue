<script setup>
import { computed, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import catKoinImage from "@/assets/cat-koin.png";
import { resolvePrizeImagePath } from "@/Utils/resolvePrizeImage";

const page = usePage();

const flashError = computed(() => page.props.flash?.error ?? null);

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    successResult: {
        type: Object,
        default: null,
    },
    tokenBalance: { type: Number, default: 0 },
    catKoinBalance: { type: Number, default: 0 },
    walletBalance: { type: Number, default: 0 },
});

const openTopupPage = () => {
    window.location.href = route("wallet.index");
};

const localItems = ref(
    (props.items || []).map((item) => ({
        ...item,
        quantity: Number(item.quantity || 1),
        max_quantity: Number(item.max_quantity || item.quantity || 1),
    })),
);

const formatNumber = (value) => Number(value || 0).toLocaleString("id-ID");

const totalMarketPrice = computed(() =>
    localItems.value.reduce((sum, item) => {
        return (
            sum +
            Number(item.market_price || 0) * Number(item.quantity || 0) * 1000
        );
    }, 0),
);

const totalSellback = computed(() =>
    localItems.value.reduce((sum, item) => {
        return (
            sum + Number(item.sellback_price || 0) * Number(item.quantity || 0)
        );
    }, 0),
);

const canSubmit = computed(() =>
    localItems.value.some((item) => Number(item.quantity || 0) > 0),
);

const decreaseQty = (index) => {
    if (localItems.value[index].quantity > 1) {
        localItems.value[index].quantity -= 1;
    }
};

const increaseQty = (index) => {
    if (
        localItems.value[index].quantity < localItems.value[index].max_quantity
    ) {
        localItems.value[index].quantity += 1;
    }
};

const isSubmitting = ref(false);

const submitSellback = () => {
    if (!canSubmit.value || isSubmitting.value) return;

    isSubmitting.value = true;

    router.post(
        route("inventory.sellback.checkout"),
        {
            items: localItems.value
                .filter((item) => Number(item.quantity) > 0)
                .map((item) => ({
                    gacha_prize_id: item.gacha_prize_id,
                    quantity: item.quantity,
                })),
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const rarityLabel = (item) => {
    const raw = String(item.rarity_label || item.rarity_code || "")
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

const rarityClass = (item) => {
    const label = rarityLabel(item);

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
</script>

<template>
    <Head title="Konfirmasi Jual Kembali" />

    <div class="min-h-screen bg-[#0C0F19] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar @topup-click="openTopupPage" />

            <div
                v-if="flashError"
                class="mt-6 rounded-xl border border-[#FF6D5A]/30 bg-[#2A1414] px-5 py-4 text-sm text-[#FFB4AA]"
            >
                {{ flashError }}
            </div>

            <section class="mt-8 pb-10">
                <div
                    class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_320px] xl:grid-cols-[minmax(0,1fr)_340px]"
                >
                    <!-- LEFT -->
                    <div class="space-y-4">
                        <article
                            v-for="(item, index) in localItems"
                            :key="`${item.gacha_prize_id}-${index}`"
                            class="rounded-xl bg-[#101521] px-3 py-3 sm:px-4 sm:py-4"
                        >
                            <div class="flex items-center gap-4 sm:gap-5">
                                <!-- image -->
                                <div
                                    class="h-full sm:w-[130px] w-[145px] shrink-0 overflow-hidden rounded-[16px]"
                                >
                                    <img
                                        v-if="resolvePrizeImagePath(item.image)"
                                        :src="resolvePrizeImagePath(item.image)"
                                        :alt="item.name"
                                        class="h-full w-full object-cover"
                                    />
                                </div>

                                <!-- content -->
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <div
                                                :class="[
                                                    'text-sm font-medium sm:text-base',
                                                    rarityClass(item),
                                                ]"
                                            >
                                                {{ rarityLabel(item) }}
                                            </div>

                                            <h2
                                                class="mt-1 line-clamp-2 text-base leading-tight text-white sm:text-lg sm:leading-6"
                                            >
                                                {{ item.name }}
                                            </h2>
                                        </div>

                                        <div
                                            class="shrink-0 text-sm font-medium text-[#FF5A5A] sm:text-base"
                                        >
                                            {{ item.max_quantity }}X
                                        </div>
                                    </div>

                                    <div
                                        class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                                    >
                                        <div>
                                            <div
                                                class="text-sm text-white/55 sm:text-base"
                                            >
                                                Harga Jual
                                            </div>

                                            <div
                                                class="mt-2 flex items-center gap-3 text-base font-medium leading-none text-white sm:text-lg"
                                            >
                                                <span
                                                    >{{ item.quantity }}X</span
                                                >

                                                <span
                                                    class="flex items-center gap-1 text-white"
                                                >
                                                    <img
                                                        :src="catKoinImage"
                                                        alt="Cat Koin"
                                                        class="h-4 w-4 sm:h-5 sm:w-5 object-contain"
                                                    />
                                                    {{
                                                        formatNumber(
                                                            item.sellback_price,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- qty control -->
                                        <div
                                            class="inline-flex h-auto w-fit items-center overflow-hidden rounded-xl bg-[#1A2132] sm:h-auto"
                                        >
                                            <button
                                                type="button"
                                                class="flex h-full w-[46px] items-center justify-center text-[24px] text-white/80 transition hover:bg-white/5 disabled:opacity-30 sm:w-[40px]"
                                                :disabled="item.quantity <= 1"
                                                @click="decreaseQty(index)"
                                            >
                                                -
                                            </button>

                                            <div
                                                class="h-3 w-px bg-white/10 sm:h-4"
                                            />

                                            <div
                                                class="flex h-full items-center justify-center px-4 sm:px-1 text-base font-medium text-white sm:min-w-[40px] sm:text-base"
                                            >
                                                {{ item.quantity }}
                                            </div>

                                            <div
                                                class="h-3 w-px bg-white/10 sm:h-4"
                                            />

                                            <button
                                                type="button"
                                                class="flex h-full w-[46px] items-center justify-center text-[24px] text-white/80 transition hover:bg-white/5 disabled:opacity-30 sm:w-[40px]"
                                                :disabled="
                                                    item.quantity >=
                                                    item.max_quantity
                                                "
                                                @click="increaseQty(index)"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <!-- RIGHT -->
                    <aside class="lg:sticky lg:top-6 lg:self-start">
                        <div class="rounded-[24px] bg-[#101521] p-5 sm:p-6">
                            <h3 class="text-[20px] font-medium text-white">
                                Konfirmasi Penjualan
                            </h3>

                            <div class="mt-6 border-t border-white/10 pt-6">
                                <div
                                    class="flex items-center justify-between text-[16px] text-white/70"
                                >
                                    <span>Total Harga</span>
                                    <span class="text-white">
                                        Rp {{ formatNumber(totalMarketPrice) }}
                                    </span>
                                </div>

                                <div
                                    class="mt-5 flex items-center justify-between text-base font-medium text-white/70"
                                >
                                    <span>Total Penjualan</span>
                                    <span
                                        class="flex items-center gap-1 text-white"
                                    >
                                        <img
                                            :src="catKoinImage"
                                            alt="Cat Koin"
                                            class="h-5 w-5 object-contain"
                                        />
                                        {{ formatNumber(totalSellback) }}
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="mt-8 flex w-full items-center justify-center rounded-xl bg-[#FF6D5A] px-6 py-3 text-base font-medium text-white transition hover:bg-[#ff7d6d] disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!canSubmit || isSubmitting"
                                @click="submitSellback"
                            >
                                {{
                                    isSubmitting
                                        ? "Memproses..."
                                        : "Jual Sekarang"
                                }}
                                <span
                                    v-if="!isSubmitting"
                                    class="ml-3 text-[22px]"
                                >
                                </span>
                            </button>
                        </div>
                    </aside>
                </div>
            </section>
        </div>

        <AppFooter />

        <div
            v-if="successResult"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-[560px] rounded-xl bg-[#101521] px-8 py-10 text-center"
            >
                <div
                    class="mx-auto flex h-[110px] w-[110px] items-center justify-center rounded-[28px] bg-[#2A1F2A]"
                >
                    <div
                        class="flex h-[84px] w-[84px] items-center justify-center rounded-[24px] bg-[#FF6D5A] text-white"
                    >
                        <i class="bi bi-percent text-[34px]" />
                    </div>
                </div>

                <h3 class="mt-8 text-2xl font-medium text-white">
                    Yay, penjualan berhasil
                </h3>

                <p
                    class="mx-auto mt-6 max-w-[320px] text-sm sm:text-base leading-5 text-white/70"
                >
                    {{
                        Number(successResult.total_amount || 0).toLocaleString(
                            "id-ID",
                        )
                    }}
                    Cat Kuji ditambahin ke akun kamu. Yuk main Catkuji lagi
                </p>

                <Link
                    :href="route('inventory.index')"
                    class="mt-8 inline-flex w-full items-center justify-center rounded-xl bg-[#FF6D5A] px-6 py-4 text-sm sm:text-lg font-medium text-white transition hover:bg-[#ff7d6d]"
                >
                    Let’s go main Catkuji
                </Link>
            </div>
        </div>
    </div>
</template>
