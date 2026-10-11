<script setup>
import { Head, router } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { computed, reactive, ref, watch } from "vue";
import catkoinIcon from "@/assets/cat-koin.png";

const props = defineProps({
    summary: {
        type: Array,
        default: () => [],
    },
    orders: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: Object,
        default: () => ({
            current_page: 1,
            last_page: 1,
            per_page: 9,
            total: 0,
            from: null,
            to: null,
        }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
});

const showFilterPanel = ref(false);
const selectedOrder = ref(null);
const searchTimeout = ref(null);
const expandedOrderItems = ref([]);

const filterForm = reactive({
    search: props.filters.search || "",
    status: props.filters.status || "all",
    period: props.filters.period || "day",
    date: props.filters.date || new Date().toISOString().slice(0, 10),
});

const periodOptions = [
    { label: "Day", value: "day" },
    { label: "Week", value: "week" },
    { label: "Month", value: "month" },
];

const safeStatusOptions = computed(() =>
    props.statusOptions.length
        ? props.statusOptions
        : [
              { label: "New Orders", value: "new" },
              { label: "In Progress", value: "in_progress" },
              { label: "Shipment Success", value: "success" },
          ],
);

const visiblePaginationPages = computed(() => {
    const currentPage = Number(props.pagination.current_page || 1);
    const lastPage = Number(props.pagination.last_page || 1);
    const pages = [];

    const start = Math.max(currentPage - 2, 1);
    const end = Math.min(currentPage + 2, lastPage);

    for (let page = start; page <= end; page++) {
        pages.push(page);
    }

    return pages;
});

const applyFilters = () => {
    router.get(
        route("admin.orders.index"),
        {
            search: filterForm.search || undefined,
            status: filterForm.status !== "all" ? filterForm.status : undefined,
            period: filterForm.period,
            date:
                filterForm.period === "day"
                    ? filterForm.date || new Date().toISOString().slice(0, 10)
                    : undefined,
            page: 1,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const changePage = (page) => {
    const targetPage = Number(page);
    const lastPage = Number(props.pagination.last_page || 1);

    if (
        targetPage < 1 ||
        targetPage > lastPage ||
        targetPage === Number(props.pagination.current_page || 1)
    ) {
        return;
    }

    router.get(
        route("admin.orders.index"),
        {
            search: filterForm.search || undefined,
            status: filterForm.status !== "all" ? filterForm.status : undefined,
            period: filterForm.period,
            date:
                filterForm.period === "day"
                    ? filterForm.date || new Date().toISOString().slice(0, 10)
                    : undefined,
            page: targetPage,
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
    () => filterForm.status,
    () => {
        applyFilters();
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

const toggleStatus = (value) => {
    filterForm.status = filterForm.status === value ? "all" : value;
};

const resetFilters = () => {
    filterForm.search = "";
    filterForm.status = "all";
    filterForm.period = "day";
    filterForm.date = new Date().toISOString().slice(0, 10);
    showFilterPanel.value = false;

    router.get(
        route("admin.orders.index"),
        {
            period: "day",
            date: filterForm.date,
        },
        {
            preserveScroll: true,
            replace: true,
        },
    );
};

const exportCsv = () => {
    window.location.href = route("admin.orders.export", {
        search: filterForm.search || undefined,
        status: filterForm.status !== "all" ? filterForm.status : undefined,
        period: filterForm.period,
        date:
            filterForm.period === "day"
                ? filterForm.date || new Date().toISOString().slice(0, 10)
                : undefined,
    });
};

const formatRupiah = (value) => {
    return `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
};

const growthClass = (growth) => {
    return Number(growth) >= 0 ? "text-emerald-400" : "text-red-400";
};

const growthIcon = (growth) => {
    if (Number(growth) > 0) {
        return "bi-caret-up-fill";
    }

    if (Number(growth) < 0) {
        return "bi-caret-down-fill";
    }

    return "";
};

const statusLabel = (order) => {
    if (order.status_raw === "booking_failed") {
        return "Booking Failed";
    }

    if (order.status_raw === "paid") {
        return "Paid";
    }

    if (order.status_raw === "processing") {
        return "Processing";
    }

    return (
        order.status_label ||
        {
            new: "New",
            in_progress: "In Progress",
            success: "Shipment Success",
        }[order.status] ||
        "New"
    );
};

const statusClass = (order) => {
    if (order.status_raw === "booking_failed") {
        return "bg-red-500/10 text-red-300";
    }

    if (order.status_raw === "paid") {
        return "bg-blue-500/10 text-blue-300";
    }

    if (order.status_raw === "processing") {
        return "bg-yellow-500/10 text-yellow-300";
    }

    return (
        {
            new: "bg-emerald-500/10 text-emerald-400",
            in_progress: "bg-yellow-500/10 text-yellow-300",
            success: "bg-sky-500/10 text-sky-300",
        }[order.status] || "bg-white/10 text-white/45"
    );
};

const openShippingDetail = (order) => {
    selectedOrder.value = order;
};

const closeShippingDetail = () => {
    selectedOrder.value = null;
};

const uniqueOrderItems = (order) => {
    const items = order.items || [];
    const grouped = new Map();

    items.forEach((item) => {
        const key = item.name || `item-${item.id}`;

        if (!grouped.has(key)) {
            grouped.set(key, {
                ...item,
                quantity: Number(item.quantity || 0),
            });

            return;
        }

        const existing = grouped.get(key);

        grouped.set(key, {
            ...existing,
            quantity:
                Number(existing.quantity || 0) + Number(item.quantity || 0),
        });
    });

    return Array.from(grouped.values());
};

const isOrderItemsExpanded = (orderId) => {
    return expandedOrderItems.value.includes(orderId);
};

const toggleOrderItems = (orderId) => {
    if (expandedOrderItems.value.includes(orderId)) {
        expandedOrderItems.value = expandedOrderItems.value.filter(
            (id) => id !== orderId,
        );
        return;
    }

    expandedOrderItems.value.push(orderId);
};

const displayedOrderItems = (order) => {
    const items = uniqueOrderItems(order);

    if (isOrderItemsExpanded(order.id)) {
        return items;
    }

    return items.slice(0, 2);
};

const hiddenOrderItemsCount = (order) => {
    const total = uniqueOrderItems(order).length;

    return Math.max(total - 2, 0);
};

const canRetryBooking = (order) => {
    return (
        order.status_raw === "booking_failed" ||
        (order.status_raw === "paid" && !order.external_order_id)
    );
};

const retryBooking = (order) => {
    if (!confirm(`Ulangi booking shipment untuk order ${order.order_no}?`)) {
        return;
    }

    router.post(
        route("admin.shipments.retry-booking", order.order_no),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedOrder.value = null;
            },
        },
    );
};

const canCancelShipment = (order) => {
    return ["paid", "booking_failed", "shipping_requested"].includes(
        order.status_raw,
    );
};

const cancelShipment = (order) => {
    const reason = prompt(
        `Alasan pembatalan shipment ${order.order_no}?`,
        "Dibatalkan oleh admin.",
    );

    if (reason === null) {
        return;
    }

    router.post(
        route("admin.shipments.cancel", order.order_no),
        {
            reason,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedOrder.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Order & Shipping" />

    <AdminLayout title="Orders" :breadcrumb="[{ label: 'Orders' }]">
        <div class="min-w-0 space-y-5">
            <section
                class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
            >
                <div>
                    <h1
                        class="text-xl font-medium leading-tight sm:text-2xl lg:text-3xl"
                    >
                        Order & Shipping
                    </h1>
                </div>

                <div
                    class="flex w-full flex-nowrap items-center gap-1.5 overflow-x-auto [-webkit-overflow-scrolling:touch] xl:w-auto xl:overflow-visible xl:pb-0"
                >
                    <select
                        v-model="filterForm.period"
                        class="h-10 shrink-0 rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
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

                    <div v-if="filterForm.period === 'day'" class="relative">
                        <input
                            v-model="filterForm.date"
                            type="date"
                            class="h-10 w-full rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
                        />
                    </div>

                    <div class="relative shrink-0">
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center gap-0 rounded-lg bg-white/5 px-0 text-xs font-medium text-white/80 hover:bg-white/10 sm:w-auto sm:gap-2 sm:px-5 sm:text-sm"
                            @click="showFilterPanel = !showFilterPanel"
                        >
                            <i class="bi bi-funnel"></i>
                            <span class="hidden sm:inline">Filter</span>
                            <span class="sm:hidden"> </span>
                        </button>

                        <div
                            v-if="showFilterPanel"
                            class="fixed left-3 right-3 top-[170px] z-[80] rounded-xl bg-[#242521] p-4 shadow-2xl shadow-black/40 sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-3 sm:w-[300px]"
                        >
                            <div class="mb-4 border-b border-white/10 pb-3">
                                <h3 class="text-base font-medium text-white">
                                    Filter Panel
                                </h3>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <div
                                        class="mb-3 flex items-center justify-between text-white"
                                    >
                                        <span>Status</span>
                                        <i
                                            class="bi bi-chevron-down text-xs"
                                        ></i>
                                    </div>

                                    <div class="pl-3 space-y-3">
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
                                                class="h-5 w-5 rounded-lg border-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                                :checked="
                                                    filterForm.status ===
                                                    option.value
                                                "
                                                @change="
                                                    toggleStatus(option.value)
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

                    <button
                        type="button"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-1 rounded-lg bg-[#ff675c] px-2 text-xs font-medium text-white hover:bg-[#ff5145] sm:gap-2 sm:px-5 sm:text-sm"
                        @click="exportCsv"
                    >
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <span class="hidden sm:inline">Export CSV</span>
                        <span class="sm:hidden">Export</span>
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

                    <div
                        class="flex items-center justify-between px-4 py-3 text-sm"
                    >
                        <p>
                            <span
                                class="inline-flex items-center gap-1"
                                :class="growthClass(card.growth)"
                            >
                                <i
                                    v-if="Number(card.growth) !== 0"
                                    class="bi text-xs"
                                    :class="growthIcon(card.growth)"
                                ></i>

                                <span>
                                    {{ Number(card.growth) > 0 ? "+" : ""
                                    }}{{ card.growth }}%
                                </span>
                            </span>

                            <span class="text-white/35">
                                vs {{ filters.comparison_label || "Last Day" }}
                            </span>
                        </p>
                    </div>
                </article>
            </section>

            <section class="relative">
                <div class="grid items-start gap-5 xl:grid-cols-3">
                    <article
                        v-for="order in orders"
                        :key="order.id"
                        class="flex min-h-[480px] flex-col rounded-xl bg-[#343531] p-3"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <img
                                    :src="order.customer.avatar"
                                    :alt="order.customer.name"
                                    class="h-12 w-12 rounded-full object-cover"
                                />

                                <div>
                                    <h2 class="font-medium text-white">
                                        {{ order.customer.name }}
                                    </h2>
                                    <p
                                        class="mt-1 text-xs sm:text-sm text-white/35"
                                    >
                                        Order {{ order.order_no }}
                                    </p>
                                </div>
                            </div>

                            <span
                                class="rounded-full px-4 py-1 text-sm font-medium"
                                :class="statusClass(order)"
                            >
                                {{ statusLabel(order) }}
                            </span>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-white">Payment Method</p>
                                <p class="mt-1 text-sm text-white/35">
                                    {{ order.payment_method }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-sm text-white">Total Payment</p>
                                <p class="mt-1 text-sm text-white/35">
                                    {{ formatRupiah(order.total_payment) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-lg bg-white/5 p-3">
                            <div
                                v-for="item in displayedOrderItems(order)"
                                :key="`${order.id}-${item.id || item.name}`"
                                class="flex min-h-[56px] items-start justify-between gap-3 py-1"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <img
                                        :src="item.image"
                                        :alt="item.name"
                                        class="h-12 w-12 rounded-lg object-cover"
                                    />

                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm sm:text-base font-medium text-white"
                                        >
                                            {{ item.name }}
                                        </p>

                                        <p
                                            class="mt-1 flex items-center text-sm text-white/35"
                                        >
                                            <span>{{ item.draw_count }}X </span>

                                            <img
                                                :src="catkoinIcon"
                                                alt="Catkoin"
                                                class="ml-2 mr-[2px] h-4 w-4 object-contain"
                                            />

                                            <span>
                                                {{
                                                    Number(
                                                        item.price || 0,
                                                    ).toLocaleString("id-ID")
                                                }}
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <span class="shrink-0 text-sm text-[#ff675c]">
                                    {{ item.quantity }}x
                                </span>
                            </div>

                            <button
                                v-if="hiddenOrderItemsCount(order) > 0"
                                type="button"
                                class="mt-1 flex w-full items-center justify-end gap-2 text-right text-sm text-white/35 transition hover:text-white"
                                @click="toggleOrderItems(order.id)"
                            >
                                <span v-if="!isOrderItemsExpanded(order.id)">
                                    +{{ hiddenOrderItemsCount(order) }} more
                                    items
                                </span>

                                <span v-else> Show less </span>

                                <i
                                    class="bi text-xs transition"
                                    :class="
                                        isOrderItemsExpanded(order.id)
                                            ? 'bi-chevron-up'
                                            : 'bi-chevron-down'
                                    "
                                ></i>
                            </button>
                        </div>

                        <div class="mt-auto pt-3">
                            <div>
                                <p class="text-sm text-white">Order Notes :</p>

                                <p
                                    class="mt-2 line-clamp-1 text-sm leading-5 text-white/35"
                                >
                                    {{ order.notes }}
                                </p>
                            </div>

                            <div class="mt-3 border-t border-white/10 pt-4">
                                <div
                                    class="grid gap-2"
                                    :class="
                                        canRetryBooking(order)
                                            ? 'grid-cols-2'
                                            : 'grid-cols-1'
                                    "
                                >
                                    <button
                                        type="button"
                                        class="flex h-12 w-full items-center justify-between rounded-lg bg-[#ff675c] px-3 text-sm font-medium text-white hover:bg-[#ff5145]"
                                        @click="openShippingDetail(order)"
                                    >
                                        <span class="truncate"
                                            >Detail Shipping</span
                                        >
                                        <i
                                            class="bi bi-chevron-right shrink-0"
                                        ></i>
                                    </button>

                                    <button
                                        v-if="canRetryBooking(order)"
                                        type="button"
                                        class="flex h-12 w-full items-center justify-between rounded-lg bg-red-500/15 px-3 text-sm font-medium text-red-200 hover:bg-red-500/25"
                                        @click="retryBooking(order)"
                                    >
                                        <span class="truncate"
                                            >Retry Booking</span
                                        >
                                        <i
                                            class="bi bi-arrow-clockwise shrink-0"
                                        ></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>

                    <div
                        v-if="!orders.length"
                        class="col-span-full rounded-xl border border-dashed border-white/10 p-12 text-center text-white/40"
                    >
                        Belum ada order.
                    </div>
                </div>

                <div
                    v-if="pagination.total > 0"
                    class="mt-3 grid grid-cols-1 items-center gap-4 px-4 py-4 md:grid-cols-3"
                >
                    <p class="text-sm text-white/40">
                        Showing
                        <span class="text-white/40">{{ pagination.from }}</span>
                        -
                        <span class="text-white/40">{{ pagination.to }}</span>
                        of
                        <span class="text-white/40">{{
                            pagination.total
                        }}</span>
                        orders
                    </p>

                    <div
                        class="flex flex-wrap items-center justify-center gap-2"
                    >
                        <button
                            type="button"
                            class="grid h-9 w-9 place-items-center rounded-lg bg-white/5 text-sm text-white/70 hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="pagination.current_page <= 1"
                            @click="changePage(pagination.current_page - 1)"
                        >
                            <i class="bi bi-chevron-left"></i>
                        </button>

                        <button
                            v-if="!visiblePaginationPages.includes(1)"
                            type="button"
                            class="grid h-9 min-w-9 place-items-center rounded-lg bg-white/5 px-3 text-sm text-white/70 hover:bg-white/10"
                            @click="changePage(1)"
                        >
                            1
                        </button>

                        <span
                            v-if="visiblePaginationPages[0] > 2"
                            class="px-1 text-white/30"
                        >
                            ...
                        </span>

                        <button
                            v-for="page in visiblePaginationPages"
                            :key="page"
                            type="button"
                            class="grid h-9 min-w-9 place-items-center rounded-lg px-3 text-sm"
                            :class="
                                page === pagination.current_page
                                    ? 'bg-[#ff675c] text-white'
                                    : 'bg-white/5 text-white/70 hover:bg-white/10'
                            "
                            @click="changePage(page)"
                        >
                            {{ page }}
                        </button>

                        <span
                            v-if="
                                visiblePaginationPages[
                                    visiblePaginationPages.length - 1
                                ] <
                                pagination.last_page - 1
                            "
                            class="px-1 text-white/30"
                        >
                            ...
                        </span>

                        <button
                            v-if="
                                !visiblePaginationPages.includes(
                                    pagination.last_page,
                                )
                            "
                            type="button"
                            class="grid h-9 min-w-9 place-items-center rounded-lg bg-white/5 px-3 text-sm text-white/70 hover:bg-white/10"
                            @click="changePage(pagination.last_page)"
                        >
                            {{ pagination.last_page }}
                        </button>

                        <button
                            type="button"
                            class="grid h-9 w-9 place-items-center rounded-lg bg-white/5 text-sm text-white/70 hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="
                                pagination.current_page >= pagination.last_page
                            "
                            @click="changePage(pagination.current_page + 1)"
                        >
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="selectedOrder"
                class="fixed inset-0 z-[9999] bg-black/60 backdrop-blur-[1px]"
                @click="closeShippingDetail"
            >
                <aside
                    class="absolute right-0 top-0 h-full w-full max-w-[480px] overflow-y-auto bg-[#1d1e1c] px-5 py-6 text-white shadow-2xl shadow-black/50"
                    @click.stop
                >
                    <div class="mb-7 flex items-center justify-between">
                        <h2 class="text-lg font-medium">Detail Tracking</h2>

                        <button
                            type="button"
                            class="grid h-9 w-9 place-items-center rounded-lg text-white/70 hover:bg-white/10 hover:text-white"
                            @click="closeShippingDetail"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <section class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-white">
                                Customer Details
                            </h3>
                        </div>

                        <div class="rounded-lg bg-white/10">
                            <div
                                class="flex justify-between border-b border-white/5 px-3 py-2 text-sm"
                            >
                                <span class="text-white/45">Name</span>
                                <span>{{ selectedOrder.customer.name }}</span>
                            </div>

                            <div
                                class="flex justify-between border-b border-white/5 px-3 py-2 text-sm"
                            >
                                <span class="text-white/45">Phone Number</span>
                                <span>{{ selectedOrder.customer.phone }}</span>
                            </div>

                            <div
                                class="flex justify-between border-b border-white/5 px-3 py-2 text-sm"
                            >
                                <span class="text-white/45">Email Address</span>
                                <span>{{ selectedOrder.customer.email }}</span>
                            </div>

                            <div class="flex justify-between px-3 py-2 text-sm">
                                <span class="text-white/45">Order ID</span>
                                <span>{{ selectedOrder.order_no }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="mt-7 space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-white">
                                Status Details
                            </h3>
                        </div>

                        <div class="rounded-lg bg-white/10">
                            <div
                                class="flex justify-between border-b border-white/5 px-3 py-2 text-sm"
                            >
                                <span class="text-white/45"
                                    >Payment Status</span
                                >
                                <span class="capitalize">
                                    {{ selectedOrder.payment_status || "-" }}
                                </span>
                            </div>

                            <div
                                class="flex justify-between border-b border-white/5 px-3 py-2 text-sm"
                            >
                                <span class="text-white/45"
                                    >Shipment Status</span
                                >
                                <span class="capitalize">
                                    {{ selectedOrder.status_raw || "-" }}
                                </span>
                            </div>

                            <div
                                class="flex justify-between border-b border-white/5 px-3 py-2 text-sm"
                            >
                                <span class="text-white/45"
                                    >Tracking Status</span
                                >
                                <span class="capitalize">
                                    {{
                                        selectedOrder.tracking_status_raw || "-"
                                    }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <section class="mt-7 space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-white">
                                Stock Availability
                            </h3>
                        </div>

                        <div class="overflow-hidden rounded-lg bg-white/10">
                            <div
                                class="flex items-center justify-between bg-white/5 px-3 py-3 text-sm"
                            >
                                <div
                                    class="flex items-center gap-3 text-emerald-400"
                                >
                                    <span
                                        class="grid h-6 w-6 place-items-center rounded-full bg-emerald-500/20"
                                    >
                                        <i class="bi bi-check"></i>
                                    </span>
                                    Estimasi Order Delivery
                                </div>

                                <span class="text-emerald-400">
                                    {{ selectedOrder.shipping.estimated_date }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-4 px-3 py-4">
                                <div>
                                    <p class="text-sm text-white/45">Dari :</p>
                                    <p
                                        class="mt-1 text-sm leading-5 text-white"
                                    >
                                        {{ selectedOrder.shipping.origin }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-sm text-white/45">
                                        Tujuan :
                                    </p>
                                    <p
                                        class="mt-1 text-sm leading-5 text-white"
                                    >
                                        {{ selectedOrder.shipping.destination }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mx-3 flex items-center justify-between rounded-lg bg-white/10 px-3 py-3 text-sm"
                            >
                                <div class="flex items-center gap-3">
                                    <i class="bi bi-truck"></i>
                                    {{ selectedOrder.shipping.courier }}
                                </div>

                                <span class="text-white/45">
                                    {{ selectedOrder.shipping.vehicle }}
                                </span>
                            </div>

                            <div class="space-y-0 px-3 py-5">
                                <div
                                    v-for="(track, index) in selectedOrder
                                        .shipping.tracking"
                                    :key="`${track.title}-${index}`"
                                    class="relative grid grid-cols-[22px_1fr_auto] gap-3 pb-5 last:pb-0"
                                >
                                    <div class="relative flex justify-center">
                                        <span
                                            class="mt-1 h-3 w-3 rounded-full"
                                            :class="
                                                track.active
                                                    ? 'bg-emerald-500'
                                                    : 'bg-white/60'
                                            "
                                        ></span>

                                        <span
                                            v-if="
                                                index !==
                                                selectedOrder.shipping.tracking
                                                    .length -
                                                    1
                                            "
                                            class="absolute top-5 h-full w-px bg-white/30"
                                        ></span>
                                    </div>

                                    <div>
                                        <p
                                            class="font-medium text-sm text-white"
                                        >
                                            {{ track.title }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm leading-4 text-white/45"
                                        >
                                            {{ track.location }}
                                        </p>
                                    </div>

                                    <div class="text-right">
                                        <p class="text-sm text-white">
                                            {{ track.date }}
                                        </p>
                                        <p class="mt-1 text-xs text-white/45">
                                            {{ track.time }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div
                        v-if="canCancelShipment(selectedOrder)"
                        class="mt-5 rounded-lg border border-red-500/20 bg-red-500/10 p-4"
                    >
                        <p class="text-sm font-medium text-red-200">
                            Shipment bermasalah?
                        </p>

                        <p class="mt-1 text-sm leading-5 text-red-100/70">
                            Batalkan shipment ini untuk mengembalikan item ke
                            inventory user. Refund ongkir tetap perlu diproses
                            manual jika pembayaran sudah berhasil.
                        </p>

                        <button
                            type="button"
                            class="mt-4 flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-red-500 px-4 text-sm font-semibold text-white hover:bg-red-600"
                            @click="cancelShipment(selectedOrder)"
                        >
                            <i class="bi bi-x-circle"></i>
                            Cancel Shipment
                        </button>
                    </div>
                </aside>
            </div>
        </Teleport>
    </AdminLayout>
</template>
