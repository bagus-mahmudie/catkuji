<script setup>
import { computed } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import HeroBanner from "@/Components/Home/HeroBanner.vue";
import HomeFilterBar from "@/Components/Home/HomeFilterBar.vue";
import CategorySection from "@/Components/Home/CategorySection.vue";

const props = defineProps({
    banners: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    allCategories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            category: "",
            search: "",
        }),
    },
    hasAnyEventResults: {
        type: Boolean,
        default: false,
    },
});

const openTopupPage = () => {
    router.visit(route("wallet.index"));
};

const isSearching = computed(() => {
    return !!props.filters.search;
});
</script>

<template>
    <Head title="Homepage" />

    <div class="min-h-screen bg-[#0C0F19] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar
                :show-wallet-balance="false"
                @topup-click="openTopupPage"
            />

            <HeroBanner :banners="banners" />

            <HomeFilterBar
                :categories="allCategories"
                :selected-category="filters.category"
                :search="filters.search"
            />

            <div
                v-if="isSearching && !hasAnyEventResults"
                class="mt-10 rounded-xl border border-dashed border-white/10 bg-white/[0.03] px-6 py-12 text-center"
            >
                <div class="mx-auto max-w-[420px]">
                    <h2 class="text-xl font-medium text-white">
                        Tidak ada hasil pencarian
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-white/55">
                        Coba gunakan kata kunci lain atau ubah kategori
                        pencarianmu.
                    </p>
                </div>
            </div>

            <div v-else class="mt-10">
                <CategorySection
                    v-for="category in categories"
                    :key="category.id"
                    :title="category.name"
                    :slug="category.slug"
                    :subtitle="category.description"
                    :card-style="category.card_style"
                    :promo-left-bg-url="category.promo_left_bg_url"
                    :category-glow-color="category.category_glow_color"
                    :category-glow-position="category.category_glow_position"
                    :promo-starts-at="category.promo_starts_at"
                    :promo-ends-at="category.promo_ends_at"
                    :events="category.events"
                />
            </div>
        </div>

        <AppFooter />
    </div>
</template>
