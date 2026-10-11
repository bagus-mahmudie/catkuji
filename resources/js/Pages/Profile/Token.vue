<script setup>
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import ProfileSectionLayout from "@/Layouts/ProfileSectionLayout.vue";
import tokenImage from "@/assets/token-silver.png";

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
});

const formattedTokenBalance = computed(() =>
    Number(props.tokenBalance ?? 0).toLocaleString("id-ID"),
);
</script>

<template>
    <Head title="Token" />

    <ProfileSectionLayout
        active="token"
        :profile="profile"
        :token-balance="tokenBalance"
        :cat-koin-balance="catKoinBalance"
    >
        <div class="rounded-xl bg-[#101521]">
            <div class="text-center">
                <p class="text-base text-white/60">Token Kamu</p>

                <div class="mt-4 flex items-center justify-center gap-3">
                    <img
                        :src="tokenImage"
                        alt="Token"
                        class="h-8 w-8 sm:h-10 sm:w-10 object-contain"
                    />

                    <div class="text-2xl sm:text-3xl font-semibold text-white">
                        {{ formattedTokenBalance }} Token
                    </div>
                </div>
            </div>

            <Link
                :href="route('token.index')"
                class="mt-8 flex w-full items-center justify-between rounded-xl bg-white/[0.05] px-4 py-3 sm:px-5 sm:py-4 text-left text-sm sm:text-base font-medium text-white/80 transition hover:bg-white/[0.08] hover:text-white"
            >
                <span>Redeem token kamu</span>
                <i class="bi bi-arrow-right text-lg"></i>
            </Link>
        </div>
    </ProfileSectionLayout>
</template>
