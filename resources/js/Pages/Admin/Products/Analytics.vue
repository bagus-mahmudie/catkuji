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
    summary: {
        type: Object,
        required: true,
    },
    topCategories: {
        type: Array,
        default: () => [],
    },
    lowStockItems: {
        type: Array,
        default: () => [],
    },
    orderSummary: {
        type: Object,
        default: () => ({
            pending: 0,
            process: 0,
            done: 0,
            chart: [],
        }),
    },
    recentOrders: {
        type: Array,
        default: () => [],
    },
    salesChart: {
        type: Array,
        default: () => [],
    },
    topProducts: {
        type: Array,
        default: () => [],
    },
    performances: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

let searchTimeout = null;

const filterForm = reactive({
    start_date: props.filters.start_date || "",
    end_date: props.filters.end_date || "",
    search: props.filters.search || "",
});

const selectedRows = ref([]);

const allPerformanceProductIds = computed(() =>
    props.performances.data.map((row) => row.id),
);

const isAllSelected = computed(() => {
    return (
        allPerformanceProductIds.value.length > 0 &&
        allPerformanceProductIds.value.every((id) =>
            selectedRows.value.includes(id),
        )
    );
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedRows.value = [];
        return;
    }

    selectedRows.value = [...allPerformanceProductIds.value];
};

const toggleSelectRow = (id) => {
    if (selectedRows.value.includes(id)) {
        selectedRows.value = selectedRows.value.filter((rowId) => rowId !== id);
        return;
    }

    selectedRows.value.push(id);
};

