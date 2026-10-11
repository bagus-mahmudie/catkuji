<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
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
    csat: {
        type: Object,
        default: () => ({}),
    },
    nps: {
        type: Object,
        default: () => ({}),
    },
    responses: {
        type: Array,
        default: () => [],
    },
    userTrend: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    periodOptions: {
        type: Array,
        default: () => [],
    },
    trendOptions: {
        type: Array,
        default: () => [],
    },
});

const searchTimeout = ref(null);

const filterForm = reactive({
    period: props.filters.period || "day",
    date: props.filters.date || new Date().toISOString().slice(0, 10),
    search: props.filters.search || "",
    trend: props.filters.trend || "dau",
});

const csatChartEl = ref(null);
let csatChart = null;

const csatCategories = computed(() => {
    return props.csat?.chart?.categories || [];
});

const csatScores = computed(() => {
    return (props.csat?.chart?.scores || []).map((value) => {
        return Number(value || 0);
    });
});

const csatResponses = computed(() => {
    return props.csat?.chart?.responses || [];
});

const csatDateLabel = computed(() => {
    return props.csat?.date_label || props.filters?.date_label || "-";
});

const getCsatChartOptions = () => {
    return {
        series: [
            {
                name: "CSAT Score",
                data: csatScores.value,
            },
        ],
        chart: {
            type: "area",
            height: 300,
            fontFamily: "Poppins, sans-serif",
            toolbar: {
                show: false,
            },
            zoom: {
                enabled: false,
            },
            foreColor: "rgba(255,255,255,0.35)",
            background: "transparent",
        },
        colors: ["#ff675c"],
        dataLabels: {
            enabled: false,
        },
        stroke: {
            curve: "smooth",
            width: [1.8],
            lineCap: "round",
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
            hover: {
                size: 4,
            },
        },
        grid: {
            show: true,
            borderColor: "rgba(255,255,255,0.12)",
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
                top: 0,
                right: 8,
                bottom: 0,
                left: 0,
            },
        },
        xaxis: {
            categories: csatCategories.value,
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            labels: {
                rotate: 0,
                trim: true,
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "12px",
                    fontFamily: "Poppins, sans-serif",
                },
            },
            tooltip: {
                enabled: false,
            },
        },
        yaxis: {
            min: 0,
            max: 5,
            tickAmount: 5,
            labels: {
                formatter: (value) => {
                    return Math.round(value);
                },
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "12px",
                    fontFamily: "Poppins, sans-serif",
                },
            },
        },
        tooltip: {
            enabled: true,
            theme: "dark",
            shared: true,
            intersect: false,
            custom: function ({ dataPointIndex }) {
                const score = csatScores.value[dataPointIndex] || 0;
                const responseCount = csatResponses.value[dataPointIndex] || 0;

                return `
                    <div class="csat-custom-tooltip">
                        <div class="csat-tooltip-row">
                            <span class="csat-tooltip-dot csat-tooltip-dot-red"></span>
                            <span>CSAT Score : ${score}/5</span>
                        </div>

                        <div class="csat-tooltip-row">
                            <span class="csat-tooltip-dot csat-tooltip-dot-gray"></span>
                            <span>Responses : ${responseCount} user</span>
                        </div>
                    </div>
                `;
            },
        },
        legend: {
            show: false,
        },
    };
};

const renderCsatChart = async () => {
    await nextTick();

    if (!csatChartEl.value) {
        return;
    }

    if (csatChart) {
        csatChart.updateOptions(getCsatChartOptions(), true, true);
        return;
    }

    csatChart = new ApexCharts(csatChartEl.value, getCsatChartOptions());
    csatChart.render();
};

const npsChartEl = ref(null);
let npsChart = null;

const npsCategories = computed(() => {
    return props.nps?.chart?.categories || [];
});

const npsPromoters = computed(() => {
    return (props.nps?.chart?.promoters || []).map((value) => {
        return Number(value || 0);
    });
});

const npsPassives = computed(() => {
    return (props.nps?.chart?.passives || []).map((value) => {
        return Number(value || 0);
    });
});

