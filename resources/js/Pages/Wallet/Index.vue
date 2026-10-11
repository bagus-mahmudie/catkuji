<script setup>
import { computed, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import WalletOverviewPanel from "@/Components/Wallet/WalletOverviewPanel.vue";
import WalletTopupPanel from "@/Components/Wallet/WalletTopupPanel.vue";

const props = defineProps({
    wallet: { type: Object, default: () => ({}) },
    midtransClientKey: { type: String, default: "" },
    tokenBalance: { type: Number, default: 0 },
});

const showTopupPanel = ref(false);

const balance = computed(() => Number(props.wallet?.balance ?? 0));

const openTopupPanel = () => {
    showTopupPanel.value = true;
};

const closeTopupPanel = () => {
    showTopupPanel.value = false;
};
</script>

<template>
    <Head title="Top Up" />

    <div class="min-h-screen bg-[#0C0F19] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar
                :show-wallet-balance="false"
                @topup-click="openTopupPanel"
            />

            <div class="mx-auto mt-10 max-w-[760px]">
                <template v-if="!showTopupPanel">
                    <WalletOverviewPanel
                        :balance="balance"
                        @topup-click="openTopupPanel"
                    />
                </template>

                <template v-else>
                    <WalletTopupPanel
                        :initial-amount="10000"
                        @back="closeTopupPanel"
                    />
                </template>
            </div>
        </div>

        <AppFooter />
    </div>
</template>
