<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import ProfileSectionLayout from "@/Layouts/ProfileSectionLayout.vue";

import flagId from "@/assets/flags/id.png";
import flagUs from "@/assets/flags/us.png";
import flagFr from "@/assets/flags/fr.png";
import flagDe from "@/assets/flags/de.png";
import flagJp from "@/assets/flags/jp.png";
import flagEs from "@/assets/flags/es.png";

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
    currentLanguage: {
        type: String,
        default: "id",
    },
});

const form = useForm({
    language: props.currentLanguage || "id",
});

const languages = [
    {
        key: "id",
        label: "Indonesia",
        image: flagId,
    },
    {
        key: "en",
        label: "English (US)",
        image: flagUs,
    },
    {
        key: "fr",
        label: "French",
        image: flagFr,
    },
    {
        key: "de",
        label: "German",
        image: flagDe,
    },
    {
        key: "ja",
        label: "Japanese",
        image: flagJp,
    },
    {
        key: "es",
        label: "Spanish",
        image: flagEs,
    },
];

const selectLanguage = (key) => {
    form.language = key;

    // form.put(route("profile.language.update"), {
    //     preserveScroll: true,
    // });
};
</script>

<template>
    <Head title="Language" />

    <ProfileSectionLayout
        active="language"
        :profile="profile"
        :token-balance="tokenBalance"
        :cat-koin-balance="catKoinBalance"
    >
        <div class="border-b border-white/10 pb-4">
            <h2 class="text-xl sm:text-2xl font-medium text-white">Language</h2>
            <p class="mt-2 text-sm sm:text-base text-white/55">
                Pilih bahasa yang ingin kamu gunakan.
            </p>
        </div>

        <div class="mt-6 space-y-4">
            <button
                v-for="language in languages"
                :key="language.key"
                type="button"
                class="flex w-full items-center justify-between rounded-xl bg-[#161C2B] px-3 pr-5 py-3 text-left transition hover:bg-[#1A2233]"
                @click="selectLanguage(language.key)"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-xl bg-white/[0.03] text-3xl"
                    >
                        <img
                            :src="language.image"
                            :alt="language.label"
                            class="h-8 w-8 rounded-full object-cover"
                        />
                    </div>

                    <div>
                        <div class="text-sm text-white/45">Language</div>
                        <div
                            class="mt-1 text-base sm:text-lg font-medium text-white"
                        >
                            {{ language.label }}
                        </div>
                    </div>
                </div>

                <div
                    class="flex h-4 w-4 sm:h-5 sm:w-5 items-center justify-center rounded-full border-2 transition"
                    :class="
                        form.language === language.key
                            ? 'border-[#F66455]'
                            : 'border-white/80'
                    "
                >
                    <div
                        v-if="form.language === language.key"
                        class="h-2.5 w-2.5 rounded-full bg-[#F66455]"
                    />
                </div>
            </button>
        </div>
    </ProfileSectionLayout>
</template>