const npsDetractors = computed(() => {
    return (props.nps?.chart?.detractors || []).map((value) => {
        return Number(value || 0);
    });
});

const npsPromotersCount = computed(() => {
    return (props.nps?.chart?.promoters_count || []).map((value) => {
        return Number(value || 0);
    });
});

const npsPassivesCount = computed(() => {
    return (props.nps?.chart?.passives_count || []).map((value) => {
        return Number(value || 0);
    });
});

const npsDetractorsCount = computed(() => {
    return (props.nps?.chart?.detractors_count || []).map((value) => {
        return Number(value || 0);
    });
});

const getNpsChartOptions = () => {
    return {
        series: [
            {
                name: "Promoters",
                data: npsPromoters.value,
            },
            {
                name: "Passives",
                data: npsPassives.value,
            },
            {
                name: "Detractors",
                data: npsDetractors.value,
            },
        ],
        chart: {
            type: "area",
            height: 300,
            fontFamily: "Poppins, sans-serif",
            toolbar: {
                show: false,
            },
            zoom: {
                enabled: false,
            },
            foreColor: "rgba(255,255,255,0.35)",
            background: "transparent",
        },
        colors: ["#ff675c", "rgba(255,255,255,0.28)", "rgba(0,0,0,0.35)"],
        dataLabels: {
            enabled: false,
        },
        stroke: {
            curve: "smooth",
            width: [1.8, 1.2, 1.2],
            lineCap: "round",
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
            hover: {
                size: 4,
            },
        },
        grid: {
            show: true,
            borderColor: "rgba(255,255,255,0.12)",
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
                top: 0,
                right: 8,
                bottom: 0,
                left: 0,
            },
        },
        xaxis: {
            categories: npsCategories.value,
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            labels: {
                rotate: 0,
                trim: true,
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "12px",
                    fontFamily: "Poppins, sans-serif",
                },
            },
            tooltip: {
                enabled: false,
            },
        },
        yaxis: {
            min: 0,
            max: 100,
            tickAmount: 4,
            labels: {
                formatter: (value) => Math.round(value),
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "12px",
                    fontFamily: "Poppins, sans-serif",
                },
            },
        },
        tooltip: {
            enabled: true,
            theme: "dark",
            shared: true,
            intersect: false,
            custom: function ({ dataPointIndex }) {
                const promoterCount =
                    npsPromotersCount.value[dataPointIndex] || 0;
                const passiveCount =
                    npsPassivesCount.value[dataPointIndex] || 0;
                const detractorCount =
                    npsDetractorsCount.value[dataPointIndex] || 0;

                const promoterPercent = npsPromoters.value[dataPointIndex] || 0;
                const passivePercent = npsPassives.value[dataPointIndex] || 0;
                const detractorPercent =
                    npsDetractors.value[dataPointIndex] || 0;

                return `
            <div class="nps-custom-tooltip">
                <div class="nps-tooltip-row">
                    <span class="nps-tooltip-dot nps-tooltip-dot-red"></span>
                    <span>Promoter : ${promoterCount} user</span>
                    <span class="nps-tooltip-percent">${Math.round(promoterPercent)}%</span>
                </div>

                <div class="nps-tooltip-row">
                    <span class="nps-tooltip-dot nps-tooltip-dot-gray"></span>
                    <span>Passive : ${passiveCount} user</span>
                    <span class="nps-tooltip-percent">${Math.round(passivePercent)}%</span>
                </div>

                <div class="nps-tooltip-row">
                    <span class="nps-tooltip-dot nps-tooltip-dot-black"></span>
                    <span>Detractor : ${detractorCount} user</span>
                    <span class="nps-tooltip-percent">${Math.round(detractorPercent)}%</span>
                </div>
            </div>
        `;
            },
        },
        legend: {
            show: false,
        },
    };
};

const renderNpsChart = async () => {
    await nextTick();

    if (!npsChartEl.value) {
        return;
    }

    if (npsChart) {
        npsChart.updateOptions(getNpsChartOptions(), true, true);
        return;
    }

    npsChart = new ApexCharts(npsChartEl.value, getNpsChartOptions());
    npsChart.render();
};

