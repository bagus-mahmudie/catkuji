<script setup>
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { reactive, computed, ref, watch } from "vue";
import catkoinIcon from "@/assets/cat-koin.png";

const props = defineProps({
    products: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const showFilterPanel = ref(false);
const viewMode = ref("grid");

const showEditCategoryModal = ref(false);
const showDeleteCategoryConfirm = ref(false);
const selectedCategory = ref(null);
const editPromoLeftBgPreview = ref(null);

let searchTimeout = null;

const filterForm = reactive({
    search: props.filters.search || "",
    status: props.filters.status || "all",
    category_id: props.filters.category_id || "all",
    stock_status: props.filters.stock_status || "all",
    sort: props.filters.sort || "all",
});

const editCategoryForm = useForm({
    name: "",
    slug: "",
    description: "",
    card_style: "standard",
    promo_left_bg: null,
    remove_promo_left_bg: false,
    category_glow_color: "#F66455",
    category_glow_position: "right",
    promo_starts_at: "",
    promo_ends_at: "",
    sort_order: 0,
});

const getDefaultFilterAccordion = () => {
    if (filterForm.status !== "all") {
        return "status";
    }

    if (filterForm.stock_status !== "all") {
        return "stock";
    }

    if (filterForm.sort !== "all") {
        return "sort";
    }

    return null;
};

const activeFilterAccordion = ref(getDefaultFilterAccordion());

const openFilterAccordion = (section) => {
    activeFilterAccordion.value =
        activeFilterAccordion.value === section ? null : section;
};

const safeStatusOptions = computed(() => {
    return [
        { label: "Active", value: "published" },
        { label: "Draft", value: "draft" },
        { label: "Hidden", value: "hidden" },
        { label: "Ended", value: "ended" },
    ];
});

const safeStockStatusOptions = computed(() => {
    return [
        { label: "In Stock", value: "available" },
        { label: "Low Stock", value: "low" },
        { label: "Out of Stock", value: "sold_out" },
    ];
});

const safeSortOptions = computed(() => {
    return [
        { label: "Newest", value: "latest" },
        { label: "Oldest", value: "oldest" },
        { label: "Highest Price", value: "price_highest" },
        { label: "Lowest Price", value: "price_lowest" },
        { label: "Highest Stock", value: "stock_highest" },
        { label: "Lowest Stock", value: "stock_lowest" },
        { label: "Name A-Z", value: "name_az" },
        { label: "Name Z-A", value: "name_za" },
    ];
});

const applyFilters = () => {
    router.get(
        route("admin.products.index"),
        {
            search: filterForm.search || undefined,
            status: filterForm.status !== "all" ? filterForm.status : undefined,
            category_id:
                filterForm.category_id !== "all"
                    ? filterForm.category_id
                    : undefined,

            stock_status:
                filterForm.stock_status !== "all"
                    ? filterForm.stock_status
                    : undefined,
            sort: filterForm.sort !== "all" ? filterForm.sort : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

watch(
    () => filterForm.search,
    () => {
        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 400);
    },
);

watch(
    () => [
        filterForm.status,
        filterForm.category_id,
        filterForm.stock_status,
        filterForm.sort,
    ],
    () => {
        applyFilters();
    },
);

watch(
    () => showFilterPanel.value,
    (isOpen) => {
        if (isOpen) {
            activeFilterAccordion.value = getDefaultFilterAccordion();
        }
    },
);

const resetFilters = () => {
    clearTimeout(searchTimeout);

    filterForm.search = "";
    filterForm.status = "all";
    filterForm.category_id = "all";
    filterForm.stock_status = "all";
    filterForm.sort = "all";

    activeFilterAccordion.value = null;
    showFilterPanel.value = false;

    router.get(
        route("admin.products.index"),
        {},
        {
            preserveScroll: true,
            replace: true,
        },
    );
};

const openEditCategoryModal = () => {
    const currentCategory =
        props.categories.find(
            (category) =>
                String(category.id) === String(filterForm.category_id),
        ) || props.categories[0];

    if (!currentCategory) {
        alert("Belum ada category yang bisa diedit.");
        return;
    }

    selectedCategory.value = currentCategory;

    editCategoryForm.clearErrors();
    editCategoryForm.reset();

    editCategoryForm.name = currentCategory.name || "";
    editCategoryForm.slug = currentCategory.slug || "";
    editCategoryForm.description = currentCategory.description || "";
    editCategoryForm.card_style = currentCategory.card_style || "standard";
    editCategoryForm.promo_left_bg = null;
    editCategoryForm.remove_promo_left_bg = false;
    editCategoryForm.category_glow_color =
        currentCategory.category_glow_color || "#F66455";
    editCategoryForm.category_glow_position =
        currentCategory.category_glow_position || "right";
    editCategoryForm.promo_starts_at = currentCategory.promo_starts_at || "";
    editCategoryForm.promo_ends_at = currentCategory.promo_ends_at || "";
    editCategoryForm.sort_order = Number(currentCategory.sort_order ?? 0);

    editPromoLeftBgPreview.value = currentCategory.promo_left_bg_url || null;
    showDeleteCategoryConfirm.value = false;
    showEditCategoryModal.value = true;
};

const closeEditCategoryModal = () => {
    showEditCategoryModal.value = false;
    showDeleteCategoryConfirm.value = false;
    selectedCategory.value = null;
    editPromoLeftBgPreview.value = null;
    editCategoryForm.clearErrors();
};

const setEditCategoryPromoLeftBg = (event) => {
    const file = event.target.files?.[0];

    if (!file) return;

    editCategoryForm.promo_left_bg = file;
    editCategoryForm.remove_promo_left_bg = false;
    editPromoLeftBgPreview.value = URL.createObjectURL(file);
};

const removeEditCategoryPromoLeftBg = () => {
    editCategoryForm.promo_left_bg = null;
    editCategoryForm.remove_promo_left_bg = true;
    editPromoLeftBgPreview.value = null;
};

watch(
    () => editCategoryForm.card_style,
    (style) => {
        if (style !== "promo") {
            editCategoryForm.promo_left_bg = null;
            editCategoryForm.remove_promo_left_bg = true;
            editPromoLeftBgPreview.value = null;
            editCategoryForm.promo_starts_at = "";
            editCategoryForm.promo_ends_at = "";
        }
    },
);

const submitEditCategory = () => {
    if (!selectedCategory.value?.id) return;

    editCategoryForm.post(
        route("admin.products.categories.update", selectedCategory.value.id),
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                closeEditCategoryModal();

                router.reload({
                    only: ["categories", "products"],
                    preserveScroll: true,
                });
            },
        },
    );
};

const requestDeleteCategory = () => {
    if (!selectedCategory.value?.id) return;

    showDeleteCategoryConfirm.value = true;
};

const deleteSelectedCategory = () => {
    if (!selectedCategory.value?.id) return;

    router.delete(
        route("admin.products.categories.destroy", selectedCategory.value.id),
        {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                closeEditCategoryModal();
            },
            onError: (errors) => {
                alert(
                    errors?.message ||
                        errors?.error ||
                        "Category gagal dihapus.",
                );
            },
        },
    );
};

const toggleStatus = (value) => {
    filterForm.status = filterForm.status === value ? "all" : value;
    activeFilterAccordion.value = "status";
};

const toggleStockStatus = (value) => {
    filterForm.stock_status = filterForm.stock_status === value ? "all" : value;
    activeFilterAccordion.value = "stock";
};

const toggleSort = (value) => {
    filterForm.sort = filterForm.sort === value ? "all" : value;
    activeFilterAccordion.value = "sort";
};

const formatCoin = (value) => {
    return new Intl.NumberFormat("id-ID").format(value || 0);
};

const statusLabel = (value) => {
    return (
        {
            draft: "Draft",
            published: "Active",
            ended: "Ended",
            hidden: "Hidden",
        }[value] || value
    );
};

const statusClass = (value) => {
    return (
        {
            published: "bg-emerald-500/15 text-emerald-400",
            draft: "bg-yellow-500/15 text-yellow-300",
            ended: "bg-red-500/15 text-red-400",
            hidden: "bg-white/10 text-white/45",
        }[value] || "bg-white/10 text-white/45"
    );
};

const stockStatusLabel = (product) => {
    if (
        product.stock_status === "sold_out" ||
        Number(product.stock_left) <= 0
    ) {
        return "Sold Out";
    }

    if (product.stock_status === "low" || Number(product.stock_left) <= 10) {
        return "Low Stock";
    }

    return "Available";
};

const stockStatusClass = (product) => {
    if (
        product.stock_status === "sold_out" ||
        Number(product.stock_left) <= 0
    ) {
        return "border-red-500/30 bg-red-500/15 text-red-300";
    }

    if (product.stock_status === "low" || Number(product.stock_left) <= 10) {
        return "border-yellow-500/30 bg-yellow-500/15 text-yellow-300";
    }

    return "border-emerald-500/30 bg-emerald-500/15 text-emerald-300";
};

const deleteProduct = (product) => {
    if (!product?.id) {
        alert("Product tidak valid.");
        return;
    }

    const confirmed = confirm(
        `Hapus product "${product.name}"?\n\nProduct yang sudah memiliki transaksi sebaiknya tidak dihapus permanen.`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(route("admin.products.destroy", product.id), {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            selectedProducts.value = selectedProducts.value.filter(
                (id) => id !== product.id,
            );
        },
        onError: (errors) => {
            const message =
                errors?.message ||
                errors?.error ||
                "Product gagal dihapus. Kemungkinan product masih memiliki data transaksi, draw result, inventory, atau relasi lain.";

            alert(message);
        },
    });
};

const visitPagination = (url) => {
    if (!url) {
        return;
    }

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    });
};

