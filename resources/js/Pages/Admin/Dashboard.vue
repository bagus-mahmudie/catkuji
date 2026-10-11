<script setup>
import { Head, router } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from "vue";
import ApexCharts from "apexcharts";

const props = defineProps({
    stats: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    salesTrend: {
        type: Array,
        default: () => [],
    },
    revenueBreakdown: {
        type: Array,
        default: () => [],
    },
    recentTransactions: {
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
});

let searchTimeout = null;

const filterForm = reactive({
    period: props.filters.period || "day",
    date: props.filters.date || new Date().toISOString().slice(0, 10),
    search: props.filters.search || "",
});

const periodOptions = [
    { label: "Day", value: "day" },
    { label: "Week", value: "week" },
    { label: "Month", value: "month" },
    { label: "Year", value: "year" },
];

const trendTabs = [
    { label: "Weekly", value: "weekly" },
    { label: "Monthly", value: "monthly" },
    { label: "Yearly", value: "yearly" },
];

const activeTrend = computed(() => props.filters.trend || "monthly");

const salesTrendTotalRevenue = computed(() => {
    return (props.salesTrend || []).reduce((total, item) => {
        return total + Number(item.revenue || 0);
    }, 0);
});

const revenueBreakdownTotal = computed(() => {
    return (props.revenueBreakdown || []).reduce((total, item) => {
        return total + Number(item.amount || 0);
    }, 0);
});

const selectedRows = ref([]);

const recentTransactionRows = computed(
    () => props.recentTransactions?.data || [],
);

const allRecentTransactionIds = computed(() =>
    recentTransactionRows.value.map((row) => row.id),
);

const isAllSelected = computed(() => {
    return (
        allRecentTransactionIds.value.length > 0 &&
        allRecentTransactionIds.value.every((id) =>
            selectedRows.value.includes(id),
        )
    );
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedRows.value = [];
        return;
    }

    selectedRows.value = [...allRecentTransactionIds.value];
};

const toggleSelectRow = (id) => {
    if (selectedRows.value.includes(id)) {
        selectedRows.value = selectedRows.value.filter((rowId) => rowId !== id);
        return;
    }

    selectedRows.value.push(id);
};

watch(
    () => props.recentTransactions.data,
    () => {
        selectedRows.value = selectedRows.value.filter((id) =>
            allRecentTransactionIds.value.includes(id),
        );
    },
    { deep: true },
);

const applyFilters = (extra = {}) => {
    router.get(
        route("admin.dashboard"),
        {
            period: filterForm.period,
            date:
                filterForm.period === "day"
                    ? filterForm.date || new Date().toISOString().slice(0, 10)
                    : undefined,
            search: filterForm.search || undefined,
            trend: extra.trend || activeTrend.value || "monthly",
        },
        {
            preserveState: true,
            preserveScroll: true,
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

watch(
    () => filterForm.search,
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => applyFilters(), 350);
    },
);

const changeTrend = (trend) => {
    applyFilters({ trend });
};

const exportCsv = () => {
    window.location.href = route("admin.dashboard.export", {
        period: filterForm.period,
        date:
            filterForm.period === "day"
                ? filterForm.date || new Date().toISOString().slice(0, 10)
                : undefined,
        search: filterForm.search || undefined,
        trend: props.filters.trend || "monthly",
    });
};

const formatRupiah = (value) => {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value || 0);
};

const formatNumber = (value) => {
    return new Intl.NumberFormat("id-ID").format(value || 0);
};

const formatDisplayDate = (date) => {
    if (!date) {
        return "-";
    }

    return new Intl.DateTimeFormat("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric",
    }).format(new Date(date));
};

