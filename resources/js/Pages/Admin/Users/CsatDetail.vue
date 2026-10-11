<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { computed, reactive, ref, watch } from "vue";

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({}),
    },
    distribution: {
        type: Array,
        default: () => [],
    },
    reviews: {
        type: Array,
        default: () => [],
    },
    selectedReview: {
        type: Object,
        default: null,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    periodOptions: {
        type: Array,
        default: () => [],
    },
    ratingOptions: {
        type: Array,
        default: () => [],
    },
});

const selected = ref(props.selectedReview);

const filterForm = reactive({
    period: props.filters.period || "day",
    date: props.filters.date || new Date().toISOString().slice(0, 10),
    rating: props.filters.rating || "",
    search: props.filters.search || "",
});

const noteForm = useForm({
    title: "",
    description: "",
    csat_feedback_id: null,
});

const submitNote = () => {
    if (!selected.value) {
        return;
    }

    noteForm.csat_feedback_id = selected.value.id;

    noteForm.post(route("admin.marketing.notes.store"), {
        preserveScroll: true,
        onSuccess: () => {
            noteForm.reset();
            noteForm.clearErrors();
        },
    });
};

const stars = computed(() => {
    const rating = Math.round(Number(props.summary.average || 0));

    return [1, 2, 3, 4, 5].map((star) => star <= rating);
});

const selectedStars = computed(() => {
    const rating = Math.round(Number(selected.value?.rating || 0));

    return [1, 2, 3, 4, 5].map((star) => star <= rating);
});

