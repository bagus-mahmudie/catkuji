<script setup>
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { computed, reactive, ref, watch } from "vue";
import { Carousel, Slide } from "vue3-carousel";
import "vue3-carousel/dist/carousel.css";

const props = defineProps({
    banners: {
        type: Object,
        required: true,
    },
    previewBanners: {
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

const modalOpen = ref(false);
const modalMode = ref("create");
const editingBanner = ref(null);
const selectedImagePreview = ref(null);
const currentPreviewIndex = ref(0);

let filterTimeout = null;

const filterForm = reactive({
    search: props.filters.search || "",
    status: props.filters.status || "active",
    start_date: props.filters.start_date || "",
    end_date: props.filters.end_date || "",
});

const form = useForm({
    title: "",
    image: null,
    url: "",
    alt_text: "",
    description: "",
    start_at: "",
    end_at: "",
    is_active: 1,
    sort_order: 0,
});

const activePreviewBanners = computed(() => {
    if (props.previewBanners?.length) {
        return props.previewBanners;
    }

    const data = props.banners?.data || [];

    return data.slice(0, 5);
});

const currentPreview = computed(() => {
    return activePreviewBanners.value[currentPreviewIndex.value] || null;
});

const pageTitle = computed(() => {
    return modalMode.value === "create" ? "Tambah Banner" : "Edit Banner";
});

const submitLabel = computed(() => {
    return modalMode.value === "create" ? "Upload" : "Upload";
});

const applyFilters = () => {
    router.get(
        route("admin.marketing.banners.index"),
        {
            search: filterForm.search || undefined,
            status: filterForm.status !== "all" ? filterForm.status : undefined,
            start_date: filterForm.start_date || undefined,
            end_date: filterForm.end_date || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

watch(
    () => filterForm.search,
    () => {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(applyFilters, 350);
    },
);

watch(
    () => [filterForm.status, filterForm.start_date, filterForm.end_date],
    () => applyFilters(),
);

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.title = "";
    form.image = null;
    form.url = "";
    form.alt_text = "";
    form.description = "";
    form.start_at = "";
    form.end_at = "";
    form.is_active = 1;
    form.sort_order = 0;
    selectedImagePreview.value = null;
    editingBanner.value = null;
};

const openCreateModal = () => {
    resetForm();
    modalMode.value = "create";
    modalOpen.value = true;
};

const openEditModal = (banner) => {
    resetForm();

    modalMode.value = "edit";
    editingBanner.value = banner;

    form.title = banner.title || "";
    form.url = banner.url || "";
    form.alt_text = banner.alt_text || "";
    form.description = banner.description || "";
    form.start_at = banner.start_at || "";
    form.end_at = banner.end_at || "";
    form.is_active = banner.is_active ? 1 : 0;
    form.sort_order = banner.sort_order || 0;

    selectedImagePreview.value = banner.image_url || null;
    modalOpen.value = true;
};

const closeModal = () => {
    if (form.processing) {
        return;
    }

    modalOpen.value = false;
    resetForm();
};

const onImageChange = (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    form.image = file;

    const reader = new FileReader();

    reader.onload = (e) => {
        selectedImagePreview.value = e.target.result;
    };

    reader.readAsDataURL(file);
};

const onDropImage = (event) => {
    const file = event.dataTransfer.files?.[0];

    if (!file) {
        return;
    }

    form.image = file;

    const reader = new FileReader();

    reader.onload = (e) => {
        selectedImagePreview.value = e.target.result;
    };

    reader.readAsDataURL(file);
};

const submit = () => {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => closeModal(),
    };

    if (modalMode.value === "create") {
        form.post(route("admin.marketing.banners.store"), options);
        return;
    }

    form.transform((data) => {
        const payload = { ...data };

        if (!payload.image) {
            delete payload.image;
        }

        return payload;
    }).post(
        route(
            "admin.marketing.banners.update.multipart",
            editingBanner.value.id,
        ),
        {
            ...options,
            onFinish: () => form.transform((data) => data),
        },
    );
};

const deleteBanner = (banner) => {
    const confirmed = confirm(
        `Hapus banner "${banner.title}" secara permanen?`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(route("admin.marketing.banners.destroy", banner.id), {
        preserveScroll: true,
    });
};

const exportCsv = () => {
    window.location.href = route("admin.marketing.banners.export");
};

const resetFilters = () => {
    filterForm.search = "";
    filterForm.status = "active";
    filterForm.start_date = "";
    filterForm.end_date = "";

    router.get(
        route("admin.marketing.banners.index"),
        {},
        {
            preserveScroll: true,
            replace: true,
        },
    );
};

const formatDateRange = computed(() => {
    if (filterForm.start_date && filterForm.end_date) {
        return `${formatDate(filterForm.start_date)} - ${formatDate(filterForm.end_date)}`;
    }

    if (filterForm.start_date) {
        return `Mulai ${formatDate(filterForm.start_date)}`;
    }

    if (filterForm.end_date) {
        return `Sampai ${formatDate(filterForm.end_date)}`;
    }

    return "Semua Tanggal";
});

const formatDate = (value) => {
    if (!value) {
        return "";
    }

    return new Intl.DateTimeFormat("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(new Date(value));
};

const goToPreview = (index) => {
    currentPreviewIndex.value = index;
};
</script>

<template>
    <Head title="Banner Promo" />

    <AdminLayout
        title="Create New Promotion"
        :breadcrumb="[{ label: 'Marketing' }, { label: 'Banner' }]"
    >
        <div class="min-w-0 space-y-5">
            <div
                v-if="flashSuccess"
                class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300"
            >
                {{ flashSuccess }}
            </div>

            <div
                v-if="flashError"
                class="rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300"
            >
                {{ flashError }}
            </div>

            <section
                class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
            >
                <div>
                    <h1
                        class="text-xl font-medium leading-tight sm:text-2xl lg:text-3xl"
                    >
                        Create New Promotion
                    </h1>
                </div>
            </section>

            <section class="rounded-xl bg-[#1d1e1c]">
                <div class="flex items-center justify-between px-4 py-3">
                    <h2 class="text-base font-medium text-white">
                        Promotion Image Preview
                    </h2>

                    <i class="bi bi-info-circle text-white/35"></i>
                </div>

                <div class="rounded-xl bg-[#343531] p-2 sm:p-3">
                    <div
                        v-if="activePreviewBanners.length"
                        class="relative overflow-hidden rounded-xl bg-[#2d2e2b]"
                    >
                        <Carousel
                            v-model="currentPreviewIndex"
                            :items-to-show="1"
                            :wrap-around="true"
                            :autoplay="4500"
                            :pause-autoplay-on-hover="true"
                            :transition="600"
                            class="admin-preview-carousel w-full"
                        >
                            <Slide
                                v-for="banner in activePreviewBanners"
                                :key="banner.id"
                                class="admin-preview-slide"
                            >
                                <a
                                    :href="banner.url || '#'"
                                    class="block w-full"
                                >
                                    <div
                                        class="relative h-[130px] w-full overflow-hidden rounded-xl bg-slate-900 md:h-[320px] lg:h-[500px]"
                                    >
                                        <img
                                            :src="banner.image_url"
                                            :alt="
                                                banner.alt_text ||
                                                banner.title ||
                                                'Promotion Banner'
                                            "
                                            class="block h-full w-full max-w-none object-cover"
                                            draggable="false"
                                        />
                                    </div>
                                </a>
                            </Slide>
                        </Carousel>

                        <div
                            v-if="activePreviewBanners.length > 1"
                            class="absolute bottom-4 right-5 z-20 flex items-center gap-2"
                        >
                            <button
                                v-for="(banner, index) in activePreviewBanners"
                                :key="banner.id"
                                type="button"
                                @click="goToPreview(index)"
                                class="rounded-full transition-all duration-200"
                                :aria-label="`Pindah ke preview banner ${index + 1}`"
                                :class="
                                    currentPreviewIndex === index
                                        ? 'h-[7px] w-[45px] bg-[#ff6258]'
                                        : 'h-[8px] w-[8px] bg-white'
                                "
                            ></button>
                        </div>
                    </div>

                    <div
                        v-else
                        class="grid h-[220px] place-items-center rounded-xl bg-[#2d2e2b] text-white/40 md:h-[320px] lg:h-[365px]"
                    >
                        Belum ada banner aktif
                    </div>
                </div>
            </section>

            <section>
                <div
                    class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <h2 class="text-xl font-medium leading-tight sm:text-2xl">
                        Active promotions
                    </h2>

                    <div
                        class="-mx-3 flex w-[calc(100%+1.5rem)] flex-nowrap items-center gap-2 overflow-x-auto px-3 pb-1 [-webkit-overflow-scrolling:touch] sm:mx-0 sm:w-auto sm:overflow-visible sm:px-0 sm:pb-0 lg:justify-end"
                    >
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <input
                                    v-model="filterForm.start_date"
                                    type="date"
                                    class="h-10 w-[120px] sm:w-[160px] rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
                                />
                            </div>

                            <span class="text-white/35">-</span>

                            <div class="relative">
                                <input
                                    v-model="filterForm.end_date"
                                    type="date"
                                    class="h-10 w-[120px] sm:w-[160px] rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
                                />
                            </div>
                        </div>

                        <select
                            v-model="filterForm.status"
                            class="h-10 rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
                        >
                            <option class="bg-[#2d2e2b]" value="all">
                                Semua
                            </option>
                            <option class="bg-[#2d2e2b]" value="active">
                                Active
                            </option>
                            <option class="bg-[#2d2e2b]" value="inactive">
                                Inactive
                            </option>
                        </select>

                        <button
                            type="button"
                            @click="openCreateModal"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#ff6258] px-4 text-xs sm:text-sm font-medium text-white transition hover:bg-[#ff766d]"
                        >
                            <span>+</span>
                            <span class="hidden sm:inline">Tambah Banner</span>
                            <span class="sm:hidden">Tambah</span>
                        </button>
                    </div>
                </div>

                <div class="mb-4 flex flex-col gap-3 md:flex-row">
                    <div class="relative flex-1">
                        <i
                            class="bi bi-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-white/45"
                        ></i>

                        <input
                            v-model="filterForm.search"
                            type="search"
                            placeholder="Cari banner promo..."
                            class="h-10 w-full rounded-lg border-none bg-white/5 pl-10 pr-4 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                        />
                    </div>

                    <button
                        type="button"
                        @click="resetFilters"
                        class="h-10 rounded-lg bg-white/5 px-4 text-sm text-white/75 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-[#ff675c]"
                    >
                        Reset Filter
                    </button>
                </div>

                <div
                    v-if="banners.data.length"
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <article
                        v-for="banner in banners.data"
                        :key="banner.id"
                        class="overflow-hidden rounded-xl bg-[#343532]"
                    >
                        <div class="relative p-3 pb-0">
                            <img
                                :src="banner.image_url"
                                :alt="banner.alt_text || banner.title"
                                class="h-[130px] sm:h-[180px] w-full rounded-lg object-cover"
                            />

                            <span
                                class="absolute left-6 top-5 inline-flex items-center gap-2 rounded-full bg-[#3f4840] px-4 py-1 text-sm"
                                :class="
                                    banner.is_active
                                        ? 'text-emerald-400'
                                        : 'text-white/45'
                                "
                            >
                                <i class="bi bi-check-circle-fill text-xs"></i>
                                {{ banner.status_label }}
                            </span>
                        </div>

                        <div class="p-3">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-base font-medium text-white">
                                    {{ banner.title }}
                                </h3>

                                <p class="shrink-0 text-sm text-white/85">
                                    {{ banner.date_label }}
                                </p>
                            </div>

                            <p
                                class="mt-2 line-clamp-2 min-h-[44px] text-sm leading-6 text-white/35"
                            >
                                {{
                                    banner.description ||
                                    "Lorem ipsum dolor sit amet consectetur. Nam a mi orci faucibus. Venenatis posuere"
                                }}
                            </p>

                            <div class="mt-4 border-t border-white/5 pt-4">
                                <button
                                    type="button"
                                    @click="openEditModal(banner)"
                                    class="flex h-10 w-full items-center justify-between rounded-lg bg-white/5 px-4 text-sm text-white transition hover:bg-white/10"
                                >
                                    <span>Edit Banner</span>
                                    <i class="bi bi-pencil text-base"></i>
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-dashed border-white/10 bg-white/[0.03] px-6 py-14 text-center"
                >
                    <p class="text-lg text-white">Belum ada banner promo</p>
                    <p class="mt-2 text-sm text-white/45">
                        Tambahkan banner baru untuk ditampilkan di homepage.
                    </p>
                </div>

                <div
                    v-if="banners.links?.length > 3"
                    class="mt-6 flex flex-wrap justify-center gap-2"
                >
                    <button
                        v-for="link in banners.links"
                        :key="link.label"
                        type="button"
                        :disabled="!link.url"
                        @click="link.url && router.visit(link.url)"
                        class="min-w-10 rounded-lg px-3 py-2 text-sm"
                        :class="
                            link.active
                                ? 'bg-[#ff6258] text-white'
                                : link.url
                                  ? 'bg-white/5 text-white/70 hover:bg-white/10'
                                  : 'cursor-not-allowed bg-white/5 text-white/25'
                        "
                        v-html="link.label"
                    />
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="modalOpen"
                class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto bg-black/55 px-4 py-20 backdrop-blur-[1px]"
                @click.self="closeModal"
            >
                <div
                    class="w-full max-w-[415px] rounded-lg bg-[#353633] p-3 shadow-2xl"
                >
                    <div
                        v-if="!selectedImagePreview"
                        class="rounded-lg bg-[#383936] p-3"
                        @dragover.prevent
                        @drop.prevent="onDropImage"
                    >
                        <label
                            class="flex min-h-[180px] cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-white/10 text-center"
                        >
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="onImageChange"
                            />

                            <i
                                class="bi bi-image my-4 text-4xl text-white/80"
                            ></i>

                            <p class="text-sm text-white">
                                Upload file atau seret ke sini
                            </p>
                            <p class="mt-2 text-sm text-white/35">
                                Format JPEG, PNG(maks. 50 MB)
                            </p>

                            <span
                                class="mt-7 flex h-10 w-full max-w-[365px] items-center justify-center rounded-lg bg-white/5 text-sm text-white"
                            >
                                Pilih File
                            </span>
                        </label>
                    </div>

                    <div v-else class="relative">
                        <img
                            :src="selectedImagePreview"
                            alt="Preview banner"
                            class="h-[126px] w-full rounded-lg object-cover"
                        />

                        <label
                            class="absolute bottom-2 right-2 cursor-pointer rounded bg-black/60 px-3 py-1 text-xs text-white"
                        >
                            Ganti
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="onImageChange"
                            />
                        </label>
                    </div>

                    <p
                        v-if="form.errors.image"
                        class="mt-2 text-xs text-red-300"
                    >
                        {{ form.errors.image }}
                    </p>

                    <div class="mt-3 sm:mt-5 space-y-4">
                        <div>
                            <label class="text-sm text-white">
                                Promotion Name
                            </label>
                            <input
                                v-model="form.title"
                                type="text"
                                placeholder='Contoh: Promo Sale 50%"'
                                class="mt-1 h-40px w-full rounded-lg border-0 bg-white/5 px-3 py-3 text-sm text-white placeholder:text-white/35 focus:ring-0"
                            />
                            <p
                                v-if="form.errors.title"
                                class="mt-1 text-xs text-red-300"
                            >
                                {{ form.errors.title }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm text-white">
                                    Start Date
                                </label>
                                <div
                                    class="mt-1 flex h-10 items-center gap-2 rounded-lg bg-white/5 px-3"
                                >
                                    <input
                                        v-model="form.start_at"
                                        type="date"
                                        class="w-full border-0 bg-transparent p-0 text-sm text-white focus:ring-0"
                                    />
                                </div>
                                <p
                                    v-if="form.errors.start_at"
                                    class="mt-1 text-xs text-red-300"
                                >
                                    {{ form.errors.start_at }}
                                </p>
                            </div>

                            <div>
                                <label class="text-sm text-white">
                                    End Date
                                </label>
                                <div
                                    class="mt-1 flex h-10 items-center gap-2 rounded-lg bg-white/5 px-3"
                                >
                                    <input
                                        v-model="form.end_at"
                                        type="date"
                                        class="w-full border-0 bg-transparent p-0 text-sm text-white focus:ring-0"
                                    />
                                </div>
                                <p
                                    v-if="form.errors.end_at"
                                    class="mt-1 text-xs text-red-300"
                                >
                                    {{ form.errors.end_at }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm text-white">Alt Text</label>
                            <input
                                v-model="form.alt_text"
                                type="text"
                                placeholder="Contoh : cat-kuji"
                                class="mt-1 w-full rounded-lg border-0 bg-white/5 px-3 py-3 text-sm text-white placeholder:text-white/35 focus:ring-0"
                            />
                        </div>

                        <div>
                            <label class="text-sm text-white">
                                Description of promo
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="5"
                                placeholder="Cth: Blok, No. Unit, Patokan"
                                class="mt-1 w-full resize-none rounded-lg border-0 bg-white/5 px-3 py-3 text-sm text-white placeholder:text-white/35 focus:ring-0"
                            ></textarea>
                        </div>

                        <div>
                            <label class="text-sm text-white">URL</label>
                            <input
                                v-model="form.url"
                                type="text"
                                placeholder="Opsional: https://..."
                                class="mt-1 w-full rounded-lg border-0 bg-white/5 px-3 py-3 text-sm text-white placeholder:text-white/35 focus:ring-0"
                            />
                        </div>

                        <div>
                            <label class="text-sm text-white">Status</label>
                            <select
                                v-model="form.is_active"
                                class="mt-1 h-12 w-full rounded-lg border-0 bg-white/5 px-3 text-sm text-white focus:ring-0"
                            >
                                <option class="bg-[#353633]" :value="1">
                                    Aktif
                                </option>
                                <option class="bg-[#353633]" :value="0">
                                    Tidak Aktif
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm text-white">
                                Sort Order
                            </label>
                            <input
                                v-model="form.sort_order"
                                type="number"
                                min="0"
                                class="mt-1 w-full rounded-lg border-0 bg-white/5 px-3 py-3 text-sm text-white placeholder:text-white/35 focus:ring-0"
                            />
                        </div>

                        <div class="border-t border-white/10 pt-5">
                            <button
                                type="button"
                                :disabled="form.processing"
                                @click="submit"
                                class="flex h-10 w-full items-center justify-center gap-3 rounded-lg bg-[#ff6258] text-sm font-medium text-white transition hover:bg-[#ff766d] disabled:opacity-60"
                            >
                                <i class="bi bi-upload"></i>
                                {{
                                    form.processing
                                        ? "Menyimpan..."
                                        : submitLabel
                                }}
                            </button>

                            <button
                                v-if="modalMode === 'edit'"
                                type="button"
                                :disabled="form.processing"
                                @click="deleteBanner(editingBanner)"
                                class="mt-2 flex h-10 w-full items-center justify-center gap-3 rounded-lg bg-white/5 text-sm text-white transition hover:bg-red-500/20 hover:text-red-200 disabled:opacity-60"
                            >
                                <i class="bi bi-trash"></i>
                                Delete Permanently
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
:deep(.admin-preview-carousel) {
    width: 100%;
}

:deep(.admin-preview-carousel .carousel__viewport) {
    width: 100%;
    overflow: hidden;
    border-radius: 12px;
}

:deep(.admin-preview-carousel .carousel__track) {
    width: 100%;
    align-items: stretch;
}

:deep(.admin-preview-carousel .carousel__slide) {
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
    flex-basis: 100% !important;
    padding: 0 !important;
}

:deep(.admin-preview-carousel .carousel__slide > *) {
    width: 100%;
}
</style>
