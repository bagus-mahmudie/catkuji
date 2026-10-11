<script setup>
import { computed, ref, watch, onMounted } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import catKoinImage from "@/assets/cat-koin.png";
import giftImage from "@/assets/gift/gift.png";
import truckImage from "@/assets/icons/truck.png";
import gifticonImage from "@/assets/icons/gift.png";
import { resolvePrizeImagePath } from "@/Utils/resolvePrizeImage";
import CsatFeedbackModal from "@/Components/Csat/CsatFeedbackModal.vue";
import axios from "axios";

onMounted(async () => {
    const shouldShowCsat = sessionStorage.getItem("show_csat_after_shipment");
    const shipmentOrderId = sessionStorage.getItem("csat_shipment_order_id");

    if (shouldShowCsat !== "1") return;

    sessionStorage.removeItem("show_csat_after_shipment");
    sessionStorage.removeItem("csat_shipment_order_id");

    try {
        const response = await axios.get(route("csat-feedback.eligibility"));

        if (response.data?.eligible) {
            setTimeout(() => {
                openCsatModal(shipmentOrderId);
            }, 400);
        }
    } catch (error) {
        console.error("Gagal cek CSAT:", error);
    }
});

const props = defineProps({
    inventories: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({
            all: 0,
            gold: 0,
            silver: 0,
            bronze: 0,
        }),
    },
    claimSummary: {
        type: Object,
        default: () => ({
            physical: 0,
            digital: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            rarity: "",
        }),
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
    unopenedPackCount: {
        type: Number,
        default: 0,
    },
});

const showClaimTypeModal = ref(false);

const openClaimTypeModal = () => {
    showClaimTypeModal.value = true;
};

const closeClaimTypeModal = () => {
    showClaimTypeModal.value = false;
};

const choosePhysicalClaim = () => {
    showClaimTypeModal.value = false;
    setSelectionMode("claim");
};

const chooseDigitalClaim = () => {
    showClaimTypeModal.value = false;

    // sementara kalau backend digital claim belum jadi:
    window.alert("Klaim hadiah digital belum tersedia.");

    // kalau nanti sudah ada:
    // router.get(route("transactions.index", { service: "digital_claim" }));
};

const localUnopenedPackCount = ref(props.unopenedPackCount || 0);
const isFiltering = ref(false);

const selectionMode = ref(null); // null | 'sellback' | 'claim'
const selectedPrizeIds = ref([]);

const isSellbackMode = computed(() => selectionMode.value === "sellback");
const isClaimMode = computed(() => selectionMode.value === "claim");

watch(
    () => props.unopenedPackCount,
    (value) => {
        localUnopenedPackCount.value = value || 0;
    },
);

watch(
    () => props.inventories.data,
    () => {
        selectedPrizeIds.value = [];
        selectionMode.value = null;
    },
);

const openTopupPage = () => {
    window.location.href = route("wallet.index");
};

const tabs = computed(() => [
    {
        label: "Semua",
        value: "",
        total: props.summary?.all ?? 0,
    },
    {
        label: "Gold",
        value: "GOLD",
        total: props.summary?.gold ?? 0,
    },
    {
        label: "Silver",
        value: "SILVER",
        total: props.summary?.silver ?? 0,
    },
    {
        label: "Bronze",
        value: "BRONZE",
        total: props.summary?.bronze ?? 0,
    },
]);

const currentRarity = computed(() =>
    String(props.filters?.rarity ?? "").toUpperCase(),
);

const setRarity = (rarity) => {
    const normalizedTarget = String(rarity ?? "").toUpperCase();
    const normalizedCurrent = currentRarity.value;

    if (normalizedTarget === normalizedCurrent) return;

    router.get(
        route("inventory.index"),
        {
            rarity: normalizedTarget || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ["inventories", "summary", "filters"],
            onStart: () => {
                isFiltering.value = true;
            },
            onFinish: () => {
                isFiltering.value = false;
            },
        },
    );
};

const normalizeRarity = (rarity) => {
    const code = String(rarity ?? "").toUpperCase();

    if (["UR", "SSR", "GOLD"].includes(code)) return "Gold";
    if (["SR", "SILVER"].includes(code)) return "Silver";
    return "Bronze";
};

const rarityTextClass = (rarity) => {
    const value = normalizeRarity(rarity);

    if (value === "Gold") return "text-[#F5B544]";
    if (value === "Silver") return "text-[#FF6B7A]";
    return "text-[#C98A55]";
};

const isActiveTab = (value) => {
    const target = String(value ?? "").toUpperCase();
    return currentRarity.value === target;
};