const renderUserTrendChart = async () => {
    await nextTick();

    if (!userTrendChartEl.value) {
        return;
    }

    if (userTrendChart) {
        userTrendChart.updateOptions(getUserTrendChartOptions(), true, true);
        return;
    }

    userTrendChart = new ApexCharts(
        userTrendChartEl.value,
        getUserTrendChartOptions(),
    );

    userTrendChart.render();
};

const safePeriodOptions = computed(() =>
    props.periodOptions.length
        ? props.periodOptions
        : [
              { label: "Day", value: "day" },
              { label: "Week", value: "week" },
              { label: "Month", value: "month" },
          ],
);

const safeTrendOptions = computed(() =>
    props.trendOptions.length
        ? props.trendOptions
        : [
              { label: "DAU", value: "dau" },
              { label: "WAU", value: "wau" },
              { label: "MAU", value: "mau" },
          ],
);

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

const applyFilters = () => {
    router.get(
        route("admin.users.index"),
        {
            period: filterForm.period,
            date:
                filterForm.period === "day"
                    ? filterForm.date || new Date().toISOString().slice(0, 10)
                    : undefined,
            search: filterForm.search || undefined,
            trend: filterForm.trend !== "dau" ? filterForm.trend : undefined,
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
    () => filterForm.trend,
    () => {
        applyFilters();
    },
);

const exportCsv = () => {
    window.location.href = route("admin.users.export", {
        period: filterForm.period,
        date:
            filterForm.period === "day"
                ? filterForm.date || new Date().toISOString().slice(0, 10)
                : undefined,
        search: filterForm.search || undefined,
        trend: filterForm.trend,
    });
};

const userTrendChartEl = ref(null);
let userTrendChart = null;

const userTrendCategories = computed(() => {
    return (props.userTrend?.series || []).map((item) => {
        return item.month;
    });
});

const userTrendDateLabels = computed(() => {
    return (props.userTrend?.series || []).map((item) => {
        if (item.is_current && userTrendMode.value === "DAU") {
            return `${item.date_label || item.month} - Today`;
        }

        if (item.is_current && userTrendMode.value === "WAU") {
            return `${item.date_label || item.month} - This Week`;
        }

        return item.date_label || item.month;
    });
});

const userTrendValues = computed(() => {
    return (props.userTrend?.series || []).map((item) => {
        return Number(item.value || 0);
    });
});

const userTrendMode = computed(() => {
    return props.userTrend?.mode || filterForm.trend.toUpperCase();
});

const userTrendAverageLabel = computed(() => {
    if (userTrendMode.value === "DAU") {
        return "Average Daily User :";
    }

    if (userTrendMode.value === "WAU") {
        return "Average Weekly User :";
    }

    return "Average Monthly User :";
});

const userTrendMax = computed(() => {
    const maxValue = Math.max(...userTrendValues.value, 1);

    return Math.max(5, Math.ceil(maxValue + maxValue * 0.2));
});

const getUserTrendChartOptions = () => {
    return {
        series: [
            {
                name: userTrendMode.value,
                data: userTrendValues.value,
            },
        ],
        chart: {
            type: "area",
            height: 300,
            fontFamily: "Poppins, sans-serif",
            toolbar: {
                show: false,
            },
            zoom: {
                enabled: false,
            },
            foreColor: "rgba(255,255,255,0.35)",
            background: "transparent",
        },
        colors: ["#ff675c"],
        dataLabels: {
            enabled: false,
        },
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
            hover: {
                size: 4,
            },
        },
        grid: {
            show: true,
            borderColor: "rgba(255,255,255,0.12)",
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
                top: 0,
                right: 8,
                bottom: 0,
                left: 0,
            },
        },
        xaxis: {
            categories: userTrendCategories.value,
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
            labels: {
                rotate: 0,
                trim: true,
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "12px",
                    fontFamily: "Poppins, sans-serif",
                },
            },
            tooltip: {
                enabled: false,
            },
        },
        yaxis: {
            min: 0,
            max: userTrendMax.value,
            tickAmount: 4,
            labels: {
                formatter: (value) => {
                    return Math.round(value);
                },
                style: {
                    colors: "rgba(255,255,255,0.35)",
                    fontSize: "12px",
                    fontFamily: "Poppins, sans-serif",
                },
            },
        },
        tooltip: {
            enabled: true,
            theme: "dark",
            shared: true,
            intersect: false,
            custom: function ({ dataPointIndex }) {
                const label = userTrendDateLabels.value[dataPointIndex] || "-";
                const value = userTrendValues.value[dataPointIndex] || 0;
                const mode = userTrendMode.value;

                return `
        <div class="trend-custom-tooltip">
            <div class="trend-tooltip-title">${label}</div>

            <div class="trend-tooltip-row">
                <span class="trend-tooltip-dot trend-tooltip-dot-red"></span>
                <span>${mode} : ${value} user</span>
            </div>
        </div>
    `;
            },
        },
        legend: {
            show: false,
        },
    };
};

