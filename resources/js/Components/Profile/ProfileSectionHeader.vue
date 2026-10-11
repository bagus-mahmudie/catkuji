<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import tokenImage from "@/assets/token-silver.png";
import catKoinImage from "@/assets/cat-koin.png";
import { resolveAvatarUrl } from "@/Utils/avatar";

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
const page = usePage();

const userAvatarUrl = computed(() => {
    return resolveAvatarUrl(page.props.auth?.user);
});
</script>

<template>
    <section class="overflow-hidden rounded-xl bg-[#101521]">
        <div
            class="flex flex-col gap-5 border-b border-white/10 px-3 py-3 sm:px-5 sm:py-5 md:flex-row md:items-center md:justify-between md:px-6"
        >
            <div class="flex items-center gap-4">
                <img
                    :src="userAvatarUrl"
                    alt="Avatar"
                    class="h-16 w-16 rounded-full object-cover ring-2 ring-white/10"
                />

                <div>
                    <h1 class="text-xl font-medium text-white">
                        {{ profile.name }}
                    </h1>
                    <p class="mt-1 text-sm text-white/65">
                        {{ profile.email }}
                    </p>
                </div>
            </div>

            <Link
                :href="route('profile.edit')"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#F66455] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#ff7a6d] sm:text-base"
            >
                <i class="bi bi-pencil" />
                <span>Edit</span>
            </Link>
        </div>

        <div class="grid grid-cols-2 gap-4 px-3 py-3 md:px-6 md:py-6">
            <div>
                <p class="mb-2 text-base text-white/55">Cat Token</p>
                <div class="flex items-center gap-2 sm:gap-3">
                    <img
                        :src="tokenImage"
                        alt="Token"
                        class="h-5 w-5 sm:h-6 sm:w-6 object-contain"
                    />
                    <p class="text-base sm:text-lg font-medium text-white">
                        {{ Number(tokenBalance).toLocaleString("id-ID") }}
                    </p>
                </div>
            </div>

            <div class="border-l-0 border-white/10 md:border-l md:pl-8">
                <p class="mb-2 text-base text-white/55">Cat Koin</p>
                <div class="flex items-center gap-2 sm:gap-3">
                    <img
                        :src="catKoinImage"
                        alt="Cat Koin"
                        class="h-5 w-5 sm:h-6 sm:w-6 object-contain"
                    />
                    <p class="text-base sm:text-lg font-medium text-white">
                        {{ Number(catKoinBalance).toLocaleString("id-ID") }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
