<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { reactive, watch } from "vue";

const props = defineProps({
    admins: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    roleOptions: {
        type: Array,
        default: () => [],
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
});

const filterForm = reactive({
    search: props.filters.search || "",
    status: props.filters.status || "all",
});

const applyFilters = () => {
    router.get(
        route("admin.settings.admins.index"),
        {
            search: filterForm.search || undefined,
            status: filterForm.status !== "all" ? filterForm.status : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

let searchTimeout = null;

watch(
    () => filterForm.search,
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => applyFilters(), 400);
    },
);

watch(
    () => filterForm.status,
    () => {
        applyFilters();
    },
);

const updateAdmin = (admin, field, value) => {
    router.patch(
        route("admin.settings.admins.update", admin.id),
        {
            role: field === "role" ? value : admin.role,
            status: field === "status" ? value : admin.status,
        },
        {
            preserveScroll: true,
        },
    );
};

const approveAdmin = (admin) => {
    router.patch(
        route("admin.settings.admins.update", admin.id),
        {
            role: "admin",
            status: "active",
        },
        {
            preserveScroll: true,
        },
    );
};

const rejectAdmin = (admin) => {
    router.patch(
        route("admin.settings.admins.update", admin.id),
        {
            role: "pending_admin",
            status: "rejected",
        },
        {
            preserveScroll: true,
        },
    );
};

const roleLabel = (role) => {
    return role
        .replace("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
};
</script>

<template>
    <Head title="Admin Setting" />

    <AdminLayout
        title="Admin Setting"
        :breadcrumb="[{ label: 'Setting' }, { label: 'Admin Management' }]"
    >
        <section class="space-y-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h1
                        class="text-xl font-medium leading-tight sm:text-2xl lg:text-3xl"
                    >
                        Admin Management
                    </h1>

                    <p class="mt-1 text-xs sm:text-sm text-white/40">
                        Kelola pendaftaran admin, role, dan status akses admin.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <div
                        class="flex h-10 items-center rounded-lg bg-white/5 px-4"
                    >
                        <i class="bi bi-search text-white/45"></i>

                        <input
                            v-model="filterForm.search"
                            type="text"
                            placeholder="Search admin..."
                            class="w-full border-0 bg-transparent px-3 text-sm text-white placeholder:text-white/30 focus:ring-0 sm:w-72"
                        />
                    </div>

                    <select
                        v-model="filterForm.status"
                        class="h-10 rounded-lg border-0 bg-white/5 px-4 text-sm text-white focus:ring-0"
                    >
                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                            class="bg-[#2d2e2b]"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl bg-[#1d1e1c]">
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-0">
                        <thead>
                            <tr class="bg-[#181917]">
                                <th
                                    class="w-14 rounded-tl-xl px-5 py-4 text-left"
                                >
                                    <input
                                        type="checkbox"
                                        disabled
                                        class="h-4 w-4 rounded border-white/20 bg-transparent text-[#ff675c] opacity-40 focus:ring-0"
                                    />
                                </th>

                                <th
                                    class="px-5 py-4 text-left text-xs font-medium uppercase tracking-wide text-white/40"
                                >
                                    Admin
                                    <i
                                        class="bi bi-caret-right-fill ml-1 text-[9px]"
                                    ></i>
                                </th>

                                <th
                                    class="px-5 py-4 text-left text-xs font-medium uppercase tracking-wide text-white/40"
                                >
                                    Role
                                    <i
                                        class="bi bi-caret-right-fill ml-1 text-[9px]"
                                    ></i>
                                </th>

                                <th
                                    class="px-5 py-4 text-left text-xs font-medium uppercase tracking-wide text-white/40"
                                >
                                    Status
                                    <i
                                        class="bi bi-caret-right-fill ml-1 text-[9px]"
                                    ></i>
                                </th>

                                <th
                                    class="sm:px-5 py-4 text-left text-xs font-medium uppercase tracking-wide text-white/40"
                                >
                                    Requested
                                    <i
                                        class="bi bi-caret-right-fill ml-1 text-[9px]"
                                    ></i>
                                </th>

                                <th
                                    class="rounded-tr-xl px-5 py-4 text-left text-xs font-medium uppercase tracking-wide text-white/40"
                                >
                                    Action
                                    <i
                                        class="bi bi-caret-right-fill ml-1 text-[9px]"
                                    ></i>
                                </th>
                            </tr>
                        </thead>

                        <tbody class="bg-[#30312e]">
                            <tr
                                v-for="admin in admins"
                                :key="admin.id"
                                class="transition hover:bg-white/[0.04]"
                            >
                                <td class="border-t border-white/5 px-5 py-5">
                                    <input
                                        type="checkbox"
                                        disabled
                                        class="h-4 w-4 rounded border-white/20 bg-transparent text-[#ff675c] opacity-40 focus:ring-0"
                                    />
                                </td>

                                <td class="border-t border-white/5 px-5 py-5">
                                    <div
                                        class="flex min-w-[300px] items-center gap-3"
                                    >
                                        <img
                                            :src="admin.avatar_url"
                                            :alt="admin.name"
                                            class="h-11 w-11 shrink-0 rounded-lg object-cover"
                                            referrerpolicy="no-referrer"
                                        />

                                        <div class="min-w-0">
                                            <p
                                                class="truncate font-medium text-white"
                                            >
                                                {{ admin.name }}
                                            </p>

                                            <p
                                                class="truncate text-sm text-white/40"
                                            >
                                                {{ admin.email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td class="border-t border-white/5 px-5 py-5">
                                    <select
                                        :value="admin.role"
                                        class="h-10 min-w-[150px] rounded-lg border-0 bg-white/5 px-3 text-sm font-medium text-white focus:ring-[#ff675c]"
                                        @change="
                                            updateAdmin(
                                                admin,
                                                'role',
                                                $event.target.value,
                                            )
                                        "
                                    >
                                        <option
                                            v-for="option in roleOptions"
                                            :key="option.value"
                                            :value="option.value"
                                            class="bg-[#2d2e2b]"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </td>

                                <td class="border-t border-white/5 px-5 py-5">
                                    <select
                                        :value="admin.status"
                                        class="h-10 min-w-[130px] rounded-lg border-0 bg-white/5 px-3 text-sm font-medium text-white focus:ring-[#ff675c]"
                                        @change="
                                            updateAdmin(
                                                admin,
                                                'status',
                                                $event.target.value,
                                            )
                                        "
                                    >
                                        <option
                                            v-for="option in statusOptions.filter(
                                                (item) => item.value !== 'all',
                                            )"
                                            :key="option.value"
                                            :value="option.value"
                                            class="bg-[#2d2e2b]"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </td>

                                <td
                                    class="border-t border-white/5 px-5 py-5 text-xs text-white/45"
                                >
                                    <p>{{ admin.requested_at || "-" }}</p>

                                    <p
                                        v-if="admin.approved_at"
                                        class="mt-1 text-xs text-emerald-300/70"
                                    >
                                        Approved: {{ admin.approved_at }}
                                    </p>
                                </td>

                                <td class="border-t border-white/5 px-5 py-5">
                                    <div
                                        class="flex items-center justify-start gap-2"
                                    >
                                        <button
                                            type="button"
                                            class="rounded-lg bg-emerald-400/10 px-4 py-2 text-sm font-medium text-emerald-300 transition hover:bg-emerald-400/20 disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="
                                                admin.role === 'admin' &&
                                                admin.status === 'active'
                                            "
                                            @click="approveAdmin(admin)"
                                        >
                                            Accept
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg bg-red-400/15 px-4 py-2 text-sm font-medium text-red-300 transition hover:bg-red-400/20 disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="
                                                admin.status === 'rejected'
                                            "
                                            @click="rejectAdmin(admin)"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!admins.length">
                                <td
                                    colspan="6"
                                    class="rounded-b-xl bg-[#30312e] px-5 py-14 text-center text-white/40"
                                >
                                    Belum ada pendaftaran admin.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