const formatStatValue = (item) => {
    if (item.type === "currency") {
        return formatRupiah(item.value);
    }

    return formatNumber(item.value);
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

const salesTrendChartRef = ref(null);
const salesTrendChart = ref(null);

const compactNumber = (value) => {
    const numberValue = Number(value || 0);

    if (numberValue >= 1000000000) {
        return `${Math.round(numberValue / 1000000000)}B`;
    }

    if (numberValue >= 1000000) {
        return `${Math.round(numberValue / 1000000)}M`;
    }

    if (numberValue >= 1000) {
        return `${Math.round(numberValue / 1000)}K`;
    }

    return Math.round(numberValue);
};

const getSalesTrendChartOptions = () => {
    return {
        series: [
            {
                name: "New User",
                data:
                    props.salesTrend?.map((item) =>
                        Number(item.new_users || 0),
                    ) || [],
            },
            {
                name: "Existing User",
                data:
                    props.salesTrend?.map((item) =>
                        Number(item.existing_users || 0),
                    ) || [],
            },
        ],
        chart: {
            type: "area",
            height: "100%",
            width: "100%",
            parentHeightOffset: 0,
            toolbar: {
                show: false,
            },
            background: "transparent",
            zoom: {
                enabled: false,
            },
            animations: {
                enabled: true,
            },
        },
        colors: ["#ff675c", "#6b7280"],
        stroke: {
            curve: "smooth",
            width: [2.5, 1.8],
        },
        fill: {
            type: "gradient",
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.35,
                opacityTo: 0.06,
                stops: [0, 100],
            },
        },
        markers: {
            size: 0,
            strokeWidth: 0,
            hover: {
                size: 5,
            },
        },
        dataLabels: {
            enabled: false,
        },
        legend: {
            show: false,
        },
        grid: {
            show: true,
            borderColor: "rgba(255,255,255,0.12)",
            strokeDashArray: 4,
            position: "back",
            xaxis: {
                lines: {
                    show: false,
                },
            },
            yaxis: {
                lines: {
                    show: true,
                },
            },
            padding: {
                top: 8,
                right: 8,
                bottom: 0,
                left: 8,
            },
        },
        xaxis: {
            categories: props.salesTrend?.map((item) => item.month) || [],
            labels: {
                show: true,
                rotate: 0,
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "12px",
                },
            },
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            tooltip: {
                enabled: false,
            },
        },
        yaxis: {
            show: true,
            min: 0,
            tickAmount: 5,
            forceNiceScale: true,
            labels: {
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "12px",
                },
                formatter: (value) => compactNumber(value),
            },
        },
        tooltip: {
            shared: true,
            intersect: false,
            custom: function ({ series, dataPointIndex }) {
                const point = props.salesTrend?.[dataPointIndex] || {};

                const label = point.month || "";
                const newUser = series?.[0]?.[dataPointIndex] ?? 0;
                const existingUser = series?.[1]?.[dataPointIndex] ?? 0;

                return `
            <div class="sales-trend-tooltip">
                <div class="sales-trend-tooltip__title">${label}</div>

                <div class="sales-trend-tooltip__row">
                    <span class="sales-trend-tooltip__left">
                        <span class="sales-trend-tooltip__dot sales-trend-tooltip__dot--new"></span>
                        New User
                    </span>
                    <span class="sales-trend-tooltip__value">${formatNumber(newUser)}</span>
                </div>

                <div class="sales-trend-tooltip__row">
                    <span class="sales-trend-tooltip__left">
                        <span class="sales-trend-tooltip__dot sales-trend-tooltip__dot--existing"></span>
                        Existing User
                    </span>
                    <span class="sales-trend-tooltip__value">${formatNumber(existingUser)}</span>
                </div>
            </div>
        `;
            },
        },
    };
};

const renderSalesTrendChart = async () => {
    await nextTick();

    if (!salesTrendChartRef.value) {
        return;
    }

    if (salesTrendChart.value) {
        salesTrendChart.value.destroy();
        salesTrendChart.value = null;
    }

    salesTrendChart.value = new ApexCharts(
        salesTrendChartRef.value,
        getSalesTrendChartOptions(),
    );

    salesTrendChart.value.render();
};

const revenueBreakdownChartRef = ref(null);
const revenueBreakdownChart = ref(null);

