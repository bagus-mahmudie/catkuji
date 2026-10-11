<script setup>
import { Head, Link } from "@inertiajs/vue3";
import ProfileSectionLayout from "@/Layouts/ProfileSectionLayout.vue";
import StandardEventCard from "@/Components/Home/StandardEventCard.vue";

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
    events: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Saved Wishlist" />

    <ProfileSectionLayout
        active="wishlist"
        :profile="profile"
        :token-balance="tokenBalance"
        :cat-koin-balance="catKoinBalance"
    >
        <div class="border-b border-white/10 pb-4">
            <h1 class="text-xl font-medium text-white sm:text-2xl">
                Saved Wishlist
            </h1>

            <p class="mt-2 text-sm text-white/55 sm:text-base">
                Event yang sudah kamu simpan akan tampil di sini.
            </p>
        </div>

        <div v-if="events.data.length" class="mt-6">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-3">
                <StandardEventCard
                    v-for="event in events.data"
                    :key="event.id"
                    :event="event"
                    :glow-color="event.category_glow_color"
                />
            </div>

            <div
                v-if="events.links && events.links.length > 3"
                class="mt-8 flex flex-wrap items-center justify-center gap-2"
            >
                <component
                    v-for="(link, index) in events.links"
                    :key="index"
                    :is="link.url ? Link : 'span'"
                    :href="link.url || undefined"
                    v-html="link.label"
                    class="inline-flex min-w-[42px] items-center justify-center rounded-xl border px-4 py-2 text-sm font-medium transition"
                    :class="
                        link.active
                            ? 'border-[#F66455] bg-[#F66455] text-white'
                            : link.url
                              ? 'border-white/10 bg-white/[0.03] text-white/80 hover:bg-white/[0.06]'
                              : 'cursor-not-allowed border-white/5 bg-white/[0.02] text-white/30'
                    "
                />
            </div>
        </div>

        <div
            v-else
            class="mt-6 rounded-xl border border-dashed border-white/10 bg-white/[0.03] px-6 py-12 text-center"
        >
            <div class="text-lg font-medium text-white">
                Wishlist masih kosong
            </div>

            <p class="mt-2 text-sm text-white/50">
                Tekan tombol heart pada event untuk menyimpannya di sini.
            </p>
        </div>
    </ProfileSectionLayout>
</template>
