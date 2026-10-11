<script setup>
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const props = defineProps({
    title: {
        type: String,
        default: "Dashboard",
    },
    breadcrumb: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const sidebarOpen = ref(false);
const productMenuOpen = ref(route().current("admin.products.*"));

const marketingMenuOpen = ref(route().current("admin.marketing.*"));

const user = computed(() => page.props.auth?.user ?? null);

const menuGroups = [
    {
        label: "Main Menu",
        items: [
            {
                label: "Dashboard",
                icon: "bi-grid",
                routeName: "admin.dashboard",
                activeRoutes: ["admin.dashboard", "admin.dashboard.index"],
            },
            {
                label: "Product",
                icon: "bi-box-seam",
                routeName: "admin.products.index",
                activeRoutes: ["admin.products.*"],
                children: [
                    {
                        label: "Add Product",
                        routeName: "admin.products.create",
                    },
                    {
                        label: "Product Analytics",
                        routeName: "admin.products.analytics",
                    },
                ],
            },
            {
                label: "Finance",
                icon: "bi-wallet2",
                routeName: "admin.payments.index",
            },
            {
                label: "Orders",
                icon: "bi-truck",
                routeName: "admin.orders.index",
            },
            {
                label: "User",
                icon: "bi-person",
                routeName: "admin.users.index",
                activeRoutes: ["admin.users.*"],
            },
            {
                label: "Marketing",
                icon: "bi-box-seam",
                routeName: null,
                activeRoutes: ["admin.marketing.*"],
                children: [
                    {
                        label: "Banner Promo",
                        routeName: "admin.marketing.banners.index",
                    },
                    {
                        label: "Notes",
                        routeName: "admin.marketing.notes.index",
                    },
                ],
            },
        ],
    },
];

const isRouteAvailable = (routeName) => {
    try {
        route(routeName);
        return true;
    } catch {
        return false;
    }
};

const isActive = (item) => {
    if (item.activeRoutes?.some((name) => route().current(name))) {
        return true;
    }

    if (item.routeName && route().current(item.routeName)) {
        return true;
    }

    if (item.children?.length) {
        return item.children.some(
            (child) => child.routeName && route().current(child.routeName),
        );
    }

    return false;
};

const isChildActive = (child) => {
    return child.routeName && route().current(child.routeName);
};

const isProductItem = (item) => {
    return item.routeName === "admin.products.index";
};

const isMarketingItem = (item) => {
    return item.label === "Marketing";
};

const isMarketingParentActive = (item) => {
    if (!isMarketingItem(item)) {
        return false;
    }

    return route().current("admin.marketing.*");
};

const isMarketingSectionOpen = (item) => {
    if (!isMarketingItem(item)) {
        return false;
    }

    return marketingMenuOpen.value || isMarketingParentActive(item);
};

const isProductParentActive = (item) => {
    if (!isProductItem(item)) {
        return false;
    }

    return route().current("admin.products.*");
};

const isProductSectionOpen = (item) => {
    if (!isProductItem(item)) {
        return false;
    }

    return productMenuOpen.value || route().current("admin.products.*");
};

const hrefFor = (routeName) => {
    if (!routeName || !isRouteAvailable(routeName)) {
        return "#";
    }

    return route(routeName);
};

const logout = () => {
    router.post(
        route("admin.logout"),
        {},
        {
            replace: true,
            onSuccess: () => {
                window.location.href = route("admin.login");
            },
        },
    );
};
</script>

<template>
    <div class="min-h-screen bg-[#262724] text-white">
        <aside
            class="fixed inset-y-0 left-0 z-40 w-[292px] max-w-[86vw] border-r border-white/10 bg-[#1d1e1c] transition-transform duration-300 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-full flex-col">
                <div
                    class="flex items-center justify-between px-5 py-5 lg:px-9 lg:py-7"
                >
                    <Link
                        :href="route('admin.dashboard')"
                        class="text-2xl font-black tracking-wide lg:text-3xl"
                        @click="sidebarOpen = false"
                    >
                        CATKUJI
                    </Link>

                    <button
                        type="button"
                        class="grid h-9 w-9 place-items-center rounded-lg bg-white/5 text-white/70 hover:bg-white/10 lg:hidden"
                        @click="sidebarOpen = false"
                    >
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <nav class="flex-1 overflow-y-auto px-4 py-5 no-scrollbar">
                    <div v-for="group in menuGroups" :key="group.label">
                        <p class="px-5 text-lg font-medium text-white/45">
                            {{ group.label }}
                        </p>

                        <div class="mt-6 space-y-2">
                            <div v-for="item in group.items" :key="item.label">
                                <div v-if="isProductItem(item)">
                                    <div
                                        class="group relative flex items-center justify-between rounded-lg px-5 py-4 text-base transition"
                                        :class="
                                            isProductParentActive(item)
                                                ? 'bg-white/5 text-[#ff675c]'
                                                : 'text-white/35 hover:bg-white/5 hover:text-white/70'
                                        "
                                    >
                                        <span
                                            v-if="isProductParentActive(item)"
                                            class="absolute -left-4 h-9 w-1 rounded-r-full bg-[#ff675c]"
                                        ></span>

                                        <Link
                                            :href="hrefFor(item.routeName)"
                                            class="flex flex-1 items-center gap-4"
                                            @click="sidebarOpen = false"
                                        >
                                            <i
                                                :class="[
                                                    'bi text-xl',
                                                    item.icon,
                                                ]"
                                            ></i>
                                            <span>{{ item.label }}</span>
                                        </Link>

                                        <button
                                            type="button"
                                            class="grid h-7 w-7 place-items-center rounded text-white/45 hover:bg-white/5 hover:text-white"
                                            @click.prevent="
                                                productMenuOpen =
                                                    !productMenuOpen
                                            "
                                        >
                                            <i
                                                class="bi text-xs transition"
                                                :class="
                                                    isProductSectionOpen(item)
                                                        ? 'bi-chevron-up'
                                                        : 'bi-chevron-down'
                                                "
                                            ></i>
                                        </button>
                                    </div>

                                    <div
                                        v-show="isProductSectionOpen(item)"
                                        class="ml-10 mt-3 space-y-4 pb-2 pl-4"
                                    >
                                        <Link
                                            v-for="child in item.children"
                                            :key="child.label"
                                            :href="hrefFor(child.routeName)"
                                            class="relative block border-l-2 py-1 pl-4 text-base transition"
                                            @click="sidebarOpen = false"
                                            :class="
                                                isChildActive(child)
                                                    ? 'border-[#ff675c] text-[#ff675c]'
                                                    : child.disabled
                                                      ? 'pointer-events-none border-white/15 text-white/25'
                                                      : 'border-white/20 text-white/35 hover:border-white/40 hover:text-white/70'
                                            "
                                        >
                                            {{ child.label }}
                                        </Link>
                                    </div>
                                </div>

                                <div v-else-if="isMarketingItem(item)">
                                    <div
                                        class="group relative flex items-center justify-between rounded-lg px-5 py-4 text-base transition"
                                        :class="
                                            isMarketingParentActive(item)
                                                ? 'bg-white/5 text-[#ff675c]'
                                                : 'text-white/35 hover:bg-white/5 hover:text-white/70'
                                        "
                                    >
                                        <span
                                            v-if="isMarketingParentActive(item)"
                                            class="absolute -left-4 h-9 w-1 rounded-r-full bg-[#ff675c]"
                                        ></span>

                                        <button
                                            type="button"
                                            class="flex flex-1 items-center gap-4 text-left"
                                            @click.prevent="
                                                marketingMenuOpen =
                                                    !marketingMenuOpen
                                            "
                                        >
                                            <i
                                                :class="[
                                                    'bi text-xl',
                                                    item.icon,
                                                ]"
                                            ></i>
                                            <span>{{ item.label }}</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="grid h-7 w-7 place-items-center rounded text-white/45 hover:bg-white/5 hover:text-white"
                                            @click.prevent="
                                                marketingMenuOpen =
                                                    !marketingMenuOpen
                                            "
                                        >
                                            <i
                                                class="bi text-xs transition"
                                                :class="
                                                    isMarketingSectionOpen(item)
                                                        ? 'bi-chevron-up'
                                                        : 'bi-chevron-down'
                                                "
                                            ></i>
                                        </button>
                                    </div>

                                    <div
                                        v-show="isMarketingSectionOpen(item)"
                                        class="ml-10 mt-3 space-y-4 pb-2 pl-4"
                                    >
                                        <Link
                                            v-for="child in item.children"
                                            :key="child.label"
                                            :href="hrefFor(child.routeName)"
                                            class="relative block border-l-2 py-1 pl-4 text-base transition"
                                            :class="
                                                isChildActive(child)
                                                    ? 'border-[#ff675c] text-[#ff675c]'
                                                    : child.disabled
                                                      ? 'pointer-events-none border-white/15 text-white/25'
                                                      : 'border-white/20 text-white/35 hover:border-white/40 hover:text-white/70'
                                            "
                                        >
                                            {{ child.label }}
                                        </Link>
                                    </div>
                                </div>

                                <Link
                                    v-else-if="item.routeName"
                                    :href="hrefFor(item.routeName)"
                                    class="group relative flex items-center gap-4 rounded-lg px-5 py-4 text-base transition"
                                    :class="
                                        isActive(item)
                                            ? 'bg-white/5 text-[#ff675c]'
                                            : 'text-white/35 hover:bg-white/5 hover:text-white/70'
                                    "
                                >
                                    <span
                                        v-if="isActive(item)"
                                        class="absolute -left-4 h-9 w-1 rounded-r-full bg-[#ff675c]"
                                    ></span>

                                    <i :class="['bi text-xl', item.icon]"></i>
                                    <span>{{ item.label }}</span>
                                </Link>

                                <div v-else>
                                    <button
                                        type="button"
                                        class="group relative flex w-full items-center justify-between rounded-lg px-5 py-4 text-base transition"
                                        :class="
                                            isActive(item)
                                                ? 'bg-white/5 text-[#ff675c]'
                                                : 'text-white/35 hover:bg-white/5 hover:text-white/70'
                                        "
                                    >
                                        <span
                                            v-if="isActive(item)"
                                            class="absolute -left-4 h-9 w-1 rounded-r-full bg-[#ff675c]"
                                        ></span>

                                        <span class="flex items-center gap-4">
                                            <i
                                                :class="[
                                                    'bi text-xl',
                                                    item.icon,
                                                ]"
                                            ></i>
                                            <span>{{ item.label }}</span>
                                        </span>

                                        <i
                                            class="bi bi-chevron-down text-xs"
                                        ></i>
                                    </button>

                                    <div class="ml-14 mt-1 space-y-1 pb-2">
                                        <Link
                                            v-for="child in item.children"
                                            :key="child.label"
                                            :href="hrefFor(child.routeName)"
                                            class="block rounded-lg px-4 py-2 text-sm transition"
                                            :class="
                                                child.routeName &&
                                                route().current(child.routeName)
                                                    ? 'text-[#ff675c]'
                                                    : 'text-white/35 hover:bg-white/5 hover:text-white/70'
                                            "
                                        >
                                            {{ child.label }}
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="p-4">
                    <div
                        class="flex items-center gap-3 rounded-lg border border-white/10 bg-white/5 p-3"
                    >
                        <img
                            :src="
                                user?.avatar_url ||
                                `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.name || 'Admin')}&background=ff675c&color=fff`
                            "
                            class="h-10 w-10 rounded-lg object-cover"
                            alt="Admin avatar"
                        />

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">
                                {{ user?.name || "Admin" }}
                            </p>
                            <p class="truncate text-xs text-white/40">
                                {{ user?.email || "-" }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-lg p-2 text-white/45 hover:bg-white/10 hover:text-white"
                            @click="logout"
                        >
                            <i class="bi bi-box-arrow-right text-2xl"></i>
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <div class="min-w-0 lg:pl-[292px]">
            <header
                class="sticky top-0 z-30 border-b border-white/10 bg-[#262724]/95 backdrop-blur"
            >
                <div
                    class="flex h-[64px] items-center justify-between gap-3 px-4 lg:h-[68px] lg:px-8"
                >
                    <div class="flex items-center gap-4">
                        <button
                            type="button"
                            class="rounded-xl p-2 text-white/60 lg:hidden"
                            @click="sidebarOpen = true"
                        >
                            <i class="bi bi-list text-2xl"></i>
                        </button>

                        <div class="min-w-0 lg:hidden">
                            <p class="truncate text-sm text-white/35">Admin</p>
                            <h1 class="truncate text-sm font-normal text-white">
                                {{ title }}
                            </h1>
                        </div>

                        <div class="hidden items-center gap-3 text-sm lg:flex">
                            <Link
                                :href="route('admin.dashboard')"
                                class="text-white/35 hover:text-white"
                            >
                                Dashboard
                            </Link>

                            <template
                                v-for="(item, index) in breadcrumb"
                                :key="`${item.label}-${index}`"
                            >
                                <span class="text-white/20">›</span>

                                <Link
                                    v-if="
                                        item.href &&
                                        index !== breadcrumb.length - 1
                                    "
                                    :href="item.href"
                                    class="text-white/35 hover:text-white"
                                >
                                    {{ item.label }}
                                </Link>

                                <span
                                    v-else
                                    :class="
                                        index === breadcrumb.length - 1
                                            ? 'text-[#ff675c]'
                                            : 'text-white/35'
                                    "
                                >
                                    {{ item.label }}
                                </span>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <!-- <div
                            class="hidden h-10 w-[370px] items-center rounded-lg bg-white/5 px-4 lg:flex"
                        >
                            <i class="bi bi-search text-white/65"></i>
                            <input
                                type="text"
                                placeholder="Search..."
                                class="w-full border-0 bg-transparent px-3 text-sm text-white placeholder:text-white/30 focus:ring-0"
                            />
                        </div> -->

                        <button
                            class="grid h-10 w-10 place-items-center rounded-lg bg-white/5 text-white/70 hover:bg-white/10"
                        >
                            <i class="bi bi-bell"></i>
                        </button>

                        <Link
                            v-if="
                                $page.props.auth?.user?.role === 'super_admin'
                            "
                            :href="route('admin.settings.admins.index')"
                            class="grid h-10 w-10 place-items-center rounded-lg text-white/70 bg-white/5 hover:bg-white/10"
                            :class="
                                route().current('admin.settings.*')
                                    ? 'border-[#ff675c]/60 bg-[#ff675c]/10 text-[#ff675c]'
                                    : ''
                            "
                        >
                            <i class="bi bi-gear"></i>
                        </Link>

                        <img
                            :src="
                                user?.avatar_url ||
                                `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.name || 'Admin')}&background=ff675c&color=fff`
                            "
                            class="h-10 w-10 rounded-lg object-cover"
                            alt="Admin avatar"
                        />
                    </div>
                </div>
            </header>

            <main
                class="min-w-0 overflow-x-hidden px-3 py-4 sm:px-4 sm:py-6 lg:px-8"
            >
                <slot />
            </main>
        </div>

        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-black/60 lg:hidden"
            @click="sidebarOpen = false"
        ></div>
    </div>
</template>