onMounted(() => {
    renderCsatChart();
    renderNpsChart();
    renderUserTrendChart();
});

onBeforeUnmount(() => {
    if (csatChart) {
        csatChart.destroy();
        csatChart = null;
    }

    if (npsChart) {
        npsChart.destroy();
        npsChart = null;
    }

    if (userTrendChart) {
        userTrendChart.destroy();
        userTrendChart = null;
    }
});

watch(
    () => props.csat,
    () => {
        renderCsatChart();
    },
    { deep: true },
);

watch(
    () => props.nps,
    () => {
        renderNpsChart();
    },
    { deep: true },
);

watch(
    () => props.userTrend,
    () => {
        renderUserTrendChart();
    },
    { deep: true },
);
</script>

<template>
    <Head title="User" />

    <AdminLayout title="User" :breadcrumb="[{ label: 'User' }]">
        <div class="min-w-0 space-y-5">
            <section
                class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
            >
                <div>
                    <h1
                        class="text-xl font-medium leading-tight sm:text-2xl lg:text-3xl"
                    >
                        User
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
                            class="h-10 rounded-lg border border-transparent bg-white/5 px-2 sm:px-5 text-xs sm:text-sm text-white outline-none focus:border-[#ff675c] focus:ring-1 focus:ring-inset"
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

            <section class="grid gap-3 sm:gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in stats"
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
                    </div>
                </article>
            </section>

            <section class="grid min-w-0 gap-4 xl:grid-cols-2 xl:gap-5">
                <article class="min-w-0 rounded-xl bg-[#1d1e1c]">
                    <div class="flex items-center justify-between px-4 py-3">
                        <h2 class="font-medium text-white">
                            Satisfaction Score (CSAT)
                        </h2>

                        <Link
                            :href="
                                route('admin.users.csat.detail', {
                                    period: filterForm.period,
                                    date:
                                        filterForm.period === 'day'
                                            ? filterForm.date
                                            : undefined,
                                })
                            "
                            class="text-sm text-white/35 hover:text-white"
                        >
                            Details
                            <i class="bi bi-arrow-up-right ml-1"></i>
                        </Link>
                    </div>

                    <div class="h-[400px] rounded-xl bg-[#343531] p-3">
                        <div
                            class="flex h-10 items-center justify-between gap-4"
                        >
                            <p class="shrink-0 text-white/35">
                                Score :
                                <span
                                    class="ml-2 text-xl font-medium text-white"
                                >
                                    {{ csat.score || 0 }}
                                </span>
                            </p>

                            <button
                                type="button"
                                class="inline-flex h-10 shrink-0 items-center gap-3 rounded-lg bg-white/5 px-3 sm:px-4 text-xs sm:text-sm text-white/80"
                            >
                                <i class="bi bi-calendar3"></i>
                                {{ csatDateLabel }}
                            </button>
                        </div>

                        <div class="mt-4 h-[300px]">
                            <div ref="csatChartEl" class="h-full w-full"></div>
                        </div>
                    </div>
                </article>

                <article class="min-w-0 rounded-xl bg-[#1d1e1c]">
                    <div class="flex items-center justify-between px-4 py-3">
                        <h2 class="font-medium text-white">
                            Net Promoter Score (NPS)
                        </h2>

                        <Link
                            :href="
                                route('admin.users.nps.detail', {
                                    period: filterForm.period,
                                    date:
                                        filterForm.period === 'day'
                                            ? filterForm.date
                                            : undefined,
                                })
                            "
                            class="text-sm text-white/35 hover:text-white"
                        >
                            Details
                            <i class="bi bi-arrow-up-right ml-1"></i>
                        </Link>
                    </div>

                    <div class="h-[400px] rounded-xl bg-[#343531] p-3">
                        <div
                            class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                        >
                            <p class="shrink-0 text-white/35">
                                Score :
                                <span
                                    class="ml-2 text-xl font-medium text-white"
                                >
                                    {{ nps.score || 0 }}
                                </span>
                            </p>

                            <div
                                class="flex sm:h-10 items-center justify-start sm:justify-end gap-3 text-xs sm:text-sm text-white/35"
                            >
                                <span
                                    class="inline-flex items-center gap-2 whitespace-nowrap"
                                >
                                    <span
                                        class="h-2 w-2 rounded bg-[#ff675c]"
                                    ></span>
                                    Promoters
                                </span>

                                <span
                                    class="inline-flex items-center gap-2 whitespace-nowrap"
                                >
                                    <span
                                        class="h-2 w-2 rounded bg-white/30"
                                    ></span>
                                    Passives
                                </span>

                                <span
                                    class="inline-flex items-center gap-2 whitespace-nowrap"
                                >
                                    <span
                                        class="h-2 w-2 rounded bg-black/30"
                                    ></span>
                                    Detractors
                                </span>
                            </div>
                        </div>

                        <div class="mt-2 sm:mt-4 h-[300px]">
                            <div ref="npsChartEl" class="h-full w-full"></div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid items-start gap-5 sm:grid-cols-2">
                <article
                    class="flex h-[430px] max-h-[430px] min-h-0 flex-col rounded-xl bg-[#1d1e1c]"
                >
                    <div
                        class="flex shrink-0 items-center justify-between px-4 py-3"
                    >
                        <h2 class="font-medium text-white">
                            Responses From Users
                        </h2>

                        <i class="bi bi-info-circle text-white/35"></i>
                    </div>

                    <div
                        class="min-h-0 flex-1 overflow-hidden rounded-xl bg-[#343531]"
                    >
                        <div
                            class="h-full divide-y divide-white/10 overflow-y-auto user-response-scroll"
                        >
                            <div
                                v-for="response in responses"
                                :key="response.id"
                                class="flex items-start gap-4 px-4 py-4"
                            >
                                <img
                                    :src="response.avatar"
                                    :alt="response.name"
                                    class="h-11 w-11 shrink-0 rounded-full object-cover"
                                />

                                <div class="min-w-0 flex-1 pr-3">
                                    <p
                                        class="break-words text-sm leading-6 text-white"
                                    >
                                        {{ response.review }}
                                    </p>

                                    <p
                                        class="mt-1 truncate text-xs text-white/35"
                                    >
                                        {{ response.name }}
                                        <span class="mx-2">•</span>
                                        {{ response.date }}
                                    </p>
                                </div>

                                <span
                                    class="inline-flex shrink-0 items-center gap-2 rounded-full bg-white/5 px-4 py-2 text-sm text-[#ff675c]"
                                >
                                    <i class="bi bi-star-fill"></i>
                                    {{ response.rating || 0 }}
                                </span>
                            </div>

                            <div
                                v-if="!responses.length"
                                class="flex h-full min-h-[260px] items-center justify-center px-4 text-center text-sm text-white/35"
                            >
                                Belum ada response user.
                            </div>
                        </div>
                    </div>
                </article>

                <article
                    class="flex h-[430px] max-h-[430px] min-h-0 flex-col overflow-hidden rounded-xl bg-[#1d1e1c]"
                >
                    <div
                        class="flex shrink-0 items-center justify-between px-4 py-3"
                    >
                        <h2 class="font-medium text-white">
                            User Trend Analysis
                        </h2>

                        <i class="bi bi-info-circle text-white/35"></i>
                    </div>

                    <div class="min-h-0 flex-1 rounded-xl bg-[#343531] p-3">
                        <div class="flex items-center justify-between">
                            <p class="text-white/35">
                                {{ userTrendAverageLabel }}
                                <span
                                    class="sm:ml-2 text-lg sm:text-2xl font-medium text-white"
                                >
                                    {{ userTrend.average || 0 }}
                                </span>
                            </p>

                            <select
                                v-model="filterForm.trend"
                                class="h-10 rounded-lg border-none bg-white/5 px-4 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                            >
                                <option
                                    v-for="option in safeTrendOptions"
                                    :key="option.value"
                                    :value="option.value"
                                    class="bg-[#2b2c29]"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div class="mt-4 h-[280px]">
                            <div
                                ref="userTrendChartEl"
                                class="h-full w-full"
                            ></div>
                        </div>
                    </div>
                </article>
            </section>
        </div>
    </AdminLayout>