const setSelectionMode = (mode) => {
    if (selectionMode.value === mode) {
        selectionMode.value = null;
        selectedPrizeIds.value = [];
        return;
    }

    selectionMode.value = mode;
    selectedPrizeIds.value = [];
};

const cancelSelectionMode = () => {
    selectionMode.value = null;
    selectedPrizeIds.value = [];
};

const isSelected = (gachaPrizeId) => {
    return selectedPrizeIds.value.includes(gachaPrizeId);
};

const isSelectableInCurrentMode = (item) => {
    if (isSellbackMode.value) {
        return item.can_sellback;
    }

    if (isClaimMode.value) {
        return item.can_claim;
    }

    return false;
};

const toggleSelection = (item) => {
    if (!isSelectableInCurrentMode(item)) return;

    const prizeId = item.gacha_prize_id;

    if (isSelected(prizeId)) {
        selectedPrizeIds.value = selectedPrizeIds.value.filter(
            (id) => id !== prizeId,
        );
        return;
    }

    selectedPrizeIds.value.push(prizeId);
};

const allSelectablePrizeIds = computed(() =>
    props.inventories.data
        .filter((item) => isSelectableInCurrentMode(item))
        .map((item) => item.gacha_prize_id),
);

const allSelected = computed(() => {
    return (
        allSelectablePrizeIds.value.length > 0 &&
        allSelectablePrizeIds.value.every((id) =>
            selectedPrizeIds.value.includes(id),
        )
    );
});

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedPrizeIds.value = [];
        return;
    }

    selectedPrizeIds.value = [...allSelectablePrizeIds.value];
};

const selectedItems = computed(() =>
    props.inventories.data
        .filter((item) => selectedPrizeIds.value.includes(item.gacha_prize_id))
        .map((item) => ({
            gacha_prize_id: item.gacha_prize_id,
            quantity: item.quantity,
        })),
);

const goToSellbackConfirm = () => {
    if (!selectedItems.value.length) return;

    router.post(
        route("inventory.sellback.preview"),
        {
            items: selectedItems.value,
        },
        {
            preserveScroll: true,
        },
    );
};

const canShipInventory = (item) => {
    return item.status === "stored" && item.can_claim === true;
};
const goToShipmentConfirm = () => {
    if (!selectedClaimItems.value.length) return;

    router.post(
        route("shipment.preview"),
        {
            items: selectedClaimItems.value,
        },
        {
            preserveScroll: true,
        },
    );
};
const selectedSellbackItems = computed(() =>
    props.inventories.data
        .filter((item) => selectedPrizeIds.value.includes(item.gacha_prize_id))
        .map((item) => ({
            gacha_prize_id: item.gacha_prize_id,
            quantity: item.quantity,
        })),
);

const selectedClaimItems = computed(() =>
    props.inventories.data
        .filter((item) => selectedPrizeIds.value.includes(item.gacha_prize_id))
        .map((item) => ({
            gacha_prize_id: item.gacha_prize_id,
            quantity: item.quantity,
        })),
);

const selectedCount = computed(() =>
    isSellbackMode.value
        ? selectedSellbackItems.value.length
        : selectedClaimItems.value.length,
);

const isShippingLocked = (item) => {
    return [
        "shipping_pending_payment",
        "shipping_requested",
        "shipped",
        "delivered",
    ].includes(item.status);
};

const isCsatModalOpen = ref(false);
const csatShipmentOrderId = ref(null);

const openCsatModal = (shipmentOrderId = null) => {
    csatShipmentOrderId.value = shipmentOrderId;
    isCsatModalOpen.value = true;
};

const closeCsatModal = () => {
    isCsatModalOpen.value = false;
    csatShipmentOrderId.value = null;
};

const skipCsatModal = () => {
    isCsatModalOpen.value = false;
    csatShipmentOrderId.value = null;
};

const continuePlayingFromCsat = () => {
    isCsatModalOpen.value = false;
    csatShipmentOrderId.value = null;
};
</script>

