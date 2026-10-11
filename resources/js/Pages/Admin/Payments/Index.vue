<script setup>
import { Head, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { computed, reactive, ref, watch } from "vue";

const props = defineProps({
    summary: {
        type: Array,
        default: () => [],
    },
    transactions: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    periodOptions: {
        type: Array,
        default: () => [],
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
    methodOptions: {
        type: Array,
        default: () => [],
    },
    sortOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const selectedRows = ref([]);
const expandedRows = ref([]);
const showFilterPanel = ref(false);
const searchTimeout = ref(null);

const filterForm = reactive({
    period: props.filters.period || "day",
    date: props.filters.date || new Date().toISOString().slice(0, 10),
    search: props.filters.search || "",
    status: props.filters.status || "all",
    method: props.filters.method || "all",
    sort: props.filters.sort || "latest",
});

const safePeriodOptions = computed(() =>
    props.periodOptions.length
        ? props.periodOptions
        : [
              { label: "Day", value: "day" },
              { label: "Week", value: "week" },
              { label: "Month", value: "month" },
          ],
);

const safeStatusOptions = computed(() =>
    props.statusOptions.length
        ? props.statusOptions
        : [
              { label: "All Status", value: "all" },
              { label: "Complete", value: "complete" },
              { label: "Processing", value: "processing" },
              { label: "Canceled", value: "canceled" },
          ],
);

const safeMethodOptions = computed(() =>
    props.methodOptions.length
        ? props.methodOptions
        : [
              { label: "All Method", value: "all" },
              { label: "Virtual Account", value: "va" },
              { label: "Retail / Gerai Offline", value: "retail" },
              { label: "E-Wallet & QRIS", value: "ewallet" },
          ],
);

const safeSortOptions = computed(() =>
    props.sortOptions.length
        ? props.sortOptions
        : [
              { label: "Newest", value: "latest" },
              { label: "Oldest", value: "oldest" },
              { label: "Amount Highest", value: "amount_highest" },
              { label: "Amount Lowest", value: "amount_lowest" },
          ],
);

const transactionRows = computed(() => props.transactions?.data || []);

const allRowIds = computed(() => transactionRows.value.map((row) => row.id));

const isAllSelected = computed(() => {
    return (
        allRowIds.value.length > 0 &&
        allRowIds.value.every((id) => selectedRows.value.includes(id))
    );
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedRows.value = [];
        return;
    }

    selectedRows.value = [...allRowIds.value];
};

const toggleSelectRow = (id) => {
    if (selectedRows.value.includes(id)) {
        selectedRows.value = selectedRows.value.filter((rowId) => rowId !== id);
        return;
    }

    selectedRows.value.push(id);
};

const toggleExpandRow = (id) => {
    if (expandedRows.value.includes(id)) {
        expandedRows.value = expandedRows.value.filter((rowId) => rowId !== id);
        return;
    }

    expandedRows.value.push(id);
};

watch(
    () => props.transactions?.data,
    () => {
        selectedRows.value = selectedRows.value.filter((id) =>
            allRowIds.value.includes(id),
        );

        expandedRows.value = expandedRows.value.filter((id) =>
            allRowIds.value.includes(id),
        );
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route("admin.payments.index"),
        {
            period: filterForm.period,
            date:
                filterForm.period === "day"
                    ? filterForm.date || new Date().toISOString().slice(0, 10)
                    : undefined,
            search: filterForm.search || undefined,
            status: filterForm.status !== "all" ? filterForm.status : undefined,
            method: filterForm.method !== "all" ? filterForm.method : undefined,
            sort: filterForm.sort !== "latest" ? filterForm.sort : undefined,
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
        clearTimeout(searchTimeout.value);

        searchTimeout.value = setTimeout(() => {
            applyFilters();
        }, 400);
    },
);

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
    () => [filterForm.status, filterForm.method, filterForm.sort],
    () => {
        applyFilters();
    },
);

const resetFilters = () => {
    filterForm.period = "day";
    filterForm.date = new Date().toISOString().slice(0, 10);
    filterForm.search = "";
    filterForm.status = "all";
    filterForm.method = "all";
    filterForm.sort = "latest";
    showFilterPanel.value = false;

    router.get(
        route("admin.payments.index"),
        {},
        {
            preserveScroll: true,
            replace: true,
        },
    );
};

const exportCsv = () => {
    window.location.href = route("admin.payments.export", {
        period: filterForm.period,
        date:
            filterForm.period === "day"
                ? filterForm.date || new Date().toISOString().slice(0, 10)
                : undefined,
        search: filterForm.search || undefined,
        status: filterForm.status !== "all" ? filterForm.status : undefined,
        method: filterForm.method !== "all" ? filterForm.method : undefined,
        sort: filterForm.sort !== "latest" ? filterForm.sort : undefined,
    });
};

const comparisonLabel = computed(() => {
    return props.filters.comparison_label || "Last Day";
});

const formatRupiah = (value) => {
    return `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
};

const statusLabel = (status) => {
    const normalized = String(status || "").toLowerCase();

    return (
        {
            paid: "Paid",
            success: "Success",
            complete: "Complete",
            completed: "Completed",
            pending: "Pending",
            processing: "Processing",
            failed: "Failed",
            expired: "Expired",
            canceled: "Canceled",
            cancelled: "Cancelled",
        }[normalized] || status
    );
};

const statusClass = (status) => {
    const normalized = String(status || "").toLowerCase();

    if (["paid", "success", "complete", "completed"].includes(normalized)) {
        return "bg-emerald-500/10 text-emerald-400";
    }

    if (["pending", "processing"].includes(normalized)) {
        return "bg-yellow-500/10 text-yellow-300";
    }

    if (["failed", "expired", "cancelled", "canceled"].includes(normalized)) {
        return "bg-red-500/10 text-red-400";
    }

    return "bg-white/10 text-white/50";
};

const statusIcon = (status) => {
    const normalized = String(status || "").toLowerCase();

    if (["paid", "success", "complete", "completed"].includes(normalized)) {
        return "bi-check-circle-fill";
    }

    if (["pending", "processing"].includes(normalized)) {
        return "bi-clock-fill";
    }

    if (["failed", "expired", "cancelled", "canceled"].includes(normalized)) {
        return "bi-x-circle-fill";
    }

    return "bi-circle-fill";
};

const methodIcon = (transaction) => {
    const methodKey = transaction.method_key;
    const paymentMethod = String(
        transaction.payment_method || "",
    ).toLowerCase();

    if (methodKey === "va" || paymentMethod.includes("virtual")) {
        return "bi-bank";
    }

    if (
        methodKey === "retail" ||
        paymentMethod.includes("retail") ||
        paymentMethod.includes("gerai")
    ) {
        return "bi-shop";
    }

    if (
        methodKey === "ewallet" ||
        paymentMethod.includes("wallet") ||
        paymentMethod.includes("qris")
    ) {
        return "bi-wallet2";
    }

    return "bi-credit-card";
};
</script>

<template>
    <Head title="Wallet & Finance" />

    <AdminLayout title="Finance" :breadcrumb="[{ label: 'Finance' }]">
        <div class="min-w-0 space-y-5">
            <section
                class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
            >
                <div>
                    <h1
                        class="text-xl font-medium leading-tight sm:text-2xl lg:text-3xl"
                    >
                        Wallet & Finance
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
                            v-for="option in safePeriodOptions"
                            :key="option.value"
                            class="bg-[#2b2c29]"
                            :value="option.value"
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
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#ff675c] px-2 sm:px-5 text-xs sm:text-sm font-medium text-white hover:bg-[#ff5145]"
                        @click="exportCsv"
                    >
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        Export CSV
                    </button>
                </div>
            </section>

            <section class="grid gap-3 sm:gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in summary"
                    :key="card.label"
                    class="overflow-hidden rounded-xl bg-[#1d1e1c]"
                >
                    <div class="bg-[#343531] rounded-xl p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span
                                    class="grid h-10 w-10 place-items-center rounded-lg bg-white/10 text-white/70"
                                >
                                    <i :class="['bi', card.icon]"></i>
                                </span>

                                <p class="text-sm text-white/35">
                                    {{ card.label }}
                                </p>
                            </div>

                            <i class="bi bi-info-circle text-white/35"></i>
                        </div>

                        <div
                            class="mt-2 sm:mt-5 flex items-end justify-between"
                        >
                            <p
                                class="break-words text-xl font-medium text-white sm:text-2xl"
                            >
                                {{ card.value }}
                            </p>

                            <div class="flex h-10 items-end gap-1 opacity-40">
                                <span class="h-4 w-[2px] bg-white/25"></span>
                                <span class="h-7 w-[2px] bg-white/25"></span>
                                <span class="h-9 w-[2px] bg-[#ff675c]"></span>
                                <span class="h-6 w-[2px] bg-white/25"></span>
                                <span class="h-8 w-[2px] bg-white/25"></span>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 py-3 text-sm">
                        <span
                            class="inline-flex items-center gap-1"
                            :class="
                                Number(card.growth) >= 0
                                    ? 'text-emerald-400'
                                    : 'text-red-400'
                            "
                        >
                            <i
                                v-if="Number(card.growth) !== 0"
                                class="bi text-xs"
                                :class="
                                    Number(card.growth) > 0
                                        ? 'bi-caret-up-fill'
                                        : 'bi-caret-down-fill'
                                "
                            ></i>

                            <span>
                                {{ Number(card.growth) > 0 ? "+" : ""
                                }}{{ card.growth }}%
                            </span>
                        </span>

                        <span class="text-white/35">
                            vs {{ comparisonLabel }}
                        </span>
                    </div>
                </article>
            </section>

            <section class="rounded-xl bg-[#1d1e1c] max-h-fit">
                <div
                    class="flex items-center justify-between gap-3 px-3 py-3 sm:px-4"
                >
                    <h2 class="text-base font-medium text-white">
                        Transaction History
                    </h2>

                    <div
                        class="flex flex-1 items-center justify-end gap-2 min-w-0"
                    >
                        <div class="relative w-full sm:w-[260px]">
                            <i
                                class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-white/45"
                            ></i>

                            <input
                                v-model="filterForm.search"
                                type="search"
                                placeholder="Search..."
                                class="h-8 w-full rounded-lg border-none bg-white/5 pl-10 pr-4 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                            />
                        </div>

                        <div class="relative">
                            <button
                                type="button"
                                class="grid h-8 w-8 place-items-center rounded-lg text-white/50 hover:bg-white/5 hover:text-white"
                                @click="showFilterPanel = !showFilterPanel"
                            >
                                <i class="bi bi-info-circle"></i>
                            </button>

                            <div
                                v-if="showFilterPanel"
                                class="absolute right-0 top-12 z-50 w-[250px] rounded-xl bg-[#2f302d] p-4 shadow-2xl shadow-black/40"
                            >
                                <div class="space-y-5">
                                    <div>
                                        <div
                                            class="text-sm font-medium text-white"
                                        >
                                            Status
                                        </div>

                                        <div class="mt-3 space-y-3">
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
                                                    class="h-4 w-4 rounded border-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                                    :checked="
                                                        filterForm.status ===
                                                        option.value
                                                    "
                                                    @change="
                                                        filterForm.status =
                                                            filterForm.status ===
                                                            option.value
                                                                ? 'all'
                                                                : option.value
                                                    "
                                                />
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <div
                                            class="text-sm font-medium text-white"
                                        >
                                            Payment Method
                                        </div>

                                        <div class="mt-3 space-y-3">
                                            <label
                                                v-for="option in safeMethodOptions"
                                                :key="option.value"
                                                class="flex cursor-pointer items-center justify-between text-sm"
                                                :class="
                                                    filterForm.method ===
                                                    option.value
                                                        ? 'text-white'
                                                        : 'text-white/35'
                                                "
                                            >
                                                <span>{{ option.label }}</span>
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                                    :checked="
                                                        filterForm.method ===
                                                        option.value
                                                    "
                                                    @change="
                                                        filterForm.method =
                                                            filterForm.method ===
                                                            option.value
                                                                ? 'all'
                                                                : option.value
                                                    "
                                                />
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <div
                                            class="text-sm font-medium text-white"
                                        >
                                            Sort By
                                        </div>

                                        <div class="mt-3 space-y-3">
                                            <label
                                                v-for="option in safeSortOptions"
                                                :key="option.value"
                                                class="flex cursor-pointer items-center justify-between text-sm"
                                                :class="
                                                    filterForm.sort ===
                                                    option.value
                                                        ? 'text-white'
                                                        : 'text-white/35'
                                                "
                                            >
                                                <span>{{ option.label }}</span>
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                                    :checked="
                                                        filterForm.sort ===
                                                        option.value
                                                    "
                                                    @change="
                                                        filterForm.sort =
                                                            filterForm.sort ===
                                                            option.value
                                                                ? 'latest'
                                                                : option.value
                                                    "
                                                />
                                            </label>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="w-full rounded-lg px-4 py-2 text-sm text-white/70 hover:bg-white/10 hover:text-white"
                                        @click="resetFilters"
                                    >
                                        Reset Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl bg-white/10">
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-[1000px] md:min-w-[1500px] lg:min-w-full text-left text-sm"
                        >
                            <thead
                                class="border-b border-white/10 text-xs uppercase text-white/35"
                            >
                                <tr>
                                    <th
                                        class="w-12 border-b border-white/10 px-5 py-4"
                                    >
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                            :checked="isAllSelected"
                                            @change="toggleSelectAll"
                                        />
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        Order ID
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        Customer
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        Amount
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        Payment Method
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        Date
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        Status
                                        <i
                                            class="bi bi-caret-right-fill text-[8px]"
                                        ></i>
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <template
                                    v-for="transaction in transactionRows"
                                    :key="transaction.id"
                                    class="border-b border-white/5 text-white last:border-b-0"
                                >
                                    <tr
                                        class="cursor-pointer border-b border-white/5 text-white transition hover:bg-white/[0.03] last:border-b-0"
                                        @click="toggleExpandRow(transaction.id)"
                                    >
                                        <td class="px-5 py-4" @click.stop>
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                                :checked="
                                                    selectedRows.includes(
                                                        transaction.id,
                                                    )
                                                "
                                                @change="
                                                    toggleSelectRow(
                                                        transaction.id,
                                                    )
                                                "
                                            />
                                        </td>

                                        <td class="px-4 py-4">
                                            {{ transaction.order_no }}
                                        </td>

                                        <td class="px-4 py-4">
                                            {{ transaction.customer }}
                                        </td>

                                        <td class="px-4 py-4">
                                            {{
                                                formatRupiah(transaction.amount)
                                            }}
                                        </td>

                                        <td class="px-4 py-4">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <span
                                                    class="grid h-10 w-10 place-items-center rounded bg-white/5 text-white/70"
                                                >
                                                    <i
                                                        :class="[
                                                            'bi',
                                                            methodIcon(
                                                                transaction,
                                                            ),
                                                        ]"
                                                    ></i>
                                                </span>

                                                <span>
                                                    {{
                                                        transaction.payment_method
                                                    }}
                                                </span>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4">
                                            {{ transaction.date }}
                                        </td>

                                        <td class="px-4 py-4">
                                            <span
                                                class="inline-flex min-w-[128px] items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-medium"
                                                :class="
                                                    statusClass(
                                                        transaction.status,
                                                    )
                                                "
                                            >
                                                <i
                                                    :class="[
                                                        'bi',
                                                        statusIcon(
                                                            transaction.status,
                                                        ),
                                                    ]"
                                                ></i>
                                                {{
                                                    statusLabel(
                                                        transaction.status,
                                                    )
                                                }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr
                                        v-if="
                                            expandedRows.includes(
                                                transaction.id,
                                            )
                                        "
                                        class="bg-black/10"
                                    >
                                        <td
                                            colspan="7"
                                            class="border-b border-white/10 px-5 py-4"
                                        >
                                            <div
                                                class="grid gap-4 text-sm text-white/60 md:grid-cols-4"
                                            >
                                                <div>
                                                    <p class="text-white/35">
                                                        Transaction Note
                                                    </p>
                                                    <p class="mt-1 text-white">
                                                        {{ transaction.note }}
                                                    </p>
                                                </div>

                                                <div>
                                                    <p class="text-white/35">
                                                        Customer Email
                                                    </p>
                                                    <p class="mt-1 text-white">
                                                        {{
                                                            transaction.customer_email ||
                                                            "-"
                                                        }}
                                                    </p>
                                                </div>

                                                <div>
                                                    <p class="text-white/35">
                                                        Payment Type
                                                    </p>
                                                    <p class="mt-1 text-white">
                                                        {{
                                                            transaction.payment_type ||
                                                            "-"
                                                        }}
                                                    </p>
                                                </div>

                                                <div>
                                                    <p class="text-white/35">
                                                        Paid At
                                                    </p>
                                                    <p class="mt-1 text-white">
                                                        {{
                                                            transaction.paid_at ||
                                                            "-"
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <tr v-if="!transactionRows.length">
                                    <td
                                        colspan="7"
                                        class="px-4 py-12 text-center text-white/40"
                                    >
                                        Belum ada transaksi finance.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="transactions.links?.length > 3"
                        class="my-5 flex flex-wrap justify-center gap-2"
                    >
                        <button
                            v-for="link in transactions.links"
                            :key="link.label"
                            type="button"
                            :disabled="!link.url"
                            @click="
                                link.url &&
                                router.visit(link.url, {
                                    preserveScroll: true,
                                    preserveState: true,
                                })
                            "
                            class="min-w-10 rounded px-3 py-2 text-sm"
                            :class="
                                link.active
                                    ? 'bg-[#ff675c] text-white'
                                    : link.url
                                      ? 'bg-white/5 text-white/70 hover:bg-white/10'
                                      : 'cursor-not-allowed bg-white/5 text-white/25'
                            "
                            v-html="link.label"
                        />
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
