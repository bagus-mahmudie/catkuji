<script setup>
import { Link } from "@inertiajs/vue3";
import catKoinImage from "@/assets/cat-koin.png";

defineProps({
    balance: {
        type: Number,
        default: 0,
    },
});

defineEmits(["topup-click"]);
</script>

<template>
    <div class="rounded-xl bg-[#101521] px-6 py-8 text-center">
        <div class="text-lg text-white/70">Saldo Kamu</div>

        <div class="mt-4 flex items-center justify-center gap-3">
            <img
                :src="catKoinImage"
                alt="Cat Koin"
                class="h-8 w-8 shrink-0 object-contain sm:h-10 sm:w-10"
            />
            <div class="text-3xl font-semibold text-white sm:text-4xl">
                {{ Number(balance ?? 0).toLocaleString("id-ID") }} Coin
            </div>
        </div>

        <div class="mt-7 flex justify-center">
            <button
                type="button"
                class="inline-flex min-w-[220px] items-center justify-center rounded-xl bg-[#F66455] px-8 py-3 text-lg font-medium text-white transition hover:bg-[#ff7d70]"
                @click="$emit('topup-click')"
            >
                + Top Up
            </button>
        </div>

        <Link
            :href="route('home')"
            class="mt-8 flex items-center justify-between rounded-xl bg-white/[0.04] px-5 py-4 text-sm text-white/75 transition hover:bg-white/[0.06]"
        >
            <span>Yuk pakai Cat Koin buat main Cat Kuji</span>

            <i class="bi bi-arrow-right text-base text-white/60"></i>
        </Link>
    </div>
</template>
