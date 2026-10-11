<script setup>
import { computed, ref } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import tokenImage from "@/assets/token-silver.png";
import TokenRedeemCard from "@/Components/Token/TokenRedeemCard.vue";

const props = defineProps({
    tokenBalance: {
        type: Number,
        default: 0,
    },
    redeemItems: {
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
});

const redeemItemList = computed(() => props.redeemItems?.data ?? []);

const paginationLinks = computed(() => {
    return props.redeemItems?.links ?? [];
});

const hasPagination = computed(() => {
    return Number(props.redeemItems?.last_page ?? 1) > 1;
});

const formattedTokenBalance = computed(() =>
    Number(props.tokenBalance ?? 0).toLocaleString("id-ID"),
);

const isHowToAddTokenModalOpen = ref(false);

const openHowToAddTokenModal = () => {
    isHowToAddTokenModalOpen.value = true;
};

const closeHowToAddTokenModal = () => {
    isHowToAddTokenModalOpen.value = false;
};

const tokenSteps = [
    "Mainkan game untuk mendapatkan token. Semakin sering kamu bermain, semakin banyak token yang bisa kamu kumpulkan dan tukarkan menjadi hadiah.",
];
</script>

<template>
    <Head title="Token" />

    <div class="min-h-screen bg-[#0C0F19] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-8 sm:px-6 lg:px-8">
            <AppNavbar />

            <section class="mt-8 rounded-xl bg-[#101521] p-5 sm:p-7">
                <div class="text-center">
                    <p class="text-base text-white/60">Token Kamu</p>

                    <div class="mt-4 flex items-center justify-center gap-3">
                        <img
                            :src="tokenImage"
                            alt="Token"
                            class="h-8 w-8 sm:h-10 sm:w-10 object-contain"
                        />

                        <div
                            class="text-3xl sm:text-4xl font-semibold text-white"
                        >
                            {{ formattedTokenBalance }} Token
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="mt-8 flex w-full items-center justify-between rounded-xl bg-white/[0.05] px-4 sm:px-5 py-3 sm:py-4 text-left text-sm sm:text-base font-medium text-white/80 transition hover:bg-white/[0.08] hover:text-white"
                    @click="openHowToAddTokenModal"
                >
                    <span>Lihat cara tambah token</span>
                    <i class="bi bi-arrow-right text-lg"></i>
                </button>
            </section>

            <section class="mt-10">
                <h1
                    class="text-3xl font-medium leading-tight text-white sm:text-4xl"
                >
                    Redeem token kamu
                </h1>

                <div
                    v-if="redeemItemList.length"
                    class="mt-5 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4"
                >
                    <TokenRedeemCard
                        v-for="item in redeemItemList"
                        :key="item.id"
                        :item="item"
                    />
                </div>

                <div
                    v-else
                    class="mt-8 rounded-xl border border-dashed border-white/10 bg-white/[0.03] px-6 py-12 text-center"
                >
                    <div class="text-lg font-semibold text-white">
                        Belum ada item redeem token
                    </div>

                    <p class="mt-2 text-sm text-white/50">
                        Prize yang bisa diredeem dengan token akan muncul di
                        sini.
                    </p>
                </div>

                <div
                    v-if="hasPagination"
                    class="mt-10 flex flex-wrap items-center justify-center gap-2"
                >
                    <template
                        v-for="(link, index) in paginationLinks"
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
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="isHowToAddTokenModalOpen"
                class="fixed inset-0 z-[110] flex items-center justify-center px-4 py-8"
            >
                <div
                    class="absolute inset-0 bg-black/70 backdrop-blur-[2px]"
                    @click="closeHowToAddTokenModal"
                />

                <div
                    class="relative z-10 flex w-full max-w-[560px] flex-col items-center"
                >
                    <div class="w-full overflow-hidden rounded-xl bg-[#101521]">
                        <div class="border-b border-white/4 px-6 py-4">
                            <h3
                                class="text-lg font-medium text-white sm:text-xl"
                            >
                                Cara mendapatkan token
                            </h3>
                        </div>

                        <div class="space-y-4 px-6 py-5">
                            <div
                                v-for="(step, i) in tokenSteps"
                                :key="i"
                                class="rounded-xl bg-white/5 px-5 py-4 text-sm sm:text-base text-white/90"
                            >
                                <div class="flex gap-3">
                                    <div class="flex-1 text-sm sm:text-base">
                                        {{ step }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-6 inline-flex h-[58px] w-[58px] items-center justify-center rounded-full bg-[#FB6758] text-white transition hover:scale-[1.03] hover:brightness-105"
                        @click="closeHowToAddTokenModal"
                    >
                        <i class="bi bi-x text-3xl leading-none" />
                    </button>
                </div>
            </div>
        </Teleport>

        <AppFooter />
    </div>
</template>