const applyFilters = () => {
    router.get(
        route("admin.users.csat.detail"),
        {
            period: filterForm.period,
            date:
                filterForm.period === "day"
                    ? filterForm.date || new Date().toISOString().slice(0, 10)
                    : undefined,
            rating: filterForm.rating || undefined,
            search: filterForm.search || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onSuccess: () => {
                selected.value = props.reviews[0] || null;
            },
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

watch(
    () => filterForm.rating,
    () => {
        applyFilters();
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
    window.location.href = route("admin.users.csat.export", {
        period: filterForm.period,
        date:
            filterForm.period === "day"
                ? filterForm.date || new Date().toISOString().slice(0, 10)
                : undefined,
        rating: filterForm.rating || undefined,
        search: filterForm.search || undefined,
    });
};
</script>

<template>
    <Head title="CSAT Detail" />

    <AdminLayout
        title="CSAT Detail"
        :breadcrumb="[
            { label: 'User', href: route('admin.users.index') },
            { label: 'Satisfaction Score' },
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
                        Satisfaction Score (CSAT)
                    </h1>
                </div>

                <div
                    class="flex w-full flex-nowrap items-center gap-2 overflow-x-auto lg:w-auto lg:overflow-visible"
                >
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
                        Export CSV
                    </button>
                </div>
            </section>

            <section
                class="grid grid-cols-1 gap-3 sm:gap-5 sm:grid-cols-[minmax(0,1fr)_500px]"
            >
                <div class="min-w-0 space-y-3 sm:space-y-5">
                    <article class="rounded-xl border-none bg-[#343531] p-5">
                        <div class="grid gap-6 lg:grid-cols-[150px_1fr]">
                            <div
                                class="flex flex-col justify-center items-center"
                            >
                                <p class="text-5xl font-medium text-white">
                                    {{ summary.average || 0 }}
                                </p>

                                <div
                                    class="mt-2 flex items-center gap-2 text-xl text-[#ff675c]"
                                >
                                    <i
                                        v-for="(active, index) in stars"
                                        :key="index"
                                        :class="[
                                            'bi',
                                            active
                                                ? 'bi-star-fill'
                                                : 'bi-star-fill text-white/10',
                                        ]"
                                    ></i>
                                </div>

                                <p class="mt-2 text-sm text-white/35">
                                    {{
                                        summary.total_ratings_label ||
                                        "0 ratings"
                                    }}
                                </p>
                            </div>

                            <div class="space-y-4">
                                <div
                                    v-for="item in distribution"
                                    :key="item.value"
                                    class="grid grid-cols-[42px_1fr_92px] items-center gap-5"
                                >
                                    <p class="text-sm text-white">
                                        {{ item.rating }}
                                    </p>

                                    <div class="h-2 rounded-full bg-white/10">
                                        <div
                                            class="h-2 rounded-full bg-[#ff675c]"
                                            :style="{
                                                width: `${item.percentage}%`,
                                            }"
                                        ></div>
                                    </div>

                                    <p class="text-right text-sm text-white/35">
                                        {{ item.count_label }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-xl border-none bg-[#343531]">
                        <div class="border-b border-white/10 p-3">
                            <div
                                class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                            >
                                <select
                                    v-model="filterForm.rating"
                                    class="h-10 rounded-lg border-none bg-white/5 px-4 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                >
                                    <option
                                        v-for="option in ratingOptions"
                                        :key="option.value"
                                        :value="option.value"
                                        class="bg-[#2b2c29]"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>

                                <input
                                    v-model="filterForm.search"
                                    type="search"
                                    placeholder="Search review..."
                                    class="h-10 rounded-lg border-none bg-white/5 px-4 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                />
                            </div>
                        </div>

                        <div class="divide-y divide-white/10">
                            <button
                                v-for="review in reviews"
                                :key="review.id"
                                type="button"
                                class="flex w-full items-start gap-4 px-4 py-5 text-left transition hover:bg-white/[0.03]"
                                :class="
                                    selected?.id === review.id
                                        ? 'bg-white/[0.03]'
                                        : ''
                                "
                                @click="selected = review"
                            >
                                <img
                                    :src="review.avatar"
                                    :alt="review.name"
                                    class="h-12 w-12 shrink-0 rounded-full object-cover"
                                />

                                <div class="min-w-0 flex-1">
                                    <p
                                        class="line-clamp-2 break-words text-sm leading-6 text-white"
                                    >
                                        {{ review.review }}
                                    </p>

                                    <p
                                        class="mt-2 truncate text-xs text-white/35"
                                    >
                                        {{ review.name }}
                                        <span class="mx-2">•</span>
                                        {{ review.date }}
                                    </p>
                                </div>

                                <span
                                    class="inline-flex shrink-0 items-center gap-2 rounded-full bg-white/5 px-4 py-2 text-sm text-[#ff675c]"
                                >
                                    <i class="bi bi-star-fill"></i>
                                    {{ Number(review.rating).toFixed(1) }}
                                </span>
                            </button>

                            <div
                                v-if="!reviews.length"
                                class="px-4 py-16 text-sm sm:text-base text-center text-white/35"
                            >
                                Belum ada review CSAT pada filter ini.
                            </div>
                        </div>
                    </article>
                </div>

                <aside class="min-w-0 rounded-xl border-none bg-[#343531] p-4">
                    <template v-if="selected">
                        <div class="flex flex-col items-center text-center">
                            <img
                                :src="selected.avatar"
                                :alt="selected.name"
                                class="h-20 w-20 rounded-full object-cover"
                            />

                            <h2 class="mt-4 text-lg font-medium text-white">
                                {{ selected.name }}
                            </h2>

                            <p class="text-sm text-white/35">
                                {{ selected.date }}
                            </p>
                        </div>

                        <div class="my-4 border-t border-white/10"></div>

                        <div
                            class="flex justify-center gap-3 text-xl text-[#ff675c]"
                        >
                            <i
                                v-for="(active, index) in selectedStars"
                                :key="index"
                                :class="[
                                    'bi',
                                    active
                                        ? 'bi-star-fill'
                                        : 'bi-star-fill text-white/10',
                                ]"
                            ></i>
                        </div>

                        <div class="mt-4 rounded-lg bg-white/5 p-4">
                            <p class="text-sm leading-6 break-words text-white">
                                {{ selected.review }}
                            </p>
                        </div>

                        <div class="mt-6">
                            <h3 class="font-medium text-white">Add New Note</h3>

                            <div class="mt-2 border-t border-white/10 pt-4">
                                <label class="text-sm text-white">Title</label>
                                <input
                                    v-model="noteForm.title"
                                    type="text"
                                    placeholder="Enter note title"
                                    class="mt-1 h-10 w-full rounded-lg border-none bg-white/5 px-4 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                />
                                <p
                                    v-if="noteForm.errors.title"
                                    class="mt-1 text-xs text-red-300"
                                >
                                    {{ noteForm.errors.title }}
                                </p>
                            </div>

                            <div class="mt-4">
                                <label class="text-sm text-white"
                                    >Description</label
                                >
                                <textarea
                                    v-model="noteForm.description"
                                    rows="7"
                                    placeholder="Enter note Description"
                                    class="mt-1 w-full rounded-lg border-none bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                ></textarea>
                                <p
                                    v-if="noteForm.errors.description"
                                    class="mt-1 text-xs text-red-300"
                                >
                                    {{ noteForm.errors.description }}
                                </p>
                            </div>

                            <button
                                type="button"
                                :disabled="noteForm.processing"
                                @click="submitNote"
                                class="mt-2 inline-flex h-10 w-full items-center justify-between rounded-lg bg-[#ff675c] px-4 text-sm font-medium text-white hover:bg-[#ff5145] disabled:opacity-60"
                            >
                                <span>
                                    {{
                                        noteForm.processing
                                            ? "Menyimpan..."
                                            : "Add Note"
                                    }}
                                </span>
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </template>

                    <div
                        v-else
                        class="flex min-h-[500px] text-sm sm:text-base items-center justify-center text-center text-white/35"
                    >
                        Pilih review untuk melihat detail.
                    </div>
                </aside>
            </section>
        </div>
    </AdminLayout>
</template>
