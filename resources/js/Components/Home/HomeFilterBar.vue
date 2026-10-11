<script setup>
import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    selectedCategory: {
        type: [String, Number, null],
        default: "",
    },
    search: {
        type: String,
        default: "",
    },
});

const selected = ref(
    props.selectedCategory ? String(props.selectedCategory) : "",
);
const keyword = ref(props.search ?? "");

const categoryOptions = computed(() => {
    return [
        {
            id: "",
            name: "Semua Kategori",
        },
        ...props.categories.map((category) => ({
            id: String(category.id),
            name: category.name,
        })),
    ];
});

const submitFilter = () => {
    router.get(
        route("home"),
        {
            category: selected.value || undefined,
            search: keyword.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const onCategoryChange = () => {
    submitFilter();
};
</script>

<template>
    <section class="mt-5 sm:mt-8">
        <div
            class="flex h-11 items-center rounded-lg bg-[#101521] px-3 shadow-[0_10px_30px_rgba(0,0,0,0.22)] md:h-auto md:rounded-xl md:px-3 md:py-2"
        >
            <!-- CATEGORY -->
            <div class="flex w-[125px] shrink-0 items-center md:w-[200px]">
                <div class="relative w-full">
                    <select
                        v-model="selected"
                        class="h-11 w-full appearance-none bg-transparent pl-0 sm:pl-5 pr-6 text-xs font-medium text-white outline-none border-none focus:ring-0 md:h-14 md:pr-10 md:text-base"
                        style="background-image: none"
                        @change="onCategoryChange"
                    >
                        <option
                            v-for="option in categoryOptions"
                            :key="option.id || 'all'"
                            :value="option.id"
                            class="bg-[#101521] text-white"
                        >
                            {{ option.name }}
                        </option>
                    </select>

                    <div
                        class="pointer-events-none absolute inset-y-0 right-0 flex items-center text-white/60 md:right-3"
                    >
                        <i
                            class="bi bi-chevron-down text-[10px] md:text-base"
                        />
                    </div>
                </div>
            </div>

            <!-- DIVIDER -->
            <div class="mx-3 h-5 w-px bg-white/10 md:mx-5 md:h-8"></div>

            <!-- SEARCH -->
            <form
                class="flex min-w-0 flex-1 items-center"
                @submit.prevent="submitFilter"
            >
                <div class="flex min-w-0 flex-1 items-center gap-2 md:gap-3">
                    <input
                        v-model="keyword"
                        type="text"
                        placeholder="Cari Koleksi Favoritmu"
                        class="h-11 min-w-0 flex-1 bg-transparent text-xs text-white placeholder:text-white/35 outline-none border-none ring-0 focus:outline-none focus:ring-0 md:h-14 md:text-base"
                    />

                    <button
                        type="submit"
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white/60 transition hover:bg-white/5 hover:text-white md:h-11 md:w-11 md:text-white/75"
                        aria-label="Cari"
                    >
                        <i class="bi bi-search text-xs md:text-base" />
                    </button>
                </div>
            </form>
        </div>
    </section>
</template>