const selectedProducts = ref([]);

const allProductIds = computed(() => {
    return props.products.data.map((product) => product.id);
});

const isAllSelected = computed(() => {
    return (
        allProductIds.value.length > 0 &&
        allProductIds.value.every((id) => selectedProducts.value.includes(id))
    );
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedProducts.value = [];
        return;
    }

    selectedProducts.value = [...allProductIds.value];
};

const toggleSelectProduct = (productId) => {
    if (selectedProducts.value.includes(productId)) {
        selectedProducts.value = selectedProducts.value.filter(
            (id) => id !== productId,
        );
        return;
    }

    selectedProducts.value.push(productId);
};

const tierValue = (product, key) => {
    return Number(product.tier_stock?.[key] ?? 0);
};
</script>

<template>
    <Head title="Product Live" />

    <AdminLayout title="Product" :breadcrumb="[{ label: 'Product' }]">
        <div class="min-w-0 space-y-5">
            <section
                class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
            >
                <div>
                    <h1
                        class="text-xl font-medium leading-tight sm:text-2xl lg:text-3xl"
                    >
                        Product Live
                    </h1>
                </div>

                <div class="min-w-0">
                    <!-- <div class="relative w-full md:w-[320px] xl:w-[420px]">
                        <i
                            class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-white/45"
                        ></i>

                        <input
                            v-model="filterForm.search"
                            type="search"
                            placeholder="Search..."
                            class="h-10 w-full rounded-lg  bg-white/5 pl-11 pr-4 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                        />
                    </div> -->

                    <div
                        class="flex w-full flex-nowrap items-center gap-2 overflow-x-auto pb-1 [-webkit-overflow-scrolling:touch] xl:w-auto xl:overflow-visible xl:pb-0"
                    >
                        <select
                            v-model="filterForm.category_id"
                            class="h-10 min-w-[120px] rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
                        >
                            <option class="bg-[#2b2c29]" value="all">
                                Category
                            </option>

                            <option
                                v-for="category in categories"
                                :key="category.id"
                                class="bg-[#2b2c29]"
                                :value="String(category.id)"
                            >
                                {{ category.name }}
                            </option>
                        </select>

                        <div class="relative">
                            <button
                                type="button"
                                class="inline-flex h-10 items-center rounded-lg border border-none bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white"
                                @click="showFilterPanel = !showFilterPanel"
                            >
                                <i class="bi bi-funnel"></i>
                                Filter
                            </button>

                            <div
                                v-if="showFilterPanel"
                                class="fixed left-3 right-3 top-[170px] z-50 rounded-xl bg-[#2f302d] p-4 shadow-2xl sm:absolute sm:left-auto sm:right-0 sm:top-14 sm:w-[220px]"
                            >
                                <div class="space-y-5">
                                    <div>
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between text-sm font-medium text-white"
                                            @click="
                                                openFilterAccordion('status')
                                            "
                                        >
                                            <span>Status</span>
                                            <i
                                                class="bi text-xs transition"
                                                :class="
                                                    activeFilterAccordion ===
                                                    'status'
                                                        ? 'bi-chevron-up'
                                                        : 'bi-chevron-down'
                                                "
                                            ></i>
                                        </button>

                                        <div
                                            v-show="
                                                activeFilterAccordion ===
                                                'status'
                                            "
                                            class="pl-3 mt-3 space-y-3"
                                        >
                                            <label
                                                v-for="option in safeStatusOptions"
                                                :key="option.value"
                                                class="flex cursor-pointer items-center justify-between text-sm"
                                                :class="
                                                    filterForm.status ===
                                                    option.value
                                                        ? 'text-white'
                                                        : 'text-white/35'
                                                "
                                            >
                                                <span>{{ option.label }}</span>

                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded-lg border-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                                    :checked="
                                                        filterForm.status ===
                                                        option.value
                                                    "
                                                    @change="
                                                        toggleStatus(
                                                            option.value,
                                                        )
                                                    "
                                                />
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between text-sm font-medium text-white"
                                            @click="
                                                openFilterAccordion('stock')
                                            "
                                        >
                                            <span>Stock</span>
                                            <i
                                                class="bi text-xs transition"
                                                :class="
                                                    activeFilterAccordion ===
                                                    'stock'
                                                        ? 'bi-chevron-up'
                                                        : 'bi-chevron-down'
                                                "
                                            ></i>
                                        </button>

                                        <div
                                            v-show="
                                                activeFilterAccordion ===
                                                'stock'
                                            "
                                            class="pl-3 mt-3 space-y-3"
                                        >
                                            <label
                                                v-for="option in safeStockStatusOptions"
                                                :key="option.value"
                                                class="flex cursor-pointer items-center justify-between text-sm"
                                                :class="
                                                    filterForm.stock_status ===
                                                    option.value
                                                        ? 'text-white'
                                                        : 'text-white/35'
                                                "
                                            >
                                                <span>{{ option.label }}</span>

                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded-lgborder-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                                    :checked="
                                                        filterForm.stock_status ===
                                                        option.value
                                                    "
                                                    @change="
                                                        toggleStockStatus(
                                                            option.value,
                                                        )
                                                    "
                                                />
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between text-sm font-medium text-white"
                                            @click="openFilterAccordion('sort')"
                                        >
                                            <span>Sort By</span>
                                            <i
                                                class="bi text-xs transition"
                                                :class="
                                                    activeFilterAccordion ===
                                                    'sort'
                                                        ? 'bi-chevron-up'
                                                        : 'bi-chevron-down'
                                                "
                                            ></i>
                                        </button>

                                        <div
                                            v-show="
                                                activeFilterAccordion === 'sort'
                                            "
                                            class="pl-3 mt-3 space-y-3"
                                        >
                                            <label
                                                v-for="option in safeSortOptions"
                                                :key="option.value"
                                                class="flex cursor-pointer items-center justify-between text-sm"
                                                :class="
                                                    filterForm.sort ===
                                                    option.value
                                                        ? 'text-[#ff675c]'
                                                        : 'text-white/35'
                                                "
                                            >
                                                <span>{{ option.label }}</span>

                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded-lgborder-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                                    :checked="
                                                        filterForm.sort ===
                                                        option.value
                                                    "
                                                    @change="
                                                        toggleSort(option.value)
                                                    "
                                                />
                                            </label>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="w-full rounded-lg px-4 py-2 text-sm text-white/70 bg-white/5 hover:bg-white/10 hover:text-white"
                                        @click="resetFilters"
                                    >
                                        Reset Filter
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-lg bg-white/5 px-2 sm:px-4 text-white/80 hover:bg-white/10"
                            @click="
                                viewMode = viewMode === 'grid' ? 'list' : 'grid'
                            "
                        >
                            <i
                                class="bi text-lg"
                                :class="
                                    viewMode === 'grid'
                                        ? 'bi-list-ul'
                                        : 'bi-grid-3x3-gap'
                                "
                            ></i>
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-white/5 px-2 sm:px-5 text-xs sm:text-sm font-medium text-white hover:bg-white/10"
                            @click="openEditCategoryModal"
                        >
                            <i class="bi bi-pencil-square"></i>
                            Edit Category
                        </button>

                        <Link
                            :href="route('admin.products.create')"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#ff675c] px-2 sm:px-6 text-xs sm:text-sm font-medium text-white hover:bg-[#ff5145]"
                        >
                            <i class="bi bi-plus-lg"></i>
                            Add Product
                        </Link>
                    </div>
                </div>
            </section>

            <section
                v-if="flashSuccess"
                class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-300"
            >
                {{ flashSuccess }}
            </section>

            <section
                v-if="flashError"
                class="rounded-xl border border-red-500/20 bg-red-500/10 px-5 py-4 text-sm text-red-300"
            >
                {{ flashError }}
            </section>

            <section
                v-if="viewMode === 'grid'"
                class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
            >
                <article
                    v-for="product in products.data"
                    :key="product.id"
                    class="overflow-hidden rounded-xl bg-[#393836] p-3"
                >
                    <div
                        class="relative h-[240px] overflow-hidden rounded-lg bg-white/5"
                    >
                        <img
                            v-if="product.thumbnail_url"
                            :src="product.thumbnail_url"
                            class="h-full w-full object-cover"
                            :alt="product.name"
                        />

                        <div
                            v-else
                            class="grid h-full place-items-center text-white/30"
                        >
                            No Image
                        </div>

                        <!-- <span
                            class="absolute left-3 top-3 inline-flex rounded-full border px-3 py-1 text-xs font-medium"
                            :class="stockStatusClass(product)"
                        >
                            {{ stockStatusLabel(product) }}
                        </span> -->
                    </div>

                    <div class="mt-3">
                        <span
                            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm"
                            :class="statusClass(product.status)"
                        >
                            <i class="bi bi-check-circle-fill"></i>
                            {{ statusLabel(product.status) }}
                        </span>

                        <h2
                            class="mt-3 line-clamp-1 text-xl font-medium text-white"
                        >
                            {{ product.name }}
                        </h2>

                        <p class="mt-1 text-sm text-white/75">
                            Stok : {{ product.stock_left ?? 0 }} /
                            {{ product.stock_total ?? 0 }}
                        </p>

                        <div
                            class="mt-4 rounded-lg bg-[#444341] p-3 divide-y divide-white/10"
                        >
                            <p class="text-sm text-white/80">Stok per Tier :</p>

                            <div
                                class="mt-3 grid grid-cols-3 divide-x divide-white/10"
                            >
                                <div class="mt-4">
                                    <p class="text-sm text-white/35">Gold</p>
                                    <p class="mt-1 text-2xl font-medium">
                                        {{ product.tier_stock?.gold ?? 0 }}
                                    </p>
                                </div>

                                <div class="pl-4 mt-4">
                                    <p class="text-sm text-white/35">Silver</p>
                                    <p class="mt-1 text-2xl font-medium">
                                        {{ product.tier_stock?.silver ?? 0 }}
                                    </p>
                                </div>

                                <div class="pl-4 mt-4">
                                    <p class="text-sm text-white/35">Bronze</p>
                                    <p class="mt-1 text-2xl font-medium">
                                        {{ product.tier_stock?.bronze ?? 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex items-end justify-between">
                            <div>
                                <p class="text-sm text-white/35">Price :</p>

                                <p
                                    class="mt-1 flex items-center gap-2 text-base font-medium"
                                >
                                    <img
                                        :src="catkoinIcon"
                                        alt="Catkoin"
                                        class="h-5 w-5 object-contain"
                                    />
                                    {{ formatCoin(product.price_per_draw) }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <Link
                                    :href="
                                        product.edit_url ||
                                        route('admin.products.edit', product.id)
                                    "
                                    class="grid h-11 w-11 place-items-center rounded-lg bg-white/5 text-white/70 hover:bg-white/10 hover:text-white"
                                >
                                    <i class="bi bi-pencil-square"></i>
                                </Link>

                                <button
                                    type="button"
                                    class="grid h-11 w-11 place-items-center rounded-lg bg-white/5 text-white/70 hover:bg-red-500/20 hover:text-red-300"
                                    @click.stop.prevent="deleteProduct(product)"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </article>

                <div
                    v-if="!products.data.length"
                    class="col-span-full rounded-xl border border-dashed border-white/10 p-10 text-center text-white/40"
                >
                    Belum ada product/event.
                </div>
            </section>

            <section v-else class="overflow-hidden rounded-xl bg-[#393836]">
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-0">
                        <thead>
                            <tr
                                class="border-b border-white/10 bg-white/[0.03]"
                            >
                                <th class="w-10 px-5 py-4 text-left">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded-lgborder-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                        :checked="isAllSelected"
                                        @change="toggleSelectAll"
                                    />
                                </th>

                                <th
                                    class="px-4 py-4 text-left text-sm sm:text-base font-medium uppercase tracking-[0.08em] text-white/35"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1"
                                    >
                                        Product Name
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </button>
                                </th>

                                <th
                                    class="px-4 py-4 text-center text-sm sm:text-base font-medium uppercase tracking-[0.08em] text-white/35"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1"
                                    >
                                        Stock
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </button>
                                </th>

                                <th
                                    class="px-4 py-4 text-center text-sm sm:text-base font-medium uppercase tracking-[0.08em] text-white/35"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1"
                                    >
                                        Gold
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </button>
                                </th>

                                <th
                                    class="px-4 py-4 text-center text-sm sm:text-base font-medium uppercase tracking-[0.08em] text-white/35"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1"
                                    >
                                        Silver
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </button>
                                </th>

                                <th
                                    class="px-4 py-4 text-center text-sm sm:text-base font-medium uppercase tracking-[0.08em] text-white/35"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1"
                                    >
                                        Bronze
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </button>
                                </th>

                                <th
                                    class="px-4 py-4 text-center text-sm sm:text-base font-medium uppercase tracking-[0.08em] text-white/35"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1"
                                    >
                                        Price
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </button>
                                </th>

                                <th
                                    class="px-4 py-4 text-center text-sm sm:text-base font-medium uppercase tracking-[0.08em] text-white/35"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1"
                                    >
                                        Status
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </button>
                                </th>

                                <th
                                    class="px-5 py-4 text-center text-sm sm:text-base font-medium uppercase tracking-[0.08em] text-white/35"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1"
                                    >
                                        Action
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </button>
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="product in products.data"
                                :key="product.id"
                                class="border-b border-white/10 transition hover:bg-white/[0.03]"
                            >
                                <td
                                    class="w-10 border-b border-white/10 px-5 py-4"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded-lgborder-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                        :checked="
                                            selectedProducts.includes(
                                                product.id,
                                            )
                                        "
                                        @change="
                                            toggleSelectProduct(product.id)
                                        "
                                    />
                                </td>

                                <td class="border-b border-white/10 px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-11 w-11 shrink-0 overflow-hidden rounded-md bg-white/5"
                                        >
                                            <img
                                                v-if="product.thumbnail_url"
                                                :src="product.thumbnail_url"
                                                :alt="product.name"
                                                class="h-full w-full object-cover"
                                            />

                                            <div
                                                v-else
                                                class="grid h-full place-items-center text-[10px] text-white/25"
                                            >
                                                No
                                            </div>
                                        </div>

                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm sm:text-base font-medium text-white"
                                            >
                                                {{ product.name }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td
                                    class="border-b border-white/10 px-4 py-4 text-center"
                                >
                                    <span
                                        class="text-sm sm:text-base text-white"
                                    >
                                        {{ product.stock_left ?? 0 }} /
                                        {{ product.stock_total ?? 0 }}
                                    </span>
                                </td>

                                <td
                                    class="border-b border-white/10 px-4 py-4 text-center"
                                >
                                    <span
                                        class="text-sm sm:text-base text-white"
                                    >
                                        {{ tierValue(product, "gold") }}
                                    </span>
                                </td>

                                <td
                                    class="border-b border-white/10 px-4 py-4 text-center"
                                >
                                    <span
                                        class="text-sm sm:text-base text-white"
                                    >
                                        {{ tierValue(product, "silver") }}
                                    </span>
                                </td>

                                <td
                                    class="border-b border-white/10 px-4 py-4 text-center"
                                >
                                    <span
                                        class="text-sm sm:text-base text-white"
                                    >
                                        {{ tierValue(product, "bronze") }}
                                    </span>
                                </td>

                                <td
                                    class="border-b border-white/10 px-4 py-4 text-center"
                                >
                                    <div
                                        class="inline-flex items-center justify-center gap-2"
                                    >
                                        <img
                                            :src="catkoinIcon"
                                            alt="Catkoin"
                                            class="h-5 w-5 object-contain"
                                        />

                                        <span
                                            class="text-sm sm:text-base font-medium text-white"
                                        >
                                            {{
                                                formatCoin(
                                                    product.price_per_draw,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </td>

                                <td
                                    class="border-b border-white/10 px-4 py-4 text-center"
                                >
                                    <span
                                        class="inline-flex min-w-[92px] items-center justify-center gap-2 rounded-full px-3 py-1 text-sm sm:text-base"
                                        :class="statusClass(product.status)"
                                    >
                                        <i
                                            class="bi text-sm sm:text-base"
                                            :class="{
                                                'bi-check-circle-fill':
                                                    product.status ===
                                                    'published',
                                                'bi-circle-fill':
                                                    product.status === 'draft',
                                                'bi-x-circle-fill':
                                                    product.status === 'ended',
                                                'bi-eye-slash-fill':
                                                    product.status === 'hidden',
                                            }"
                                        ></i>

                                        {{ statusLabel(product.status) }}
                                    </span>
                                </td>

                                <td
                                    class="border-b border-white/10 px-5 py-4 text-center"
                                >
                                    <div
                                        class="inline-flex items-center justify-center gap-2"
                                    >
                                        <Link
                                            :href="
                                                product.edit_url ||
                                                route(
                                                    'admin.products.edit',
                                                    product.id,
                                                )
                                            "
                                            class="grid h-10 w-10 place-items-center rounded-lg bg-white/5 text-white/70 hover:bg-white/10 hover:text-white"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                        </Link>

                                        <button
                                            type="button"
                                            class="grid h-10 w-10 place-items-center rounded-lg bg-white/5 text-white/70 hover:bg-red-500/20 hover:text-red-300"
                                            @click.stop.prevent="
                                                deleteProduct(product)
                                            "
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!products.data.length">
                                <td
                                    colspan="9"
                                    class="px-4 py-10 text-center text-white/40"
                                >
                                    Belum ada product/event.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-if="products.links?.length > 3"
                class="flex flex-wrap items-center justify-between gap-4"
            >
                <p class="text-sm text-white/35">
                    Menampilkan {{ products.from }} - {{ products.to }} dari
                    {{ products.total }} product
                </p>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="link in products.links"
                        :key="link.label"
                        type="button"
                        :disabled="!link.url"
                        @click="visitPagination(link.url)"
                        class="rounded-lg px-3 py-2 text-sm"
                        :class="[
                            link.active
                                ? 'bg-[#ff675c] text-white'
                                : 'bg-white/5 text-white/60 hover:bg-white/10',
                            !link.url ? 'cursor-not-allowed opacity-40' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="showEditCategoryModal"
                class="fixed inset-0 z-[100] overflow-y-auto bg-black/70 px-4 py-8 backdrop-blur-sm"
                @click="closeEditCategoryModal"
            >
                <div
                    class="mx-auto w-full max-w-[620px] rounded-2xl bg-[#252623] p-4 shadow-2xl sm:p-6"
                    @click.stop
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-white">
                                Edit Category
                            </h2>
                            <p class="mt-1 text-sm text-white/45">
                                Ubah tampilan category, urutan homepage, dan
                                glow.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="grid h-10 w-10 place-items-center rounded-lg bg-white/5 text-white/70 hover:bg-white/10"
                            @click="closeEditCategoryModal"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="mb-2 block text-sm text-white/70">
                                Pilih Category
                            </label>

                            <select
                                :value="selectedCategory?.id || ''"
                                class="w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                @change="
                                    selectedCategory =
                                        categories.find(
                                            (category) =>
                                                String(category.id) ===
                                                String($event.target.value),
                                        ) || null;

                                    if (selectedCategory) {
                                        editCategoryForm.name =
                                            selectedCategory.name || '';
                                        editCategoryForm.slug =
                                            selectedCategory.slug || '';
                                        editCategoryForm.description =
                                            selectedCategory.description || '';
                                        editCategoryForm.card_style =
                                            selectedCategory.card_style ||
                                            'standard';
                                        editCategoryForm.promo_left_bg = null;
                                        editCategoryForm.remove_promo_left_bg = false;
                                        editCategoryForm.category_glow_color =
                                            selectedCategory.category_glow_color ||
                                            '#F66455';
                                        editCategoryForm.category_glow_position =
                                            selectedCategory.category_glow_position ||
                                            'right';
                                        editCategoryForm.sort_order = Number(
                                            selectedCategory.sort_order ?? 0,
                                        );
                                        editCategoryForm.promo_starts_at =
                                            selectedCategory.promo_starts_at ||
                                            '';
                                        editCategoryForm.promo_ends_at =
                                            selectedCategory.promo_ends_at ||
                                            '';
                                        editPromoLeftBgPreview =
                                            selectedCategory.promo_left_bg_url ||
                                            null;
                                        showDeleteCategoryConfirm = false;
                                        editCategoryForm.clearErrors();
                                    }
                                "
                            >
                                <option
                                    v-for="category in categories"
                                    :key="category.id"
                                    class="bg-[#2b2c29]"
                                    :value="category.id"
                                >
                                    {{ category.name }} —
                                    {{ category.events_count ?? 0 }}
                                    event
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm text-white/70">
                                    Nama Category
                                </label>

                                <input
                                    v-model="editCategoryForm.name"
                                    type="text"
                                    class="w-full rounded-lg bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                    placeholder="Nama category"
                                />

                                <p
                                    v-if="editCategoryForm.errors.name"
                                    class="mt-2 text-sm text-red-400"
                                >
                                    {{ editCategoryForm.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm text-white/70">
                                    Slug
                                </label>

                                <input
                                    v-model="editCategoryForm.slug"
                                    type="text"
                                    class="w-full rounded-lg bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                    placeholder="Slug category"
                                />

                                <p
                                    v-if="editCategoryForm.errors.slug"
                                    class="mt-2 text-sm text-red-400"
                                >
                                    {{ editCategoryForm.errors.slug }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm text-white/70">
                                Deskripsi
                            </label>

                            <textarea
                                v-model="editCategoryForm.description"
                                rows="3"
                                class="w-full rounded-lg bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                placeholder="Deskripsi category"
                            ></textarea>

                            <p
                                v-if="editCategoryForm.errors.description"
                                class="mt-2 text-sm text-red-400"
                            >
                                {{ editCategoryForm.errors.description }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm text-white/70">
                                    Tampilan Card Homepage
                                </label>

                                <select
                                    v-model="editCategoryForm.card_style"
                                    class="w-full rounded-lg bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                >
                                    <option
                                        class="bg-[#2b2c29]"
                                        value="standard"
                                    >
                                        Standard Event Card
                                    </option>
                                    <option class="bg-[#2b2c29]" value="promo">
                                        Promo Event Card
                                    </option>
                                </select>

                                <p
                                    v-if="editCategoryForm.errors.card_style"
                                    class="mt-2 text-sm text-red-400"
                                >
                                    {{ editCategoryForm.errors.card_style }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm text-white/70">
                                    Urutan Section di Homepage
                                </label>

                                <input
                                    v-model="editCategoryForm.sort_order"
                                    type="number"
                                    min="1"
                                    class="w-full rounded-lg bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                    placeholder="0"
                                />

                                <p class="mt-2 text-xs text-white/35">
                                    Angka lebih kecil tampil lebih atas.
                                </p>

                                <p
                                    v-if="editCategoryForm.errors.sort_order"
                                    class="mt-2 text-sm text-red-400"
                                >
                                    {{ editCategoryForm.errors.sort_order }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="editCategoryForm.card_style === 'promo'"
                            class="rounded-lg border border-white/10 bg-white/[0.04] p-3"
                        >
                            <label class="mb-2 block text-sm text-white/70">
                                Promo Left Background
                            </label>

                            <div
                                class="grid h-[140px] place-items-center overflow-hidden rounded-lg bg-black/20"
                            >
                                <img
                                    v-if="editPromoLeftBgPreview"
                                    :src="editPromoLeftBgPreview"
                                    alt="Promo Left Background Preview"
                                    class="h-full w-full object-contain"
                                />

                                <div v-else class="px-4 text-center">
                                    <i
                                        class="bi bi-image text-3xl text-white/40"
                                    ></i>
                                    <p class="mt-2 text-xs text-white/50">
                                        Upload gambar dekorasi kiri promo
                                    </p>
                                    <p class="mt-1 text-[11px] text-white/30">
                                        PNG, JPG, WEBP maks. 5MB
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <label
                                    class="flex cursor-pointer items-center justify-center rounded-lg bg-white/5 px-4 py-3 text-sm font-medium text-white hover:bg-white/10"
                                >
                                    Pilih Gambar Promo
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="setEditCategoryPromoLeftBg"
                                    />
                                </label>

                                <button
                                    type="button"
                                    class="rounded-lg bg-white/5 px-4 py-3 text-sm font-medium text-white/70 hover:bg-white/10"
                                    @click="removeEditCategoryPromoLeftBg"
                                >
                                    Hapus Gambar
                                </button>
                            </div>

                            <p
                                v-if="editCategoryForm.errors.promo_left_bg"
                                class="mt-2 text-sm text-red-400"
                            >
                                {{ editCategoryForm.errors.promo_left_bg }}
                            </p>
                        </div>

                        <div
                            v-if="editCategoryForm.card_style === 'promo'"
                            class="grid gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <label class="mb-2 block text-sm text-white/70">
                                    Promo Start Date
                                </label>

                                <input
                                    v-model="editCategoryForm.promo_starts_at"
                                    type="datetime-local"
                                    class="w-full rounded-lg bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                />

                                <p
                                    v-if="
                                        editCategoryForm.errors.promo_starts_at
                                    "
                                    class="mt-2 text-sm text-red-400"
                                >
                                    {{
                                        editCategoryForm.errors.promo_starts_at
                                    }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm text-white/70">
                                    Promo End Date
                                </label>

                                <input
                                    v-model="editCategoryForm.promo_ends_at"
                                    type="datetime-local"
                                    class="w-full rounded-lg bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                />

                                <p
                                    v-if="editCategoryForm.errors.promo_ends_at"
                                    class="mt-2 text-sm text-red-400"
                                >
                                    {{ editCategoryForm.errors.promo_ends_at }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="rounded-lg border border-white/10 bg-white/[0.04] p-3"
                        >
                            <label class="mb-2 block text-sm text-white/70">
                                Warna Glow Category
                            </label>

                            <div class="flex items-center gap-3">
                                <input
                                    v-model="
                                        editCategoryForm.category_glow_color
                                    "
                                    type="color"
                                    class="h-12 w-14 cursor-pointer rounded-lg border border-white/10 bg-transparent p-1"
                                />

                                <input
                                    v-model="
                                        editCategoryForm.category_glow_color
                                    "
                                    type="text"
                                    maxlength="7"
                                    placeholder="#F66455"
                                    class="h-12 flex-1 rounded-lg border border-white/10 bg-white/5 px-4 text-sm uppercase text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                />
                            </div>

                            <div
                                class="mt-3 h-10 rounded-lg border border-white/10"
                                :style="{
                                    background: `linear-gradient(90deg, ${editCategoryForm.category_glow_color}, transparent)`,
                                    boxShadow: `0 0 34px ${editCategoryForm.category_glow_color}66`,
                                }"
                            ></div>

                            <p
                                v-if="
                                    editCategoryForm.errors.category_glow_color
                                "
                                class="mt-2 text-sm text-red-400"
                            >
                                {{
                                    editCategoryForm.errors.category_glow_color
                                }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg border border-white/10 bg-white/[0.04] p-3"
                        >
                            <label class="mb-2 block text-sm text-white/70">
                                Posisi Glow Bawah Section
                            </label>

                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    class="flex cursor-pointer items-center justify-center rounded-lg border px-4 py-3 text-sm font-medium transition"
                                    :class="
                                        editCategoryForm.category_glow_position ===
                                        'left'
                                            ? 'border-[#ff675c] bg-[#ff675c]/15 text-white'
                                            : 'border-white/10 bg-white/5 text-white/60 hover:bg-white/10'
                                    "
                                >
                                    <input
                                        v-model="
                                            editCategoryForm.category_glow_position
                                        "
                                        type="radio"
                                        value="left"
                                        class="hidden"
                                    />
                                    Kiri Bawah
                                </label>

                                <label
                                    class="flex cursor-pointer items-center justify-center rounded-lg border px-4 py-3 text-sm font-medium transition"
                                    :class="
                                        editCategoryForm.category_glow_position ===
                                        'right'
                                            ? 'border-[#ff675c] bg-[#ff675c]/15 text-white'
                                            : 'border-white/10 bg-white/5 text-white/60 hover:bg-white/10'
                                    "
                                >
                                    <input
                                        v-model="
                                            editCategoryForm.category_glow_position
                                        "
                                        type="radio"
                                        value="right"
                                        class="hidden"
                                    />
                                    Kanan Bawah
                                </label>
                            </div>

                            <p
                                v-if="
                                    editCategoryForm.errors
                                        .category_glow_position
                                "
                                class="mt-2 text-sm text-red-400"
                            >
                                {{
                                    editCategoryForm.errors
                                        .category_glow_position
                                }}
                            </p>
                        </div>

                        <div
                            v-if="showDeleteCategoryConfirm"
                            class="rounded-xl border border-red-500/30 bg-red-500/10 p-4"
                        >
                            <h3 class="text-base font-semibold text-red-300">
                                Yakin arsipkan category?
                            </h3>

                            <p class="mt-2 text-sm leading-6 text-red-100/80">
                                Anda yakin ingin mengarsipkan category
                                <strong>{{ selectedCategory?.name }}</strong
                                >? Category akan disembunyikan dari homepage dan
                                filter category, tetapi event dan prize di
                                dalamnya tetap aman di database.
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-600"
                                    @click="deleteSelectedCategory"
                                >
                                    Ya, Arsipkan Category
                                </button>

                                <button
                                    type="button"
                                    class="rounded-lg bg-white/5 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10"
                                    @click="showDeleteCategoryConfirm = false"
                                >
                                    Batal
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <button
                            type="button"
                            class="rounded-lg border border-red-500/30 bg-red-500/10 px-5 py-3 text-sm font-semibold text-red-300 hover:bg-red-500/15"
                            @click="requestDeleteCategory"
                        >
                            Arsipkan Category
                        </button>

                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="rounded-lg bg-white/5 px-5 py-3 text-sm font-semibold text-white/70 hover:bg-white/10"
                                @click="closeEditCategoryModal"
                            >
                                Batal
                            </button>

                            <button
                                type="button"
                                class="rounded-lg bg-[#ff675c] px-5 py-3 text-sm font-semibold text-white hover:bg-[#ff5145] disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="editCategoryForm.processing"
                                @click="submitEditCategory"
                            >
                                Simpan Category
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
