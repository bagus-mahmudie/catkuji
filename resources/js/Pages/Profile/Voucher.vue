<script setup>
import { Head } from "@inertiajs/vue3";
import ProfileSectionLayout from "@/Layouts/ProfileSectionLayout.vue";

defineProps({
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
});
</script>

<template>
    <Head title="Cat Voucher" />

    <ProfileSectionLayout
        active="voucher"
        :profile="profile"
        :token-balance="tokenBalance"
        :cat-koin-balance="catKoinBalance"
    >
        <div class="border-b border-white/10 pb-4">
            <h2 class="text-xl sm:text-2xl font-medium text-white">
                Cat Voucher
            </h2>
            <p class="mt-2 text-sm sm:text-base text-white/55">
                Voucher dan promo kamu akan tampil di sini.
            </p>
        </div>

        <div
            class="border border-dashed border-white/15 mt-6 flex min-h-[260px] items-center justify-center rounded-xl bg-[#0C0F19] px-6 py-12 text-center"
        >
            <div>
                <h3 class="text-lg sm:text-2xl font-medium text-white">
                    Tidak ada voucher
                </h3>
                <p class="mt-1 text-xs sm:text-sm text-white/55">
                    Anda belum memiliki voucher.
                </p>
            </div>
        </div>
    </ProfileSectionLayout>
</template>