</template>
<style scoped>
.user-response-scroll {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.user-response-scroll::-webkit-scrollbar {
    display: none;
}

:deep(.apexcharts-canvas) {
    background: transparent !important;
}

:deep(.apexcharts-tooltip) {
    box-shadow: none !important;
    background: transparent !important;
}

:deep(.apexcharts-tooltip-title) {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    background: #343531 !important;
    color: #ffffff !important;
}

:deep(.apexcharts-tooltip-text) {
    color: #ffffff !important;
}

:deep(.apexcharts-xaxistooltip) {
    display: none !important;
}

:deep(.nps-custom-tooltip) {
    min-width: 150px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    color: rgba(255, 255, 255, 0.82);
    font-size: 12px;
    background: #1d1e1c;
    padding: 10px 12px;
}

:deep(.nps-tooltip-row) {
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 22px;
    white-space: nowrap;
}

:deep(.nps-tooltip-dot) {
    width: 9px;
    height: 9px;
    border-radius: 9999px;
    flex-shrink: 0;
}

:deep(.nps-tooltip-dot-red) {
    background: #ff675c;
}

:deep(.nps-tooltip-dot-gray) {
    background: rgba(255, 255, 255, 0.3);
}

:deep(.nps-tooltip-dot-black) {
    background: rgba(0, 0, 0, 0.7);
}

:deep(.nps-tooltip-percent) {
    margin-left: auto;
    color: rgba(255, 255, 255, 0.42);
}

:deep(.apexcharts-gridline) {
    stroke: rgba(255, 255, 255, 0.18) !important;
    stroke-dasharray: 6 !important;
}

:deep(.apexcharts-xaxis-tick) {
    display: none !important;
}

:deep(.csat-custom-tooltip) {
    min-width: 170px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #1d1e1c;
    color: rgba(255, 255, 255, 0.82);
    font-size: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}

:deep(.csat-tooltip-row) {
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 22px;
    white-space: nowrap;
}

:deep(.csat-tooltip-dot) {
    width: 9px;
    height: 9px;
    border-radius: 9999px;
    flex-shrink: 0;
}

:deep(.csat-tooltip-dot-red) {
    background: #ff675c;
}

:deep(.csat-tooltip-dot-gray) {
    background: rgba(255, 255, 255, 0.3);
}

:deep(.trend-custom-tooltip) {
    min-width: 150px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #1d1e1c;
    color: rgba(255, 255, 255, 0.82);
    font-size: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}

:deep(.trend-tooltip-title) {
    margin-bottom: 6px;
    color: rgba(255, 255, 255, 0.45);
    font-size: 11px;
}

:deep(.trend-tooltip-row) {
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 22px;
    white-space: nowrap;
}

:deep(.trend-tooltip-dot) {
    width: 9px;
    height: 9px;
    border-radius: 9999px;
    flex-shrink: 0;
}

:deep(.trend-tooltip-dot-red) {
    background: #ff675c;
}
</style>
