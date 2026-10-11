<script setup>
import { Head, router, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import TransactionSidebar from "@/Components/Transactions/TransactionSidebar.vue";
import TransactionStatusFilter from "@/Components/Transactions/TransactionStatusFilter.vue";
import TransactionAccordionList from "@/Components/Transactions/TransactionAccordionList.vue";

const props = defineProps({
    transactions: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({
            service: "all",
            status: "all",
        }),
    },
});

const page = usePage();

const searchParams = computed(() => {
    const url = new URL(page.url, window.location.origin);
    return url.searchParams;
});

const initialOpenId = computed(() => searchParams.value.get("open") || "");

const setService = (service) => {
    router.get(
        route("transactions.index"),
        {
            service,
            status: props.filters.status,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const setStatus = (status) => {
    router.get(
        route("transactions.index"),
        {
            service: props.filters.service,
            status,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const goToTopup = () => {
    router.get(route("wallet.index"));
};
</script>

<template>
    <Head title="Transaksi" />

    <div class="min-h-screen bg-[#0C0F19] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar :show-wallet-balance="false" @topup-click="goToTopup" />

            <div
                class="mt-8 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
            >
                <h1 class="text-3xl font-medium sm:text-4xl">Transaksi</h1>

                <TransactionStatusFilter
                    :model-value="filters.status"
                    @change="setStatus"
                />
            </div>

            <div
                class="mt-4 sm:mt-8 grid gap-4 sm:gap-6 lg:grid-cols-[330px_minmax(0,1fr)]"
            >
                <TransactionSidebar
                    :active-service="filters.service"
                    @change="setService"
                />

                <TransactionAccordionList
                    :transactions="transactions"
                    :active-service="filters.service"
                    :initial-open-id="initialOpenId"
                />
            </div>
        </div>

        <AppFooter />
    </div>
</template>
