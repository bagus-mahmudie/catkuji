<script setup>
import { Head, router } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { reactive, watch } from "vue";

const props = defineProps({
    columns: {
        type: Object,
        default: () => ({
            detractors: { title: "Detractors", items: [] },
            passives: { title: "Passive", items: [] },
            promoters: { title: "Promoter", items: [] },
        }),
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    periodOptions: {
        type: Array,
        default: () => [],
    },
});

const filterForm = reactive({
    period: props.filters.period || "day",
    date: props.filters.date || new Date().toISOString().slice(0, 10),
    search: props.filters.search || "",
});

const applyFilters = () => {
    router.get(
        route("admin.users.nps.detail"),
        {
            period: filterForm.period,
            date:
                filterForm.period === "day"
                    ? filterForm.date || new Date().toISOString().slice(0, 10)
                    : undefined,
            search: filterForm.search || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

watch(
    () => filterForm.period,
    () => {
        applyFilters();
    },
);

watch(
    () => filterForm.date,
    () => {
        if (filterForm.period === "day") {
            applyFilters();
        }
    },
);

let searchTimeout = null;

watch(
    () => filterForm.search,
    () => {
        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 400);
    },
);

const exportCsv = () => {
    window.location.href = route("admin.users.nps.export", {
        period: filterForm.period,
        date:
            filterForm.period === "day"
                ? filterForm.date || new Date().toISOString().slice(0, 10)
                : undefined,
        search: filterForm.search || undefined,
    });
};
</script>

<template>
    <Head title="NPS Detail" />

    <AdminLayout
        title="NPS Detail"
        :breadcrumb="[
            { label: 'User', href: route('admin.users.index') },
            { label: 'Net Promoter Score (NPS)' },
        ]"
    >
        <div class="space-y-5">
            <section
                class="flex flex-col gap-4 border-b border-white/10 pb-4 xl:flex-row xl:items-center xl:justify-between"
            >
                <div>
                    <h1
                        class="text-xl font-medium leading-tight sm:text-2xl lg:text-3xl"
                    >
                        Net Promoter Score (NPS)
                    </h1>

                    <div
                        class="mt-1 flex flex-wrap gap-4 text-xs sm:text-sm text-white/35"
                    >
                        <span>
                            Average Score:
                            <b class="text-white font-medium"
                                >{{ summary.average_score || 0 }}/10</b
                            >
                        </span>

                        <span>
                            Total:
                            <b class="text-white font-medium">{{
                                summary.total_responses || 0
                            }}</b>
                        </span>
                    </div>
                </div>

                <div
                    class="flex w-full flex-nowrap items-center gap-2 overflow-x-auto lg:w-auto lg:overflow-visible"
                >
                    <input
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Search..."
                        class="h-10 rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white placeholder:text-white/35 outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
                    />

                    <select
                        v-model="filterForm.period"
                        class="h-10 rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
                    >
                        <option
                            v-for="option in periodOptions"
                            :key="option.value"
                            :value="option.value"
                            class="bg-[#2b2c29]"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <div v-if="filterForm.period === 'day'" class="relative">
                        <input
                            v-model="filterForm.date"
                            type="date"
                            class="h-10 w-full rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
                        />
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#ff675c] px-2 sm:px-5 text-xs sm:text-sm font-medium text-white hover:bg-[#ff5145]"
                        @click="exportCsv"
                    >
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <span class="hidden sm:inline">Export CSV</span>
                        <span class="sm:hidden">Export</span>
                    </button>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                <article
                    v-for="column in [
                        columns.detractors,
                        columns.passives,
                        columns.promoters,
                    ]"
                    :key="column.title"
                    class="flex h-[calc(100vh-230px)] min-h-[560px] min-w-0 flex-col rounded-xl bg-[#1d1e1c]"
                >
                    <div
                        class="flex shrink-0 items-start justify-between px-4 py-3"
                    >
                        <div>
                            <h2 class="text-base font-medium text-white">
                                {{ column.title }}
                            </h2>

                            <p class="text-xs sm:text-sm text-white/35">
                                {{ column.description }}
                                <span class="mx-1">•</span>
                                {{ column.items.length }} feedback
                            </p>
                        </div>

                        <i class="bi bi-info-circle text-white/35"></i>
                    </div>

                    <div
                        class="min-h-0 flex-1 overflow-hidden rounded-xl bg-[#343531]"
                    >
                        <div
                            class="h-full space-y-3 overflow-y-auto p-3 nps-column-scroll"
                        >
                            <article
                                v-for="item in column.items"
                                :key="item.id"
                                class="rounded-lg bg-white/5 p-4"
                            >
                                <p
                                    class="line-clamp-3 break-words text-sm leading-6 text-white"
                                >
                                    {{ item.feedback }}
                                </p>

                                <div
                                    class="my-4 border-t border-white/10"
                                ></div>

                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <img
                                            :src="item.avatar"
                                            :alt="item.name"
                                            class="h-10 w-10 shrink-0 rounded-full object-cover"
                                        />

                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-medium text-white"
                                            >
                                                {{ item.name }}
                                            </p>

                                            <p
                                                class="mt-1 truncate text-xs text-white/35"
                                            >
                                                {{ item.date }}
                                                <span class="mx-1">•</span>
                                                {{ item.time }}
                                            </p>
                                        </div>
                                    </div>

                                    <span
                                        class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-medium text-[#ff675c]"
                                    >
                                        {{ item.score }}/10
                                    </span>
                                </div>
                            </article>

                            <div
                                v-if="!column.items.length"
                                class="flex h-full min-h-[240px] items-center justify-center rounded-lg border border-dashed border-white/10 text-center text-sm text-white/35"
                            >
                                Tidak ada feedback pada kategori ini.
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        </div>
    </AdminLayout>
</template>

<style scoped>
.nps-column-scroll {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.nps-column-scroll::-webkit-scrollbar {
    display: none;
}
</style>