<template>
    <Head title="Inventory" />

    <div class="min-h-screen bg-[#0C0F19] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar @topup-click="openTopupPage" />

            <section class="mt-8">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl sm:text-4xl font-medium text-white">
                            Inventory
                        </h1>
                    </div>

                    <div class="hidden items-center gap-3 sm:flex">
                        <div class="relative">
                            <select
                                class="appearance-none rounded-xl border border-white/10 bg-[#101521] py-2 pl-4 pr-10 text-sm text-white outline-none transition"
                                :value="filters.rarity || ''"
                                @change="setRarity($event.target.value)"
                            >
                                <option value="">Semua</option>
                                <option value="GOLD">Gold</option>
                                <option value="SILVER">Silver</option>
                                <option value="BRONZE">Bronze</option>
                            </select>

                            <i
                                class="bi bi-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-white/60"
                            ></i>
                        </div>

                        <Link
                            :href="route('inventory.pack.index')"
                            class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-[#0B1425] transition hover:bg-[#111D33]"
                        >
                            <img
                                :src="giftImage"
                                alt="Pack"
                                class="h-6 w-6 object-contain"
                            />

                            <span
                                v-if="localUnopenedPackCount > 0"
                                class="absolute -right-1 -top-1 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
                            >
                                {{ localUnopenedPackCount }}
                            </span>
                        </Link>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3 sm:hidden">
                    <select
                        class="flex-1 rounded-xl border border-white/10 bg-[#101521] px-4 py-2 text-sm text-white outline-none transition"
                        :value="filters.rarity || ''"
                        @change="setRarity($event.target.value)"
                    >
                        <option value="">Semua</option>
                        <option value="GOLD">Gold</option>
                        <option value="SILVER">Silver</option>
                        <option value="BRONZE">Bronze</option>
                    </select>

                    <Link
                        :href="route('inventory.pack.index')"
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-[#0B1425] transition hover:bg-[#111D33]"
                    >
                        <img
                            :src="giftImage"
                            alt="Pack"
                            class="h-6 w-6 object-contain"
                        />

                        <span
                            v-if="localUnopenedPackCount > 0"
                            class="absolute -right-1 -top-1 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
                        >
                            {{ localUnopenedPackCount }}
                        </span>
                    </Link>
                </div>

                <div
                    class="mt-8 grid grid-cols-4 overflow-hidden rounded-xl bg-[#101521] sm:grid-cols-4"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        type="button"
                        class="relative px-3 py-3 text-left transition hover:bg-white/[0.03] sm:px-5 sm:py-4"
                        :class="{
                            'bg-white/[0.02]': isActiveTab(tab.value),
                        }"
                        @click="setRarity(tab.value)"
                    >
                        <div
                            v-if="tab !== tabs[tabs.length - 1]"
                            class="absolute right-0 top-1/2 h-8 w-px -translate-y-1/2 bg-white/10"
                        />
                        <div class="text-xs text-white/60 sm:text-base">
                            {{ tab.label }}
                        </div>
                        <div
                            class="mt-1 text-lg font-semibold text-white sm:mt-2 sm:text-[26px]"
                        >
                            {{ tab.total }}
                        </div>
                    </button>
                </div>

                <div
                    v-if="
                        (isSellbackMode || isClaimMode) &&
                        inventories.data.length
                    "
                    class="mt-8 flex items-center gap-4"
                >
                    <button
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded-md border transition"
                        :class="
                            allSelected
                                ? 'border-[#FF6D5A] bg-[#FF6D5A]/15'
                                : 'border-[#FF6D5A] bg-transparent'
                        "
                        @click="toggleSelectAll"
                    >
                        <i
                            v-if="allSelected"
                            class="bi bi-check-lg text-sm text-[#FF6D5A]"
                        />
                    </button>

                    <button
                        type="button"
                        class="text-base font-medium text-white"
                        @click="toggleSelectAll"
                    >
                        Pilih semua hadiah
                    </button>
                </div>

                <div
                    v-if="inventories.data.length"
                    class="mt-6 grid grid-cols-3 gap-2 transition-opacity duration-200 sm:gap-4 md:grid-cols-4"
                    :class="{
                        'pointer-events-none opacity-60': isFiltering,
                    }"
                >
                    <article
                        v-for="item in inventories.data"
                        :key="item.gacha_prize_id"
                        class="group overflow-hidden rounded-xl bg-[#101521] transition hover:-translate-y-1"
                    >
                        <div class="relative sm:p-3 px-1.5 py-2">
                            <div class="overflow-hidden rounded-xl bg-white">
                                <img
                                    v-if="resolvePrizeImagePath(item.image)"
                                    :src="resolvePrizeImagePath(item.image)"
                                    :alt="item.name"
                                    class="aspect-[1/1] w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex aspect-[1/1] items-center justify-center bg-[#0b1324] px-4 text-center text-sm text-white/45"
                                >
                                    Gambar hadiah belum tersedia
                                </div>
                            </div>

                            <button
                                v-if="isSellbackMode || isClaimMode"
                                type="button"
                                class="absolute left-5 top-5 flex h-6 w-6 items-center justify-center rounded-md border backdrop-blur-sm transition"
                                :class="
                                    isSelected(item.gacha_prize_id)
                                        ? 'border-[#FF8B7E] bg-[#FF8B7E]/20'
                                        : 'border-[#FF8B7E] bg-[#1A2132]/60'
                                "
                                @click="toggleSelection(item)"
                            >
                                <i
                                    v-if="isSelected(item.gacha_prize_id)"
                                    class="bi bi-check-lg text-sm text-[#FF8B7E]"
                                />
                            </button>

                            <!-- <button
                                v-else
                                type="button"
                                class="absolute right-5 top-5 inline-flex h-7 w-7 items-center justify-center rounded-full bg-black/35 text-white/90 backdrop-blur-sm"
                            >
                                <i class="bi bi-heart text-[12px]" />
                            </button> -->
                        </div>

                        <div class="sm:px-3 sm:pb-4 px-1.5 pb-2">
                            <div class="flex items-start justify-between gap-3">
                                <div
                                    class="text-xs sm:text-base font-medium"
                                    :class="rarityTextClass(item.rarity_code)"
                                >
                                    {{ normalizeRarity(item.rarity_code) }}
                                </div>

                                <div
                                    v-if="item.quantity > 1"
                                    class="text-xs sm:text-base font-medium text-[#FF5F6D]"
                                >
                                    x{{ item.quantity }}
                                </div>
                            </div>

                            <h3
                                class="sm:mt-2 mt-0 line-clamp-2 min-h-[48px] text-sm sm:text-lg font-medium leading-6 text-white"
                            >
                                {{ item.name }}
                            </h3>

                            <div
                                class="sm:mt-4 mt-1 text-xs sm:text-sm text-white/45"
                            >
                                Harga Jual
                            </div>

                            <div class="mt-1 flex items-center gap-1.5">
                                <img
                                    :src="catKoinImage"
                                    alt="Cat Koin"
                                    class="sm:h-[16px] sm:w-[16px] h-[12px] w-[12px] object-contain"
                                />
                                <span
                                    class="text-xs sm:text-lg font-semibold text-white"
                                >
                                    {{
                                        Number(
                                            item.sellback_price ?? 0,
                                        ).toLocaleString("id-ID")
                                    }}
                                </span>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-8 rounded-xl border border-dashed border-white/10 bg-white/[0.02] px-6 py-16 text-center"
                >
                    <div class="text-lg sm:text-xl font-medium text-white">
                        Inventory masih kosong
                    </div>
                    <p class="mt-2 text-sm text-white/55">
                        Lakukan draw dulu, nanti hadiah akan muncul di sini.
                    </p>

                    <div class="mt-6">
                        <Link
                            :href="route('home')"
                            class="inline-flex items-center rounded-xl bg-[#FF6D5A] px-5 py-3 text-base font-medium text-white"
                        >
                            Mulai Draw
                        </Link>
                    </div>
                </div>

                <div
                    v-if="inventories.links?.length > 3"
                    class="mt-8 flex flex-wrap items-center justify-center gap-2"
                >
                    <template
                        v-for="(link, index) in inventories.links"
                        :key="`${index}-${link.label}`"
                    >
                        <component
                            :is="link.url ? Link : 'span'"
                            :href="link.url || undefined"
                            v-html="link.label"
                            class="rounded-xl border px-4 py-2 text-sm"
                            :class="
                                link.active
                                    ? 'border-[#FF6D5A] bg-[#FF6D5A] text-white'
                                    : 'border-white/10 bg-[#101521] text-white/80'
                            "
                        />
                    </template>
                </div>

                <div
                    v-if="inventories.data.length && !selectionMode"
                    class="mt-10 grid grid-cols-1 gap-4 md:grid-cols-2"
                >
                    <button
                        type="button"
                        class="rounded-xl bg-white/5 px-6 py-4 text-base font-medium text-white transition hover:bg-[#15233d]"
                        @click="openClaimTypeModal"
                    >
                        Ambil Hadiah
                    </button>

                    <button
                        type="button"
                        class="rounded-xl bg-[#FF6D5A] px-6 py-4 text-base font-medium text-white transition hover:bg-[#ff7d6d]"
                        @click="setSelectionMode('sellback')"
                    >
                        Jual Kembali
                    </button>
                </div>

                <div
                    v-if="inventories.data.length && selectionMode"
                    class="mt-10 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="text-base font-medium text-white/90">
                        {{ selectedCount }} Hadiah dipilih
                    </div>

                    <div class="flex gap-4 md:w-[520px]">
                        <button
                            type="button"
                            class="flex-1 rounded-xl bg-white/5 px-6 py-4 text-base font-medium text-white transition hover:bg-[#15233d]"
                            @click="cancelSelectionMode"
                        >
                            Batal
                        </button>

                        <button
                            v-if="isSellbackMode"
                            type="button"
                            class="flex-1 rounded-xl bg-[#FF6D5A] px-6 py-4 text-base font-medium text-white transition hover:bg-[#ff7d6d] disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="selectedCount === 0"
                            @click="goToSellbackConfirm"
                        >
                            Lanjutkan
                        </button>

                        <button
                            v-if="isClaimMode"
                            type="button"
                            class="flex-1 rounded-xl bg-[#FF6D5A] px-6 py-4 text-base font-medium text-white transition hover:bg-[#ff7d6d] disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="selectedCount === 0"
                            @click="goToShipmentConfirm"
                        >
                            Lanjutkan ke Pengiriman
                        </button>
                    </div>
                </div>

                <div
                    v-if="showClaimTypeModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
                    @click.self="closeClaimTypeModal"
                >
                    <div
                        class="w-full max-w-[600px] rounded-xl bg-[#101521] p-5 sm:p-6 shadow-2xl"
                    >
                        <div
                            class="flex items-center justify-between border-b border-white/10 pb-3"
                        >
                            <h2
                                class="text-lg sm:text-3xl font-medium text-white"
                            >
                                Ambil hadiah
                            </h2>
                        </div>

                        <div class="mt-5 space-y-3">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl bg-white/5 px-3 py-3 text-left transition hover:bg-[#1a2338]"
                                @click="choosePhysicalClaim"
                            >
                                <div class="flex min-w-0 items-center gap-4">
                                    <div
                                        class="flex h-[50px] w-[50px] shrink-0 items-center justify-center rounded-xl bg-[#FF6D5A] text-white"
                                    >
                                        <img
                                            :src="truckImage"
                                            alt="Kirim hadiah"
                                            class="h-[32px] w-[32px] object-contain"
                                        />
                                    </div>

                                    <div class="min-w-0">
                                        <div
                                            class="text-base sm:text-lg font-medium text-white"
                                        >
                                            Kirim hadiah
                                        </div>
                                        <div class="text-sm text-white/60">
                                            Figure, card, mug, towel, dll
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-6 w-6 sm:h-7 sm:w-7 items-center justify-center rounded-full bg-[#FF6D5A] px-3 text-sm sm:text-base font-medium text-white"
                                    >
                                        {{ claimSummary.physical ?? 0 }}
                                    </div>
                                    <i
                                        class="bi bi-chevron-right text-sm sm:text-base text-white"
                                    />
                                </div>
                            </button>

                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl bg-white/5 px-3 py-3 text-left transition hover:bg-[#1a2338]"
                                @click="chooseDigitalClaim"
                            >
                                <div class="flex min-w-0 items-center gap-4">
                                    <div
                                        class="flex h-[50px] w-[50px] shrink-0 items-center justify-center rounded-xl bg-[#FF6D5A] text-white"
                                    >
                                        <img
                                            :src="gifticonImage"
                                            alt="Kirim hadiah"
                                            class="h-[32px] w-[32px] object-contain"
                                        />
                                    </div>

                                    <div class="min-w-0">
                                        <div
                                            class="text-base sm:text-lg font-medium text-white"
                                        >
                                            Klaim hadiah digital
                                        </div>
                                        <div class="text-sm text-white/60">
                                            Voucher, token dll
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-6 w-6 sm:h-7 sm:w-7 items-center justify-center rounded-full bg-[#FF6D5A] px-3 text-sm sm:text-base font-medium text-white"
                                    >
                                        {{ claimSummary.digital ?? 0 }}
                                    </div>
                                    <i
                                        class="bi bi-chevron-right text-sm sm:text-base text-white"
                                    />
                                </div>
                            </button>
                        </div>

                        <button
                            type="button"
                            class="absolute"
                            @click="closeClaimTypeModal"
                        ></button>
                    </div>
                </div>
            </section>
        </div>

        <CsatFeedbackModal
            :show="isCsatModalOpen"
            :shipment-order-id="csatShipmentOrderId"
            @close="closeCsatModal"
            @skipped="skipCsatModal"
            @play-again="continuePlayingFromCsat"
        />

        <AppFooter />
    </div>
</template>