const getRevenueBreakdownChartOptions = () => {
    return {
        series: [
            {
                name: "Revenue",
                data:
                    props.revenueBreakdown?.map((item) =>
                        Number(item.amount || 0) > 0
                            ? Number(item.amount || 0)
                            : 1,
                    ) || [],
            },
        ],
        chart: {
            type: "bar",
            height: "100%",
            width: "100%",
            parentHeightOffset: 0,
            toolbar: {
                show: false,
            },
            background: "transparent",
            zoom: {
                enabled: false,
            },
        },
        colors: ["#ff675c"],
        plotOptions: {
            bar: {
                columnWidth: "38%",
                borderRadius: 6,
                borderRadiusApplication: "end",
            },
        },
        dataLabels: {
            enabled: false,
        },
        legend: {
            show: false,
        },
        grid: {
            show: true,
            borderColor: "rgba(255,255,255,0.12)",
            strokeDashArray: 4,
            position: "back",
            xaxis: {
                lines: {
                    show: false,
                },
            },
            yaxis: {
                lines: {
                    show: true,
                },
            },
            padding: {
                top: 8,
                right: 4,
                bottom: 0,
                left: 4,
            },
        },
        xaxis: {
            categories:
                props.revenueBreakdown?.map((item) => item.category) || [],
            labels: {
                show: true,
                rotate: -35,
                trim: true,
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "10px",
                },
            },
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            tooltip: {
                enabled: false,
            },
        },
        yaxis: {
            show: true,
            min: 0,
            tickAmount: 4,
            labels: {
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "10px",
                },
                formatter: (value) => compactNumber(value),
            },
        },
        tooltip: {
            shared: false,
            intersect: false,
            custom: function ({ dataPointIndex }) {
                const item = props.revenueBreakdown?.[dataPointIndex] || {};

                return `
                    <div class="revenue-breakdown-tooltip">
                        <div class="revenue-breakdown-tooltip__title">
                            ${item.category || "-"}
                        </div>

                        <div class="revenue-breakdown-tooltip__row">
                            <span class="revenue-breakdown-tooltip__left">
                                <span class="revenue-breakdown-tooltip__dot"></span>
                                Revenue
                            </span>
                            <span class="revenue-breakdown-tooltip__value">
                                ${formatRupiah(Number(item.amount || 0))}
                            </span>
                        </div>

                        <div class="revenue-breakdown-tooltip__row">
                            <span class="revenue-breakdown-tooltip__left">
                                Percentage
                            </span>
                            <span class="revenue-breakdown-tooltip__value">
                                ${item.percentage || 0}%
                            </span>
                        </div>
                    </div>
                `;
            },
        },
    };
};

const renderRevenueBreakdownChart = async () => {
    await nextTick();

    if (!revenueBreakdownChartRef.value) {
        return;
    }

    if (revenueBreakdownChart.value) {
        revenueBreakdownChart.value.destroy();
        revenueBreakdownChart.value = null;
    }

    revenueBreakdownChart.value = new ApexCharts(
        revenueBreakdownChartRef.value,
        getRevenueBreakdownChartOptions(),
    );

    revenueBreakdownChart.value.render();
};

onMounted(() => {
    renderSalesTrendChart();
    renderRevenueBreakdownChart();
});

watch(
    () => props.salesTrend,
    () => {
        renderSalesTrendChart();
    },
    { deep: true },
);