watch(
    () => props.performances.data,
    () => {
        selectedRows.value = selectedRows.value.filter((id) =>
            allPerformanceProductIds.value.includes(id),
        );
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route("admin.products.analytics"),
        {
            search: filterForm.search || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const formatDisplayDate = (date) => {
    if (!date) {
        return "-";
    }

    return new Intl.DateTimeFormat("en-GB", {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(new Date(date));
};

watch(
    () => filterForm.search,
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => applyFilters(), 350);
    },
);

const money = (value) => {
    return `Rp ${new Intl.NumberFormat("id-ID").format(Math.round(value || 0))}`;
};

const number = (value) => {
    return new Intl.NumberFormat("id-ID").format(value || 0);
};

const inventoryStockPercent = computed(() => {
    if (!props.summary.total_stock || props.summary.total_stock <= 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round(
            (props.summary.stock_left / props.summary.total_stock) * 100,
        ),
    );
});

const statusLabel = (status) => {
    return (
        {
            published: "Aktif",
            draft: "Draft",
            ended: "Ended",
            hidden: "Hidden",
        }[status] || status
    );
};

const statusClass = (status) => {
    return (
        {
            published: "bg-emerald-500/15 text-emerald-400",
            draft: "bg-yellow-500/15 text-yellow-300",
            ended: "bg-red-500/15 text-red-300",
            hidden: "bg-white/10 text-white/45",
        }[status] || "bg-white/10 text-white/45"
    );
};

const orderStatusClass = (status) => {
    return (
        {
            pending: "text-[#ff6258]",
            process: "text-gray-400",
            done: "text-gray-500",
            cancelled: "text-red-300",
        }[status] || "text-white/60"
    );
};

const categoryChartColors = [
    "#ff6258",
    "#9ca3af",
    "#4b5563",
    "#f5b55f",
    "#6ee7b7",
    "#93c5fd",
    "#c084fc",
    "#f472b6",
];

const topCategoryMain = computed(() => {
    return (
        props.topCategories?.[0] || {
            name: "Belum ada kategori",
            revenue: 0,
            percentage: 0,
        }
    );
});

const topCategoryTotalRevenue = computed(() => {
    return props.topCategories.reduce(
        (total, category) => total + Number(category.revenue || 0),
        0,
    );
});

const topCategorySeries = computed(() => {
    return props.topCategories.map((category) => Number(category.revenue || 0));
});

const topCategoryLabels = computed(() => {
    return props.topCategories.map((category) => category.name || "Unknown");
});

const topCategoryChartRef = ref(null);
const topCategoryChart = ref(null);

const getTopCategoryChartOptions = () => {
    return {
        series: topCategorySeries.value,
        labels: topCategoryLabels.value,
        colors: categoryChartColors,
        chart: {
            type: "donut",
            height: 175,
            width: "100%",
            background: "transparent",
            toolbar: {
                show: false,
            },
        },
        stroke: {
            show: false,
        },
        dataLabels: {
            enabled: false,
        },
        legend: {
            show: false,
        },
        tooltip: {
            enabled: true,
            custom: function ({ series, seriesIndex }) {
                const category = props.topCategories?.[seriesIndex] || {};
                const color =
                    categoryChartColors[
                        seriesIndex % categoryChartColors.length
                    ];

                const name = category.name || "Unknown Category";
                const revenue = Number(category.revenue || 0);
                const percentage = Number(category.percentage || 0);
                const played = Number(category.played || 0);

                return `
            <div class="top-category-tooltip">
                <div class="top-category-tooltip__title">
                    <span
                        class="top-category-tooltip__dot"
                        style="background:${color}"
                    ></span>
                    <span>${name}</span>
                </div>

                <div class="top-category-tooltip__row">
                    <span class="top-category-tooltip__label">Revenue</span>
                    <span class="top-category-tooltip__value">${money(revenue)}</span>
                </div>

                <div class="top-category-tooltip__row">
                    <span class="top-category-tooltip__label">Persentase</span>
                    <span class="top-category-tooltip__value">${percentage}%</span>
                </div>

                <div class="top-category-tooltip__row">
                    <span class="top-category-tooltip__label">Total Played</span>
                    <span class="top-category-tooltip__value">${number(played)}</span>
                </div>
            </div>
        `;
            },
        },
        plotOptions: {
            pie: {
                expandOnClick: false,
                donut: {
                    size: "80%",
                    labels: {
                        show: false,
                    },
                },
            },
        },
        states: {
            hover: {
                filter: {
                    type: "lighten",
                    value: 0.08,
                },
            },
            active: {
                filter: {
                    type: "none",
                },
            },
        },
    };
};

const renderTopCategoryChart = async () => {
    await nextTick();

    if (!topCategoryChartRef.value) {
        return;
    }

    if (topCategoryChart.value) {
        topCategoryChart.value.destroy();
        topCategoryChart.value = null;
    }

    if (!topCategorySeries.value.length || topCategoryTotalRevenue.value <= 0) {
        return;
    }

    topCategoryChart.value = new ApexCharts(
        topCategoryChartRef.value,
        getTopCategoryChartOptions(),
    );

    topCategoryChart.value.render();
};

const orderSummaryChartRef = ref(null);
const orderSummaryChart = ref(null);

const getOrderSummaryChartOptions = () => {
    return {
        series: [
            {
                name: "Pending",
                data:
                    props.orderSummary.chart?.map((item) =>
                        Number(item.pending || 0),
                    ) || [],
            },
            {
                name: "Process",
                data:
                    props.orderSummary.chart?.map((item) =>
                        Number(item.process || 0),
                    ) || [],
            },
            {
                name: "Done",
                data:
                    props.orderSummary.chart?.map((item) =>
                        Number(item.done || 0),
                    ) || [],
            },
        ],
        chart: {
            type: "area",
            height: 150,
            width: "100%",
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
        colors: ["#ff6258", "#9ca3af", "#4b5563"],
        stroke: {
            curve: "smooth",
            width: 2,
        },
        fill: {
            type: "gradient",
            fill: {
                type: "gradient",
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.12,
                    stops: [0, 100],
                },
            },
        },
        markers: {
            size: 0,
            strokeWidth: 0,
            hover: {
                size: 4,
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
            borderColor: "rgba(255,255,255,0.22)",
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
                right: 6,
                bottom: 0,
                left: 6,
            },
        },
        xaxis: {
            categories:
                props.orderSummary.chart?.map((item) => item.label) || [],
            labels: {
                show: true,
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
            show: false,
            min: 0,
            tickAmount: 3,
            forceNiceScale: true,
        },
        tooltip: {
            shared: true,
            intersect: false,
            custom: function ({ series, dataPointIndex, w }) {
                const point = props.salesChart?.[dataPointIndex] || {};

                const label = point.label || point.date || "";

                const pending = series?.[0]?.[dataPointIndex] ?? 0;
                const process = series?.[1]?.[dataPointIndex] ?? 0;
                const done = series?.[2]?.[dataPointIndex] ?? 0;

                return `
            <div class="order-summary-tooltip">
                <div class="order-summary-tooltip__title">${label}</div>

                <div class="order-summary-tooltip__row">
                    <span class="order-summary-tooltip__left">
                        <span class="order-summary-tooltip__dot order-summary-tooltip__dot--pending"></span>
                        Pending
                    </span>
                    <span class="order-summary-tooltip__value">${pending}</span>
                </div>

                <div class="order-summary-tooltip__row">
                    <span class="order-summary-tooltip__left">
                        <span class="order-summary-tooltip__dot order-summary-tooltip__dot--process"></span>
                        Process
                    </span>
                    <span class="order-summary-tooltip__value">${process}</span>
                </div>

                <div class="order-summary-tooltip__row">
                    <span class="order-summary-tooltip__left">
                        <span class="order-summary-tooltip__dot order-summary-tooltip__dot--done"></span>
                        Done
                    </span>
                    <span class="order-summary-tooltip__value">${done}</span>
                </div>
            </div>
        `;
            },
        },
    };
};

const renderOrderSummaryChart = async () => {
    await nextTick();

    if (!orderSummaryChartRef.value) {
        return;
    }

    if (orderSummaryChart.value) {
        orderSummaryChart.value.destroy();
        orderSummaryChart.value = null;
    }

    orderSummaryChart.value = new ApexCharts(
        orderSummaryChartRef.value,
        getOrderSummaryChartOptions(),
    );

    orderSummaryChart.value.render();
};

const totalSalesChartRef = ref(null);
const totalSalesChart = ref(null);

const getTotalSalesChartOptions = () => {
    return {
        series: [
            {
                name: "Revenue",
                data:
                    props.salesChart?.map((item) =>
                        Number(item.revenue || 0),
                    ) || [],
            },
            {
                name: "Total Played",
                data:
                    props.salesChart?.map((item) => Number(item.played || 0)) ||
                    [],
            },
        ],
        chart: {
            type: "area",
            height: 240,
            width: "100%",
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
        colors: ["#ff6258", "#6b7280"],
        stroke: {
            curve: "smooth",
            width: [2, 1.5],
        },
        fill: {
            type: "gradient",
            fill: {
                type: "gradient",
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.12,
                    stops: [0, 100],
                },
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
            borderColor: "rgba(255,255,255,0.16)",
            strokeDashArray: 6,
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
            categories: props.salesChart?.map((item) => item.label) || [],
            labels: {
                show: true,
                rotate: 0,
                trim: false,
                hideOverlappingLabels: false,
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "10px",
                },
                formatter: function (value) {
                    const labels =
                        props.salesChart?.map((item) => item.label) || [];
                    const lastLabel = labels[labels.length - 1];

                    const dayNumber = Number.parseInt(
                        String(value).split(" ")[0],
                        10,
                    );

                    if (!dayNumber) {
                        return value;
                    }

                    const isEveryTwoDaysFromStart = dayNumber % 2 === 1;
                    const isLastDate = value === lastLabel;

                    return isEveryTwoDaysFromStart || isLastDate ? value : "";
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
            forceNiceScale: true,
            labels: {
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "11px",
                },
                formatter: (value) => {
                    if (value >= 1000000) {
                        return `${Math.round(value / 1000000)}M`;
                    }

                    if (value >= 1000) {
                        return `${Math.round(value / 1000)}K`;
                    }

                    return Math.round(value);
                },
            },
        },
        tooltip: {
            shared: true,
            intersect: false,
            custom: function ({ series, dataPointIndex }) {
                const point = props.salesChart?.[dataPointIndex] || {};

                const label =
                    point.tooltip_label || point.label || point.date || "";

                const revenue =
                    Number(series?.[0]?.[dataPointIndex] ?? 0) * 1000;
                const played = series?.[1]?.[dataPointIndex] ?? 0;

                return `
            <div class="sales-chart-tooltip">
                <div class="sales-chart-tooltip__title">${label}</div>

                <div class="sales-chart-tooltip__row">
                    <span class="sales-chart-tooltip__left">
                        <span class="sales-chart-tooltip__dot sales-chart-tooltip__dot--revenue"></span>
                        Revenue
                    </span>
                    <span class="sales-chart-tooltip__value">${money(revenue)}</span>
                </div>

                <div class="sales-chart-tooltip__row">
                    <span class="sales-chart-tooltip__left">
                        <span class="sales-chart-tooltip__dot sales-chart-tooltip__dot--played"></span>
                        Total Played
                    </span>
                    <span class="sales-chart-tooltip__value">${number(played)}</span>
                </div>
            </div>
        `;
            },
        },
    };
};

const renderTotalSalesChart = async () => {
    await nextTick();

    if (!totalSalesChartRef.value) {
        return;
    }

    if (totalSalesChart.value) {
        totalSalesChart.value.destroy();
        totalSalesChart.value = null;
    }

    totalSalesChart.value = new ApexCharts(
        totalSalesChartRef.value,
        getTotalSalesChartOptions(),
    );

    totalSalesChart.value.render();
};

onMounted(() => {
    renderTopCategoryChart();
    renderOrderSummaryChart();
    renderTotalSalesChart();
});

watch(
    () => props.topCategories,
    () => {
        renderTopCategoryChart();
    },
    { deep: true },
);

watch(
    () => props.orderSummary,
    () => {
        renderOrderSummaryChart();
    },
    { deep: true },
);

watch(
    () => props.salesChart,
    () => {
        renderTotalSalesChart();
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (topCategoryChart.value) {
        topCategoryChart.value.destroy();
        topCategoryChart.value = null;
    }

    if (orderSummaryChart.value) {
        orderSummaryChart.value.destroy();
        orderSummaryChart.value = null;
    }

    if (totalSalesChart.value) {
        totalSalesChart.value.destroy();
        totalSalesChart.value = null;
    }
});

const growthPrefix = (value) => {
    const numericValue = Number(value || 0);

    if (numericValue > 0) {
        return "▲ +";
    }

    if (numericValue < 0) {
        return "▼ ";
    }

    return "";
};

const growthClass = (value) => {
    return Number(value || 0) >= 0 ? "text-emerald-400" : "text-[#ff675c]";
};
</script>

<template>
    <Head title="Product Analytics" />

    <AdminLayout
        title="Product Analytics"
        :breadcrumb="[{ label: 'Product' }, { label: 'Product Analytics' }]"
    >
        <div class="min-w-0 space-y-3 sm:space-y-5">
            <section
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <h1
                    class="text-xl font-medium leading-tight sm:text-2xl lg:text-3xl"
                >
                    Product Analytics
                </h1>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-4">
                <div class="space-y-3 sm:space-y-5">
                    <div class="rounded-xl bg-[#1d1e1c]">
                        <div
                            class="flex items-center justify-between px-4 py-3"
                        >
                            <h2 class="text-base text-white">Total Product</h2>
                            <i class="bi bi-info-circle text-white/35"></i>
                        </div>

                        <div class="rounded-xl bg-white/10 p-3">
                            <p class="text-sm text-white/35">
                                Active product/event
                            </p>

                            <p class="mt-3 text-2xl font-medium text-white">
                                {{ number(summary.total_products) }}
                            </p>

                            <div
                                class="mt-4 rounded-lg bg-white/5 px-3 py-2 text-sm text-white"
                            >
                                <span
                                    :class="
                                        growthClass(
                                            summary.active_products_growth,
                                        )
                                    "
                                >
                                    {{
                                        growthPrefix(
                                            summary.active_products_growth,
                                        )
                                    }}{{ summary.active_products_growth }}%
                                </span>
                                <span> vs Last Week</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-[#1d1e1c]">
                        <div
                            class="flex items-center justify-between px-4 py-3"
                        >
                            <h2 class="text-base text-white">Total Category</h2>
                            <i class="bi bi-info-circle text-white/35"></i>
                        </div>

                        <div class="rounded-xl bg-white/10 p-3">
                            <p class="text-sm text-white/35">Active Category</p>

                            <p class="mt-3 text-2xl font-medium text-white">
                                {{ number(summary.total_categories) }}
                            </p>

                            <div
                                class="mt-4 rounded-lg bg-white/5 px-3 py-2 text-sm text-white"
                            >
                                <span
                                    :class="
                                        growthClass(summary.category_growth)
                                    "
                                >
                                    {{ growthPrefix(summary.category_growth)
                                    }}{{ summary.category_growth }}%
                                </span>
                                <span> vs Last Week</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-[#1d1e1c] max-h-fit">
                    <div class="flex items-center justify-between px-4 py-3">
                        <h2 class="text-base text-white">Top Category</h2>
                        <i class="bi bi-info-circle text-white/35"></i>
                    </div>

                    <div class="rounded-xl bg-white/10 px-3 py-3">
                        <div class="relative mx-auto h-[175px] w-full">
                            <div
                                v-if="topCategoryTotalRevenue > 0"
                                ref="topCategoryChartRef"
                                class="h-[175px] w-full"
                            ></div>

                            <div
                                v-else
                                class="grid h-[175px] place-items-center text-sm text-white/35"
                            >
                                Belum ada revenue category
                            </div>

                            <div
                                v-if="topCategoryTotalRevenue > 0"
                                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center"
                            >
                                <p class="text-xs text-white/35">Revenue</p>

                                <p
                                    class="mt-1 text-base font-medium leading-none text-white"
                                >
                                    {{ money(topCategoryMain.revenue || 0) }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="mt-3 max-h-[140px] space-y-3 p-3 overflow-y-auto pr-1 custom-thin-scroll"
                        >
                            <div
                                v-for="(category, index) in topCategories"
                                :key="category.id"
                                class="flex items-center justify-between border-b border-white/5 pb-3 text-sm last:border-b-0"
                            >
                                <div
                                    class="flex min-w-0 items-center gap-2 text-white/35"
                                >
                                    <span
                                        class="h-1.5 w-1.5 shrink-0"
                                        :style="{
                                            backgroundColor:
                                                categoryChartColors[
                                                    index %
                                                        categoryChartColors.length
                                                ],
                                        }"
                                    ></span>

                                    <span class="truncate">
                                        {{ category.name }}
                                    </span>
                                </div>

                                <span class="shrink-0 pl-3 text-white">
                                    {{ category.percentage }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-[#1d1e1c] max-h-fit">
                    <div class="flex items-center justify-between px-4 py-3">
                        <h2 class="text-base text-white">Inventory Status</h2>
                        <i class="bi bi-info-circle text-white/35"></i>
                    </div>

                    <div class="rounded-xl bg-white/10 p-3">
                        <div
                            class="flex items-center justify-between text-white"
                        >
                            <div>
                                <p class="text-white/35">Total Stock :</p>
                                <p class="mt-1 text-xs text-white/35">
                                    Stock tersedia dari total stock event
                                </p>
                            </div>

                            <p class="text-2xl font-semibold">
                                {{ number(summary.total_stock) }}
                            </p>
                        </div>

                        <div class="group relative mt-4">
                            <div
                                class="h-2 overflow-hidden rounded-full bg-white/10"
                            >
                                <div
                                    class="h-full rounded-full bg-[#ff6258] transition-all duration-300"
                                    :style="{
                                        width: `${inventoryStockPercent}%`,
                                    }"
                                ></div>
                            </div>

                            <div
                                class="pointer-events-none absolute left-1/2 top-[-78px] z-20 w-[230px] -translate-x-1/2 rounded-xl bg-[#1d1e1c] px-3 py-2 text-xs text-white opacity-0 shadow-xl transition group-hover:opacity-100"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-white/45"
                                        >Stock tersedia</span
                                    >
                                    <span class="font-medium">
                                        {{ number(summary.stock_left) }}
                                    </span>
                                </div>

                                <div
                                    class="mt-1 flex items-center justify-between"
                                >
                                    <span class="text-white/45"
                                        >Total stock</span
                                    >
                                    <span class="font-medium">
                                        {{ number(summary.total_stock) }}
                                    </span>
                                </div>

                                <div
                                    class="mt-1 flex items-center justify-between"
                                >
                                    <span class="text-white/45"
                                        >Persentase</span
                                    >
                                    <span class="font-medium text-[#ff6258]">
                                        {{ inventoryStockPercent }}%
                                    </span>
                                </div>

                                <div
                                    class="absolute -bottom-1 left-1/2 h-2 w-2 -translate-x-1/2 rotate-45 bg-[#1d1e1c]"
                                ></div>
                            </div>

                            <div
                                class="mt-2 flex items-center justify-between text-xs text-white/35"
                            >
                                <span>
                                    Tersedia: {{ number(summary.stock_left) }}
                                </span>
                                <span> {{ inventoryStockPercent }}% </span>
                            </div>
                        </div>

                        <div
                            class="mt-4 grid grid-cols-2 rounded-lg bg-white/5 p-3"
                        >
                            <div>
                                <p class="text-sm text-white/35">Low Stock</p>
                                <p class="mt-2 text-2xl font-medium text-white">
                                    {{ number(summary.low_stock_count) }}
                                </p>
                            </div>
                            <div class="border-l border-white/10 pl-6">
                                <p class="text-sm text-white/35">Expired</p>
                                <p class="mt-2 text-2xl font-medium text-white">
                                    {{ number(summary.expired_count) }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="mt-3 max-h-[123px] space-y-3 overflow-y-auto pr-1 custom-thin-scroll"
                        >
                            <div
                                v-for="item in lowStockItems"
                                :key="item.id"
                                class="rounded-lg bg-white/5 p-3"
                            >
                                <div class="flex justify-between text-sm">
                                    <p class="text-white">{{ item.name }}</p>
                                    <p class="text-[#ff6258]">
                                        {{ item.status }}
                                    </p>
                                </div>
                                <p class="mt-1 text-xs text-white/35">
                                    Stok : {{ item.stock_left }}
                                </p>
                            </div>

                            <p
                                v-if="!lowStockItems.length"
                                class="rounded-lg h-[123px] text-center bg-white/5 p-3 text-sm text-white/35"
                            >
                                Tidak ada produk low stock.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-[#1d1e1c] max-h-fit">
                    <div class="flex items-center justify-between px-4 py-3">
                        <h2 class="text-base text-white">Order Summary</h2>
                        <i class="bi bi-info-circle text-white/35"></i>
                    </div>

                    <div class="rounded-xl bg-white/10 p-3">
                        <div class="grid grid-cols-3">
                            <div>
                                <p class="text-sm text-white/35">Pending</p>
                                <p
                                    class="mt-2 text-2xl font-semibold text-white"
                                >
                                    {{ number(orderSummary.pending) }}
                                </p>
                            </div>

                            <div class="border-l border-white/10 px-5">
                                <p class="text-sm text-white/35">Process</p>
                                <p
                                    class="mt-2 text-2xl font-semibold text-white"
                                >
                                    {{ number(orderSummary.process) }}
                                </p>
                            </div>

                            <div class="border-l border-white/10 pl-5">
                                <p class="text-sm text-white/35">Done</p>
                                <p
                                    class="mt-2 text-2xl font-semibold text-white"
                                >
                                    {{ number(orderSummary.done) }}
                                </p>
                            </div>
                        </div>

                        <div class="h-[150px]">
                            <div
                                v-if="orderSummary.chart?.length"
                                ref="orderSummaryChartRef"
                                class="h-[150px] w-full"
                            ></div>

                            <div
                                v-else
                                class="grid h-[150px] place-items-center text-sm text-white/35"
                            >
                                Belum ada data order minggu ini.
                            </div>
                        </div>

                        <div
                            class="mt-0 flex flex-wrap items-center gap-3 text-xs text-white/35"
                        >
                            <span>↗ 7-Day Order trend</span>
                        </div>

                        <div
                            class="mt-4 max-h-[85px] space-y-2 overflow-y-auto pr-1 custom-thin-scroll"
                        >
                            <div
                                v-for="order in recentOrders"
                                :key="order.id"
                                class="rounded-lg bg-white/5 p-3"
                            >
                                <div
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="truncate font-medium text-white"
                                        >
                                            {{ order.customer_name }}
                                        </p>
                                        <p
                                            class="mt-1 truncate text-xs text-white/35"
                                        >
                                            {{ order.order_no }}
                                        </p>
                                    </div>

                                    <p
                                        class="shrink-0 text-sm font-medium"
                                        :class="orderStatusClass(order.status)"
                                    >
                                        <i
                                            v-if="order.status === 'done'"
                                            class="bi bi-check-circle-fill mr-1"
                                        ></i>
                                        {{ order.status_label }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="!recentOrders.length"
                                class="rounded-lg bg-white/5 p-3 text-sm text-white/35"
                            >
                                Belum ada order terbaru.
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_365px]">
                <div class="rounded-xl bg-[#1d1e1c] max-h-fit">
                    <div class="flex items-center justify-between px-4 py-3">
                        <h2 class="text-base text-white">Total Sales</h2>
                        <i class="bi bi-info-circle text-white/35"></i>
                    </div>

                    <div class="rounded-xl bg-white/10 p-3">
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="flex gap-10 text-center">
                                <div>
                                    <p class="text-2xl font-medium text-white">
                                        {{ number(summary.current_played) }}
                                    </p>
                                    <p class="mt-2 text-sm text-white/35">
                                        This Month
                                    </p>
                                </div>
                                <div>
                                    <p class="text-2xl font-medium text-white">
                                        {{ number(summary.previous_played) }}
                                    </p>
                                    <p class="mt-2 text-sm text-white/35">
                                        Last Month
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex h-10 items-center gap-3 rounded-lg bg-white/10 px-4 text-sm text-white"
                            >
                                <i class="bi bi-calendar4-week"></i>

                                <span class="font-medium">
                                    {{ formatDisplayDate(filters.start_date) }}
                                </span>

                                <span class="text-white/35">-</span>

                                <span class="font-medium">
                                    {{ formatDisplayDate(filters.end_date) }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-5 h-[260px]">
                            <div
                                v-if="salesChart?.length"
                                ref="totalSalesChartRef"
                                class="h-[260px] w-full"
                            ></div>

                            <div
                                v-else
                                class="grid h-[260px] place-items-center text-sm text-white/35"
                            >
                                Belum ada data sales pada periode ini.
                            </div>
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-3 text-sm text-white/35"
                        >
                            <span class="inline-flex items-center gap-1">
                                <span
                                    class="h-2 w-2 rounded-full bg-[#ff6258]"
                                ></span>
                                Revenue
                            </span>

                            <span class="inline-flex items-center gap-1">
                                <span
                                    class="h-2 w-2 rounded-full bg-gray-500"
                                ></span>
                                Total Played
                            </span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-[#1d1e1c] max-h-fit">
                    <div class="flex items-center justify-between px-4 py-3">
                        <h2 class="text-base text-white">Top Product</h2>
                        <i class="bi bi-info-circle text-white/35"></i>
                    </div>

                    <div
                        class="max-h-[384px] space-y-4 overflow-y-auto rounded-xl bg-white/10 p-3 pr-2 custom-thin-scroll"
                    >
                        <div
                            v-for="product in topProducts"
                            :key="product.id"
                            class="border-b border-white/10 pb-4 last:border-b-0"
                        >
                            <div class="flex min-h-[70px] gap-3">
                                <img
                                    :src="product.image_url"
                                    :alt="product.name"
                                    class="h-[70px] w-[70px] shrink-0 self-stretch rounded-lg object-cover"
                                />

                                <div
                                    class="flex min-w-0 flex-1 flex-col justify-between"
                                >
                                    <div>
                                        <div
                                            class="flex items-start justify-between gap-3"
                                        >
                                            <p
                                                class="line-clamp-1 font-medium text-white"
                                            >
                                                {{ product.name }}
                                            </p>

                                            <p class="shrink-0 text-white/70">
                                                {{ product.stock_left }}/{{
                                                    product.stock_total
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="mt-3 h-2 overflow-hidden rounded-full bg-white/10"
                                        >
                                            <div
                                                class="h-full rounded-full bg-[#ff6258]"
                                                :style="{
                                                    width:
                                                        product.stock_total > 0
                                                            ? `${Math.min(100, (product.stock_left / product.stock_total) * 100)}%`
                                                            : '0%',
                                                }"
                                            ></div>
                                        </div>
                                    </div>

                                    <p class="mt-1 text-sm text-white/35">
                                        {{
                                            money(
                                                Number(product.revenue || 0) *
                                                    1000,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <p
                            v-if="!topProducts.length"
                            class="text-sm text-white/35"
                        >
                            Belum ada data top product.
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl bg-[#1d1e1c] max-h-fit">
                <div
                    class="flex items-center justify-between gap-3 px-3 py-3 sm:px-4"
                >
                    <h2 class="text-base text-white">Product Performance</h2>

                    <div
                        class="flex items-center justify-between gap-3 px-3 py-3 sm:px-4"
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
                                    <th class="w-16 px-4 py-4">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-white/20 bg-white/5 text-[#ff6258] focus:ring-[#ff6258]"
                                            :checked="isAllSelected"
                                            @change="toggleSelectAll"
                                        />
                                    </th>
                                    <th class="px-4 py-4 font-medium">
                                        Product Name
                                    </th>
                                    <th class="px-4 py-4 font-medium">Price</th>
                                    <th class="px-4 py-4 font-medium">
                                        Total Played
                                    </th>
                                    <th class="px-4 py-4 font-medium">
                                        Revenue
                                    </th>
                                    <th class="px-4 py-4 font-medium">Stock</th>
                                    <th class="px-4 py-4 font-medium">
                                        Status
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="product in performances.data"
                                    :key="product.id"
                                    class="border-b border-white/5 text-white last:border-b-0"
                                >
                                    <td class="px-4 py-4" @click.stop>
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-white/20 bg-white/5 text-[#ff6258] focus:ring-[#ff6258]"
                                            :checked="
                                                selectedRows.includes(
                                                    product.id,
                                                )
                                            "
                                            @change="
                                                toggleSelectRow(product.id)
                                            "
                                        />
                                    </td>

                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <img
                                                :src="product.image_url"
                                                :alt="product.name"
                                                class="h-12 w-12 rounded-md object-cover"
                                            />
                                            <div>
                                                <p>{{ product.name }}</p>
                                                <p
                                                    class="text-xs text-white/35"
                                                >
                                                    {{
                                                        product.category || "-"
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-4 py-4">
                                        {{ number(product.price) }} Coin
                                    </td>
                                    <td class="px-4 py-4">
                                        {{ number(product.played) }} Played
                                    </td>
                                    <td class="px-4 py-4">
                                        {{ money(product.revenue) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        {{ product.stock_left }}/{{
                                            product.stock_total
                                        }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <span
                                            class="inline-flex min-w-[110px] justify-center rounded-full px-4 py-2 text-xs"
                                            :class="statusClass(product.status)"
                                        >
                                            <i
                                                v-if="
                                                    product.status ===
                                                    'published'
                                                "
                                                class="bi bi-check-circle-fill mr-2"
                                            ></i>
                                            {{ statusLabel(product.status) }}
                                        </span>
                                    </td>
                                </tr>

                                <tr v-if="!performances.data.length">
                                    <td
                                        colspan="7"
                                        class="px-4 py-10 text-center text-white/35"
                                    >
                                        Belum ada data product performance.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="performances.links?.length > 3"
                        class="my-5 flex flex-wrap justify-center gap-2"
                    >
                        <button
                            v-for="link in performances.links"
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
            </section>
        </div>
    </AdminLayout>
</template>

<style scoped>
.custom-thin-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.18) transparent;
}

.custom-thin-scroll::-webkit-scrollbar {
    width: 4px;
}

.custom-thin-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.custom-thin-scroll::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
}

:deep(.apexcharts-canvas),
:deep(.apexcharts-svg) {
    background: transparent !important;
}

:deep(.apexcharts-tooltip) {
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
}

:deep(.order-summary-tooltip) {
    min-width: 150px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    background: #1d1e1c;
    padding: 10px 12px;
    color: white;
}

:deep(.order-summary-tooltip__title) {
    margin-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 6px;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.6);
}

:deep(.order-summary-tooltip__row) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-size: 12px;
}

:deep(.order-summary-tooltip__row + .order-summary-tooltip__row) {
    margin-top: 6px;
}

:deep(.order-summary-tooltip__left) {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.86);
}

:deep(.order-summary-tooltip__value) {
    font-weight: 600;
    color: white;
}

:deep(.order-summary-tooltip__dot) {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    display: inline-block;
}

:deep(.order-summary-tooltip__dot--pending) {
    background: #ff6258;
}

:deep(.order-summary-tooltip__dot--process) {
    background: #9ca3af;
}

:deep(.order-summary-tooltip__dot--done) {
    background: #4b5563;
}

:deep(.apexcharts-gridline) {
    stroke: rgba(255, 255, 255, 0.14) !important;
    stroke-dasharray: 4 !important;
}

:deep(.apexcharts-grid-borders line) {
    stroke: rgba(255, 255, 255, 0.14) !important;
    stroke-dasharray: 4 !important;
}

:deep(.apexcharts-xaxis-tick),
:deep(.apexcharts-yaxis-tick) {
    display: none;
}

:deep(.top-category-tooltip) {
    min-width: 190px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    background: #1d1e1c;
    padding: 10px 12px;
    color: white;
}

:deep(.top-category-tooltip__title) {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 7px;
    font-size: 12px;
    font-weight: 600;
    color: white;
}

:deep(.top-category-tooltip__dot) {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    display: inline-block;
    flex-shrink: 0;
}

:deep(.top-category-tooltip__row) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-size: 12px;
}

:deep(.top-category-tooltip__row + .top-category-tooltip__row) {
    margin-top: 6px;
}

:deep(.top-category-tooltip__label) {
    color: rgba(255, 255, 255, 0.45);
}

:deep(.top-category-tooltip__value) {
    font-weight: 600;
    color: white;
}

:deep(.sales-chart-tooltip) {
    min-width: 180px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    background: #1d1e1c;
    padding: 10px 12px;
    color: white;
}

:deep(.sales-chart-tooltip__title) {
    margin-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 6px;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.6);
}

:deep(.sales-chart-tooltip__row) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-size: 12px;
}

:deep(.sales-chart-tooltip__row + .sales-chart-tooltip__row) {
    margin-top: 6px;
}

:deep(.sales-chart-tooltip__left) {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.86);
}

:deep(.sales-chart-tooltip__value) {
    font-weight: 600;
    color: white;
}

:deep(.sales-chart-tooltip__dot) {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    display: inline-block;
}

:deep(.sales-chart-tooltip__dot--revenue) {
    background: #ff6258;
}

:deep(.sales-chart-tooltip__dot--played) {
    background: #6b7280;
}
</style>
