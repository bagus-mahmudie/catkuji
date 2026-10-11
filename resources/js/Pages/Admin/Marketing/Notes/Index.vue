<script setup>
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { computed, reactive, ref, watch } from "vue";

const props = defineProps({
    notes: {
        type: Object,
        required: true,
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

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? null);

const modalOpen = ref(false);
const modalMode = ref("create");
const editingNote = ref(null);

let searchTimeout = null;

const filterForm = reactive({
    period: props.filters.period || "daily",
    date: props.filters.date || new Date().toISOString().slice(0, 10),
    search: props.filters.search || "",
});

const form = useForm({
    title: "",
    description: "",
    note_date: "",
    csat_feedback_id: null,
});

const modalTitle = computed(() => {
    return modalMode.value === "create" ? "Add New Note" : "Edit Note";
});

const submitLabel = computed(() => {
    return modalMode.value === "create" ? "Add Note" : "Save Note";
});

const applyFilters = () => {
    router.get(
        route("admin.marketing.notes.index"),
        {
            period: filterForm.period,
            date:
                filterForm.period === "daily"
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
        if (filterForm.period === "daily") {
            applyFilters();
        }
    },
);

watch(
    () => filterForm.search,
    () => {
        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 350);
    },
);

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.title = "";
    form.description = "";
    form.note_date = "";
    form.csat_feedback_id = null;
    editingNote.value = null;
};

const openCreateModal = () => {
    resetForm();
    modalMode.value = "create";
    modalOpen.value = true;
};

const openEditModal = (note) => {
    resetForm();

    modalMode.value = "edit";
    editingNote.value = note;

    form.title = note.title || "";
    form.description = note.description || "";
    form.note_date = note.note_date || "";
    form.csat_feedback_id = note.csat_feedback_id || null;

    modalOpen.value = true;
};

const closeModal = () => {
    if (form.processing) {
        return;
    }

    modalOpen.value = false;
    resetForm();
};

const submit = () => {
    const payloadOptions = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (modalMode.value === "create") {
        form.post(route("admin.marketing.notes.store"), payloadOptions);
        return;
    }

    form.put(
        route("admin.marketing.notes.update", editingNote.value.id),
        payloadOptions,
    );
};

const deleteNote = (note) => {
    const confirmed = confirm(`Hapus note "${note.title}"?`);

    if (!confirmed) {
        return;
    }

    router.delete(route("admin.marketing.notes.destroy", note.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Admin Notes" />

    <AdminLayout
        title="Admin Notes"
        :breadcrumb="[{ label: 'Marketing' }, { label: 'Notes' }]"
    >
        <div class="space-y-6">
            <div
                v-if="flashSuccess"
                class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300"
            >
                {{ flashSuccess }}
            </div>

            <section
                class="flex flex-col gap-4 border-b border-white/10 pb-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <h1
                    class="text-xl font-medium leading-tight sm:text-2xl lg:text-3xl"
                >
                    Admin Notes
                </h1>

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
                            class="bg-[#2d2e2b]"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <div
                        v-if="filterForm.period === 'daily'"
                        class="flex h-10 items-center gap-3"
                    >
                        <input
                            v-model="filterForm.date"
                            type="date"
                            class="h-10 w-full rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
                        />
                    </div>

                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#ff6258] px-2 sm:px-5 text-xs sm:text-sm font-medium text-white transition hover:bg-[#ff766d]"
                    >
                        <span>+</span>
                        Add Note
                    </button>
                </div>
            </section>

            <div class="flex h-10 items-center rounded-lg bg-white/5 px-4">
                <i class="bi bi-search text-white/45"></i>
                <input
                    v-model="filterForm.search"
                    type="text"
                    placeholder="Search notes..."
                    class="w-full border-0 bg-transparent px-3 text-sm text-white placeholder:text-white/30 focus:ring-0"
                />
            </div>

            <section
                v-if="notes.data.length"
                class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="note in notes.data"
                    :key="note.id"
                    class="rounded-xl border border-black/30 bg-[#343532] p-3 shadow-sm"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white/10 text-white"
                        >
                            <i class="bi bi-calendar2-week text-lg"></i>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <h2
                                    class="line-clamp-1 text-base font-medium text-white"
                                >
                                    {{ note.title }}
                                </h2>

                                <span
                                    v-if="note.csat_feedback_id"
                                    class="shrink-0 rounded-full bg-[#ff6258]/15 px-2 py-1 text-[10px] text-[#ff8a82]"
                                >
                                    CSAT
                                </span>
                            </div>

                            <p
                                class="mt-2 line-clamp-3 break-words min-h-[72px] text-sm leading-6 text-white/35"
                            >
                                {{ note.description || "Tidak ada deskripsi." }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 flex items-center justify-between gap-3 rounded-lg bg-white/5 px-3 py-2"
                    >
                        <div
                            class="flex min-w-0 flex-wrap font-medium items-center gap-x-3 gap-y-1 text-xs text-white"
                        >
                            <span class="inline-flex items-center gap-1">
                                <i class="bi bi-person"></i>
                                {{ note.admin_name }}
                            </span>

                            <span class="inline-flex items-center gap-1">
                                <i class="bi bi-calendar4-week"></i>
                                {{ note.date_label }}
                            </span>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                @click="openEditModal(note)"
                                class="grid h-8 w-8 place-items-center rounded-lg bg-white/5 text-white transition hover:bg-white/10"
                                aria-label="Edit note"
                            >
                                <i class="bi bi-pencil text-base"></i>
                            </button>

                            <button
                                type="button"
                                @click="deleteNote(note)"
                                class="grid h-8 w-8 place-items-center rounded-lg bg-white/5 text-white transition hover:bg-red-500/20 hover:text-red-200"
                                aria-label="Delete note"
                            >
                                <i class="bi bi-trash text-base"></i>
                            </button>
                        </div>
                    </div>
                </article>
            </section>

            <div
                v-else
                class="rounded-xl border border-dashed border-white/10 bg-white/[0.03] px-6 py-16 text-center"
            >
                <p class="text-lg text-white">Belum ada notes</p>
                <p class="mt-2 text-sm text-white/45">
                    Tambahkan note baru dari halaman ini atau dari CSAT detail.
                </p>
            </div>

            <div
                v-if="notes.links?.length > 3"
                class="mt-6 flex flex-wrap justify-center gap-2"
            >
                <button
                    v-for="link in notes.links"
                    :key="link.label"
                    type="button"
                    :disabled="!link.url"
                    @click="link.url && router.visit(link.url)"
                    class="min-w-10 rounded px-3 py-2 text-sm"
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
        </div>

        <Teleport to="body">
            <div
                v-if="modalOpen"
                class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto bg-black/55 px-4 py-10 backdrop-blur-[1px]"
                @click.self="closeModal"
            >
                <div
                    class="w-full max-w-[550px] rounded-xl border border-black/40 bg-[#353633] p-3 shadow-2xl"
                >
                    <div class="px-1 pb-3">
                        <h3 class="font-medium text-white">
                            {{ modalTitle }}
                        </h3>
                    </div>

                    <div class="border-t border-white/10 pt-4">
                        <label class="text-sm text-white">Title</label>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="Enter note title"
                            class="mt-1 h-10 w-full rounded-lg border-0 bg-white/5 px-4 text-sm text-white placeholder:text-white/35 focus:ring-0"
                        />
                        <p
                            v-if="form.errors.title"
                            class="mt-1 text-xs text-red-300"
                        >
                            {{ form.errors.title }}
                        </p>
                    </div>

                    <div class="mt-4">
                        <label class="text-sm text-white">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="11"
                            placeholder="Enter note Description"
                            class="mt-1 w-full resize-none rounded-lg border-0 bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/35 focus:ring-0"
                        ></textarea>
                        <p
                            v-if="form.errors.description"
                            class="mt-1 text-xs text-red-300"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <div class="mt-4">
                        <label class="text-sm text-white">Note Date</label>
                        <input
                            v-model="form.note_date"
                            type="datetime-local"
                            class="mt-1 h-10 w-full rounded-lg border-0 bg-white/5 px-4 text-sm text-white focus:ring-0"
                        />
                    </div>

                    <button
                        type="button"
                        :disabled="form.processing"
                        @click="submit"
                        class="mt-4 inline-flex h-10 w-full items-center justify-between rounded-lg bg-[#ff6258] px-4 text-sm font-medium text-white transition hover:bg-[#ff766d] disabled:opacity-60"
                    >
                        <span>
                            {{ form.processing ? "Menyimpan..." : submitLabel }}
                        </span>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
