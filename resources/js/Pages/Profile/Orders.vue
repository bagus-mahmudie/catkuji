<script setup>
import { Head } from "@inertiajs/vue3";
import ProfileSectionLayout from "@/Layouts/ProfileSectionLayout.vue";
import TransactionAccordionList from "@/Components/Transactions/TransactionAccordionList.vue";

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
    tokenBalance: {
        type: Number,
        default: 0,
    },
    catKoinBalance: {
        type: Number,
        default: 0,
    },
    transactions: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Order History" />

    <ProfileSectionLayout
        active="orders"
        :profile="profile"
        :token-balance="tokenBalance"
        :cat-koin-balance="catKoinBalance"
    >
        <div class="border-b border-white/10 pb-4">
            <h2 class="text-xl sm:text-2xl font-medium text-white">
                Order History
            </h2>
            <p class="mt-2 text-sm sm:text-base text-white/55">
                Riwayat pembelian gacha kamu akan tampil di sini.
            </p>
        </div>

        <div class="mt-6">
            <TransactionAccordionList
                :transactions="transactions"
                active-service="purchase"
                initial-open-id=""
            />
        </div>
    </ProfileSectionLayout>
</template>
