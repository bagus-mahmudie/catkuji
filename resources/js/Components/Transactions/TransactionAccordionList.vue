<script setup>
import { computed, ref } from "vue";
import { watch, nextTick, onMounted } from "vue";
import TopupTransactionCard from "@/Components/Transactions/cards/TopupTransactionCard.vue";
import ShippingTransactionCard from "@/Components/Transactions/cards/ShippingTransactionCard.vue";
import SellbackTransactionCard from "@/Components/Transactions/cards/SellbackTransactionCard.vue";
import PurchaseTransactionCard from "@/Components/Transactions/cards/PurchaseTransactionCard.vue";
import DigitalClaimTransactionCard from "@/Components/Transactions/cards/DigitalClaimTransactionCard.vue";

const props = defineProps({
    transactions: {
        type: Array,
        default: () => [],
    },
    activeService: {
        type: String,
        default: "all",
    },
    initialOpenId: {
        type: String,
        default: "",
    },
});

const expandedIds = ref([]);
const showAll = ref(false);
const initialLimit = 5;

const visibleTransactions = computed(() => {
    if (showAll.value) {
        return props.transactions;
    }

    return props.transactions.slice(0, initialLimit);
});

const hasMoreThanLimit = computed(() => {
    return props.transactions.length > initialLimit;
});

const remainingCount = computed(() => {
    return Math.max(props.transactions.length - initialLimit, 0);
});

const toggleCard = (id) => {
    if (expandedIds.value.includes(id)) {
        expandedIds.value = expandedIds.value.filter((item) => item !== id);
        return;
    }

    expandedIds.value = [id];
};

const isExpanded = (id) => expandedIds.value.includes(id);

const toggleShowAll = () => {
    showAll.value = !showAll.value;

    if (!showAll.value) {
        const visibleIds = visibleTransactions.value.map((item) => item.id);
        expandedIds.value = expandedIds.value.filter((id) =>
            visibleIds.includes(id),
        );
    }
};

const scrollToCard = async (id) => {
    await nextTick();

    const el = document.getElementById(`transaction-card-${id}`);
    if (!el) return;

    el.scrollIntoView({
        behavior: "smooth",
        block: "start",
    });
};

const applyInitialOpen = async () => {
    if (!props.initialOpenId) return;

    const exists = props.transactions.some(
        (item) => item.id === props.initialOpenId,
    );
    if (!exists) return;

    showAll.value = true;
    expandedIds.value = [props.initialOpenId];

    await scrollToCard(props.initialOpenId);
};

onMounted(() => {
    applyInitialOpen();
});

watch(
    () => props.initialOpenId,
    () => {
        applyInitialOpen();
    },
);

watch(
    () => props.transactions,
    () => {
        applyInitialOpen();
    },
    { deep: true },
);
</script>

<template>
    <section class="space-y-4">
        <div
            v-if="transactions.length === 0"
            class="rounded-xl bg-[#101521] px-8 py-14 text-center"
        >
            <div class="text-2xl font-semibold text-white">
                Belum ada transaksi
            </div>
            <div class="mt-2 text-white/60">Tidak ada data transaksi.</div>
        </div>

        <template v-for="item in visibleTransactions" :key="item.id">
            <div :id="`transaction-card-${item.id}`">
                <TopupTransactionCard
                    v-if="item.service === 'topup'"
                    :item="item"
                    :expanded="isExpanded(item.id)"
                    @toggle="toggleCard(item.id)"
                />

                <ShippingTransactionCard
                    v-else-if="item.service === 'shipping'"
                    :item="item"
                    :expanded="isExpanded(item.id)"
                    @toggle="toggleCard(item.id)"
                />

                <SellbackTransactionCard
                    v-else-if="item.service === 'sellback'"
                    :item="item"
                    :expanded="isExpanded(item.id)"
                    @toggle="toggleCard(item.id)"
                />

                <PurchaseTransactionCard
                    v-else-if="item.service === 'purchase'"
                    :item="item"
                    :expanded="isExpanded(item.id)"
                    @toggle="toggleCard(item.id)"
                />

                <DigitalClaimTransactionCard
                    v-else-if="item.service === 'digital_claim'"
                    :item="item"
                    :expanded="isExpanded(item.id)"
                    @toggle="toggleCard(item.id)"
                />
            </div>
        </template>

        <div v-if="hasMoreThanLimit" class="pt-2">
            <button
                type="button"
                class="flex w-full items-center shadow-xl justify-center gap-2 rounded-xl bg-[#101521] px-6 py-4 text-base font-medium text-white transition hover:bg-[#131c2c]"
                @click="toggleShowAll"
            >
                <span v-if="!showAll">
                    Lihat Semua
                    <span v-if="remainingCount > 0" class="text-white/60">
                        ({{ remainingCount }} lainnya)
                    </span>
                </span>

                <span v-else>Tampilkan Lebih Sedikit</span>

                <i
                    class="bi text-sm transition duration-200"
                    :class="showAll ? 'bi-chevron-up' : 'bi-chevron-down'"
                />
            </button>
        </div>
    </section>
</template>
