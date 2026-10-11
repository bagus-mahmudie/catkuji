<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { router } from "@inertiajs/vue3";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import StandardEventCard from "@/Components/Home/StandardEventCard.vue";

const props = defineProps({
    category: Object,
    events: Array,
    filters: Object,
    availableTypes: Array,
});

const filterTabs = computed(() => {
    return [{ value: "semua", label: "Semua" }, ...props.availableTypes];
});

const setTypeFilter = (value) => {
    router.get(
        route("categories.show", props.category.slug),
        { type: value === "semua" ? "" : value },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const staticDescriptions = {
    gadget: "Set berhadiah gadget-gadget terkini yang bisa didapatkan lewat pengalaman belanja yang seru",
    gaming: "Set bertema game yang menghadirkan hadiah seperti merchandise, voucher, dan item digital dari berbagai platform gaming.",
    ichibankuji:
        "Gacha resmi dari Bandai Jepang berisi hadiah acak seperti figure dan koleksi anime populer, favorit para kolektor!",
    tcg: "Paket kartu TCG acak buatan Kitakuji, berisi campuran kartu dari reguler hingga langka, cocok untuk kolektor dan pencari kejutan!",
};

const activeType = computed(() => props.filters?.type || "semua");

const resolvedCategoryGlowColor = computed(() => {
    const color = String(props.category?.category_glow_color || "").trim();

    if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
        return color;
    }

    return "#F66455";
});
</script>
<template>
    <Head :title="category.name" />

    <div class="min-h-screen bg-[#0C0F19] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-8 sm:px-6 lg:px-8">
            <AppNavbar />

            <div class="mb-6 mt-6 sm:mb-8 sm:mt-8">
                <h1
                    class="text-3xl sm:text-4xl font-medium leading-tight text-white"
                >
                    {{ category.name }}
                </h1>

                <p class="mt-3 max-w-[600px] text-base text-white/65">
                    {{ staticDescriptions[category.slug] || "" }}
                </p>
            </div>

            <div class="mb-6 sm:mb-8 flex flex-wrap gap-3">
                <button
                    v-for="item in filterTabs"
                    :key="item.value"
                    type="button"
                    @click="setTypeFilter(item.value)"
                    :class="[
                        'rounded-xl px-4 py-2 text-base font-medium transition',
                        activeType === item.value
                            ? 'bg-[#11182B] text-white border border-[#1F2A44]'
                            : 'bg-white/5 text-white/70 border border-transparent hover:bg-white/10',
                    ]"
                >
                    {{ item.label }}
                </button>
            </div>

            <div
                v-if="events.length"
                class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4"
            >
                <StandardEventCard
                    v-for="event in events"
                    :key="event.id"
                    :event="event"
                    :glow-color="resolvedCategoryGlowColor"
                />
            </div>

            <div
                v-else
                class="rounded-xl border border-dashed border-white/10 bg-white/[0.03] px-6 py-10 text-center text-white/50"
            >
                Belum ada event pada filter ini.
            </div>
        </div>

        <AppFooter />
    </div>
</template>
