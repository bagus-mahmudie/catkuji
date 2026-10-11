<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref } from "vue";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import DrawResultModal from "@/Components/Draw/DrawResultModal.vue";
import giftImage from "@/assets/gift/gift.png";
import bgGift from "@/assets/gift/bg-gift.png";

const props = defineProps({
    packs: {
        type: Array,
        default: () => [],
    },
});

const selectedPack = ref(null);
const isDrawResultModalOpen = ref(false);

const openPackModal = (pack) => {
    selectedPack.value = pack;
    isDrawResultModalOpen.value = true;
};

const closePackModal = () => {
    isDrawResultModalOpen.value = false;
    selectedPack.value = null;

    router.reload({
        only: ["packs"],
        preserveScroll: true,
        preserveState: false,
    });
};

const handleGoInventory = () => {
    isDrawResultModalOpen.value = false;
    selectedPack.value = null;

    router.visit(route("inventory.index"));
};

const openTopupPage = () => {
    router.visit(route("wallet.index"));
};
</script>

<template>
    <Head title="Pack Saya" />

    <div class="min-h-screen bg-[#0C0F19] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar @topup-click="openTopupPage" />

            <main class="mx-auto max-w-7xl py-8">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h1 class="text-lg font-medium sm:text-2xl">
                            Pack Saya
                        </h1>
                        <p class="text-xs sm:text-base text-white/60">
                            Daftar pack yang belum dibuka
                        </p>
                    </div>

                    <Link
                        :href="route('inventory.index')"
                        class="flex h-10 w-10 items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-0 py-2 text-sm font-medium text-white transition hover:bg-white/10 sm:h-auto sm:w-auto sm:justify-start sm:px-4 sm:text-base"
                        title="Kembali"
                    >
                        <i class="bi bi-arrow-left text-base"></i>
                        <span class="hidden sm:inline"
                            >Kembali ke Inventory</span
                        >
                    </Link>
                </div>

                <div
                    v-if="packs.length === 0"
                    class="rounded-xl border border-white/10 bg-white/5 p-8 text-center"
                >
                    <p class="text-lg font-semibold">Belum ada pack</p>
                    <p class="mt-2 text-sm text-white/60">
                        Pack yang belum dibuka akan muncul di sini.
                    </p>
                </div>

                <div
                    v-else
                    class="grid grid-cols-3 gap-2 sm:gap-4 md:grid-cols-4"
                >
                    <button
                        v-for="pack in packs"
                        :key="pack.id"
                        type="button"
                        class="group overflow-hidden rounded-xl bg-[#101521] p-2 text-left shadow-[0_10px_30px_rgba(0,0,0,0.25)] transition duration-300 hover:-translate-y-0.5 hover:bg-[#0E1830] sm:p-4"
                        @click="openPackModal(pack)"
                    >
                        <div
                            class="mb-4 flex aspect-[4/5] items-center justify-center rounded-xl"
                            :style="{
                                backgroundImage: `url(${bgGift})`,
                                backgroundSize: 'cover',
                                backgroundPosition: 'center',
                                backgroundRepeat: 'no-repeat',
                            }"
                        >
                            <img
                                :src="giftImage"
                                alt="Pack"
                                class="gift-shake-on-hover h-16 w-16 object-contain transition duration-300 md:h-24 md:w-24 lg:h-36 lg:w-36"
                            />
                        </div>

                        <div class="space-y-1">
                            <p
                                class="text-[10px] text-white/80 md:text-xs lg:text-lg"
                            >
                                {{ pack.pack_count }} pack belum dibuka
                            </p>

                            <p class="text-[8px] text-white/40 sm:text-sm">
                                {{ pack.pack_no }}
                            </p>
                        </div>
                    </button>
                </div>
            </main>

            <DrawResultModal
                :key="selectedPack?.id ?? 'pack-modal'"
                :show="isDrawResultModalOpen"
                :result="selectedPack ? { pack: selectedPack } : null"
                initial-stage="gift"
                @close="closePackModal"
                @go-inventory="handleGoInventory"
            />
        </div>

        <AppFooter />
    </div>
</template>

<style scoped>
.group:hover .gift-shake-on-hover {
    animation: gift-shake-custom 0.6s ease-in-out infinite;
    transform-origin: center center;
    will-change: transform;
}

@keyframes gift-shake-custom {
    0% {
        transform: rotate(0deg) scale(1);
    }
    20% {
        transform: rotate(-3deg) scale(1.02);
    }
    40% {
        transform: rotate(3deg) scale(1.04);
    }
    60% {
        transform: rotate(-2deg) scale(1.04);
    }
    80% {
        transform: rotate(2deg) scale(1.02);
    }
    100% {
        transform: rotate(0deg) scale(1);
    }
}
</style>