watch(
    () => props.revenueBreakdown,
    () => {
        renderRevenueBreakdownChart();
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (salesTrendChart.value) {
        salesTrendChart.value.destroy();
        salesTrendChart.value = null;
    }

    if (revenueBreakdownChart.value) {
        revenueBreakdownChart.value.destroy();
        revenueBreakdownChart.value = null;
    }
});
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout title="Dashboard" :breadcrumb="[{ label: 'Overview' }]">
        <div class="min-w-0 space-y-5">
            <section
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h1
                        class="text-xl font-medium leading-tight sm:text-2xl lg:text-3xl"
                    >
                        Welcome Back,
                        {{ $page.props.auth?.user?.name || "Admin" }}
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
                            class="bg-[#2d2e2b]"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <div v-if="filterForm.period === 'day'" class="relative">
                        <input
                            v-model="filterForm.date"
                            type="date"
                            class="h-10 rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
                        />
                    </div>

                    <button
                        type="button"
                        @click="exportCsv"
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#ff675c] px-2 sm:px-5 text-xs sm:text-sm font-medium text-white hover:bg-[#ff5145]"
                    >
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        Export CSV
                    </button>
                </div>
            </section>

            <section class="grid gap-3 sm:gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="item in stats"
                    :key="item.label"
                    class="rounded-xl bg-[#1d1e1c]"
                >
                    <div class="rounded-xl bg-white/10 p-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    class="grid h-10 w-10 place-items-center rounded-lg bg-white/10 text-white/75"
                                >
                                    <i :class="['bi', item.icon]"></i>
                                </div>

                                <p class="text-sm text-white/35">
                                    {{ item.label }}
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
                                {{ formatStatValue(item) }}
                            </p>

                            <div class="flex h-10 items-end gap-1 opacity-50">
                                <span
                                    class="h-5 w-[2px] rounded bg-white/15"
                                ></span>
                                <span
                                    class="h-8 w-[2px] rounded bg-white/15"
                                ></span>
                                <span
                                    class="h-6 w-[2px] rounded bg-[#ff675c]"
                                ></span>
                                <span
                                    class="h-9 w-[2px] rounded bg-white/15"
                                ></span>
                                <span
                                    class="h-7 w-[2px] rounded bg-white/15"
                                ></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-3 text-sm">
                        <p>
                            <span
                                class="inline-flex items-center gap-1"
                                :class="
                                    Number(item.growth) >= 0
                                        ? 'text-emerald-400'
                                        : 'text-red-400'
                                "
                            >
                                <i
                                    v-if="Number(item.growth) !== 0"
                                    class="bi text-xs"
                                    :class="
                                        Number(item.growth) > 0
                                            ? 'bi-caret-up-fill'
                                            : 'bi-caret-down-fill'
                                    "
                                ></i>

                                <span>
                                    {{ Number(item.growth) > 0 ? "+" : ""
                                    }}{{ item.growth }}%
                                </span>
                            </span>

                            <span class="text-white/35">
                                vs {{ filters.comparison_label || "Last Day" }}
                            </span>
                        </p>
                    </div>
                </article>
            </section>

            <section
                class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_420px]"
            >
                <article class="min-w-0 rounded-xl bg-[#1d1e1c]">
                    <div class="flex items-center justify-between px-4 py-3">
                        <h2 class="font-medium">Sales Trend</h2>
                        <i class="bi bi-info-circle text-white/35"></i>
                    </div>

                    <div class="min-w-0 rounded-xl bg-white/10 p-3 sm:p-4">
                        <div
                            class="grid min-w-0 gap-4 md:gap-3 lg:grid-cols-[1fr_auto_1fr] lg:items-center"
                        >
                            <div class="flex items-center">
                                <p class="text-sm text-white/35 sm:text-base">
                                    {{
                                        activeTrend === "weekly"
                                            ? "Weekly Revenue :"
                                            : activeTrend === "monthly"
                                              ? "Monthly Revenue :"
                                              : "Yearly Revenue :"
                                    }}

                                    <span
                                        class="mt-1 block break-words text-xl font-medium text-white sm:ml-2 sm:mt-0 sm:inline sm:text-2xl"
                                    >
                                        {{
                                            formatRupiah(salesTrendTotalRevenue)
                                        }}
                                    </span>
                                </p>
                            </div>

                            <div
                                class="flex flex-wrap items-center justify-start md:justify-start gap-3 sm:justify-center sm:gap-6"
                            >
                                <div
                                    class="flex items-center gap-2 text-sm text-white/35"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#ff675c]"
                                    ></span>
                                    New User
                                </div>

                                <div
                                    class="flex items-center gap-2 text-sm text-white/35"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full bg-gray-500"
                                    ></span>
                                    Existing User
                                </div>
                            </div>

                            <div
                                class="flex justify-start md:justify-start lg:justify-end"
                            >
                                <div
                                    class="grid w-full grid-cols-3 rounded-full bg-[#2b2c29] p-1 text-xs sm:w-auto sm:text-sm"
                                >
                                    <button
                                        v-for="tab in trendTabs"
                                        :key="tab.value"
                                        type="button"
                                        @click="changeTrend(tab.value)"
                                        class="rounded-full px-3 py-2 transition sm:px-4"
                                        :class="
                                            activeTrend === tab.value
                                                ? 'bg-white/10 text-white'
                                                : 'text-white/70 hover:text-white'
                                        "
                                    >
                                        {{ tab.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-5 h-[240px] min-w-0 sm:mt-6 sm:h-[264px]"
                        >
                            <div
                                v-if="salesTrend?.length"
                                ref="salesTrendChartRef"
                                class="h-[240px] w-full sm:h-[264px]"
                            ></div>

                            <div
                                v-else
                                class="grid h-[240px] place-items-center rounded-xl border border-dashed border-white/10 text-center text-sm text-white/35 sm:h-[264px]"
                            >
                                Belum ada data sales trend.
                            </div>
                        </div>
                    </div>
                </article>
                <article class="min-w-0 rounded-xl bg-[#1d1e1c]">
                    <div class="flex items-center justify-between px-4 py-3">
                        <h2 class="font-medium">Revenue Breakdown</h2>
                        <i class="bi bi-info-circle text-white/35"></i>
                    </div>
                    <div class="min-w-0 rounded-xl bg-white/10 p-3 sm:p-4">
                        <p class="text-sm text-white/35">Revenue by category</p>

                        <p
                            class="mt-1 break-words text-xl font-medium sm:text-2xl"
                        >
                            {{ formatRupiah(revenueBreakdownTotal) }}
                        </p>

                        <!-- <div
                            class="mt-6 flex w-full items-center justify-between rounded bg-white/8 px-4 py-3 text-sm text-white/80"
                        >
                            <span class="flex items-center gap-3">
                                <i class="bi bi-calendar3"></i>
                                {{
                                    filters.date_label ||
                                    formatDisplayDate(filterForm.date)
                                }}
                            </span>
                            <i class="bi bi-chevron-down"></i>
                        </div> -->

                        <div class="mt-5 h-[240px] min-w-0 sm:h-[260px]">
                            <div
                                v-if="revenueBreakdown?.length"
                                ref="revenueBreakdownChartRef"
                                class="h-[240px] w-full sm:h-[260px]"
                            ></div>

                            <div
                                v-else
                                class="grid h-[240px] place-items-center rounded-xl border border-dashed border-white/10 text-center text-sm text-white/35 sm:h-[260px]"
                            >
                                Belum ada data revenue per kategori.
                            </div>
                        </div>

                        <!-- <div
                            class="mt-4 max-h-[95px] space-y-2 overflow-y-auto pr-1 custom-thin-scroll"
                        >
                            <div
                                v-for="item in revenueBreakdown"
                                :key="item.category"
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <div
                                    class="flex min-w-0 items-center gap-2 text-white/45"
                                >
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full bg-[#ff675c]"
                                    ></span>
                                    <span class="truncate">{{
                                        item.category
                                    }}</span>
                                </div>

                                <div class="shrink-0 text-right">
                                    <p class="text-white">
                                        {{ item.percentage }}%
                                    </p>
                                    <p class="text-xs text-white/35">
                                        {{ formatRupiah(item.amount) }}
                                    </p>
                                </div>
                            </div>
                        </div> -->
                    </div>
                </article>
            </section>

            <section class="max-h-fit rounded-xl bg-[#1d1e1c]">
                <div
                    class="flex items-center justify-between gap-3 px-3 py-3 sm:px-4"
                >
                    <h2 class="text-base font-medium text-white">
                        Transaction History
                    </h2>

                    <div
                        class="flex flex-1 items-center justify-end gap-2 min-w-0"
                    >
                        <div class="relative w-full md:w-[260px]">
                            <i
                                class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-white/45"
                            ></i>

                            <input
                                v-model="filterForm.search"
                                type="search"
                                placeholder="Search..."
                                class="h-8 w-full rounded-lg border-none bg-white/5 pl-10 pr-4 text-sm text-white placeholder:text-white/35 focus:ring-2 focus:ring-inset focus:border-[#ff675c] focus:ring-[#ff675c]"
                            />
                        </div>

                        <i class="bi bi-info-circle text-white/35"></i>
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
                                    <th class="w-16 px-5 sm:px-8 py-4">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                            :checked="isAllSelected"
                                            @change="toggleSelectAll"
                                        />
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        ORDER ID
                                        <i
                                            class="bi bi-caret-right-fill ml-1 text-[10px]"
                                        ></i>
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        CUSTOMER
                                        <i
                                            class="bi bi-caret-right-fill ml-1 text-[10px]"
                                        ></i>
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        AMOUNT
                                        <i
                                            class="bi bi-caret-right-fill ml-1 text-[10px]"
                                        ></i>
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        PAYMENT METHOD
                                        <i
                                            class="bi bi-caret-right-fill ml-1 text-[10px]"
                                        ></i>
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        DATE
                                        <i
                                            class="bi bi-caret-right-fill ml-1 text-[10px]"
                                        ></i>
                                    </th>

                                    <th class="px-4 py-4 font-medium">
                                        STATUS
                                        <i
                                            class="bi bi-caret-right-fill ml-1 text-[10px]"
                                        ></i>
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="transaction in recentTransactionRows"
                                    :key="transaction.id"
                                    class="border-b border-white/5 text-white last:border-b-0"
                                >
                                    <td class="px-5 sm:px-8 py-4" @click.stop>
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-white/20 bg-white/5 text-[#ff675c] focus:ring-[#ff675c]"
                                            :checked="
                                                selectedRows.includes(
                                                    transaction.id,
                                                )
                                            "
                                            @change="
                                                toggleSelectRow(transaction.id)
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
                                        {{ formatRupiah(transaction.amount) }}
                                    </td>

                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="grid h-9 w-9 place-items-center rounded bg-white/8"
                                            >
                                                <i class="bi bi-bank"></i>
                                            </span>
                                            {{ transaction.payment_method }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-4">
                                        {{ transaction.date }}
                                    </td>

                                    <td class="px-4 py-4">
                                        <span
                                            class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium"
                                            :class="
                                                statusClass(
                                                    transaction.raw_status ||
                                                        transaction.status,
                                                )
                                            "
                                        >
                                            <i
                                                :class="[
                                                    'bi',
                                                    statusIcon(
                                                        transaction.raw_status ||
                                                            transaction.status,
                                                    ),
                                                ]"
                                            ></i>
                                            {{ transaction.status }}
                                        </span>
                                    </td>
                                </tr>

                                <tr v-if="!recentTransactionRows.length">
                                    <td
                                        colspan="7"
                                        class="px-8 py-10 text-center text-white/35"
                                    >
                                        Belum ada transaksi.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="recentTransactions.links?.length > 3"
                        class="my-5 flex flex-wrap justify-center gap-2"
                    >
                        <button
                            v-for="link in recentTransactions.links"
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

<style scoped>
:deep(.apexcharts-canvas),
:deep(.apexcharts-svg) {
    background: transparent !important;
}

:deep(.apexcharts-tooltip) {
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
}

:deep(.apexcharts-gridline) {
    stroke: rgba(255, 255, 255, 0.12) !important;
    stroke-dasharray: 4 !important;
}

:deep(.apexcharts-grid-borders line) {
    stroke: rgba(255, 255, 255, 0.12) !important;
    stroke-dasharray: 4 !important;
}

:deep(.apexcharts-xaxis-tick),
:deep(.apexcharts-yaxis-tick) {
    display: none;
}

:deep(.sales-trend-tooltip) {
    min-width: 190px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    background: #1d1e1c;
    padding: 10px 12px;
    color: white;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.28);
}

:deep(.sales-trend-tooltip__title) {
    margin-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 6px;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.6);
}

:deep(.sales-trend-tooltip__row) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-size: 12px;
}

:deep(.sales-trend-tooltip__row + .sales-trend-tooltip__row) {
    margin-top: 6px;
}

:deep(.sales-trend-tooltip__left) {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.86);
}

:deep(.sales-trend-tooltip__value) {
    font-weight: 600;
    color: white;
}

:deep(.sales-trend-tooltip__dot) {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    display: inline-block;
}

:deep(.sales-trend-tooltip__dot--new) {
    background: #ff675c;
}

:deep(.sales-trend-tooltip__dot--existing) {
    background: #6b7280;
}

.custom-thin-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.18) transparent;
}

.custom-thin-scroll::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}

.custom-thin-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.custom-thin-scroll::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
}

:deep(.revenue-breakdown-tooltip) {
    min-width: 190px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    background: #1d1e1c;
    padding: 10px 12px;
    color: white;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.28);
}

:deep(.revenue-breakdown-tooltip__title) {
    margin-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 6px;
    font-size: 12px;
    font-weight: 600;
    color: white;
}

:deep(.revenue-breakdown-tooltip__row) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-size: 12px;
}

:deep(.revenue-breakdown-tooltip__row + .revenue-breakdown-tooltip__row) {
    margin-top: 6px;
}

:deep(.revenue-breakdown-tooltip__left) {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.86);
}

:deep(.revenue-breakdown-tooltip__value) {
    font-weight: 600;
    color: white;
}

:deep(.revenue-breakdown-tooltip__dot) {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    display: inline-block;
    background: #ff675c;
}
</style>
