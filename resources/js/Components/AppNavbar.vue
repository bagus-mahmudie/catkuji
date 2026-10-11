<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import LogoutConfirmModal from "@/Components/Auth/LogoutConfirmModal.vue";
import catKoinImage from "@/assets/cat-koin.png";
import tokenImage from "@/assets/token-silver.png";
import { openAuthModal } from "@/Utils/authModal";
import { resolveAvatarUrl } from "@/Utils/avatar";

const mobileMenuOpen = ref(false);

const menus = [
    { label: "Homepage", href: route("home") },
    { label: "Inventory", href: route("inventory.index") },
    { label: "Transaksi", href: route("transactions.index") },
    { label: "Akun", href: route("profile.token") },
];

const showLogoutModal = ref(false);
const logoutProcessing = ref(false);

const props = defineProps({
    topupHref: {
        type: String,
        default: "#",
    },
    showWalletBalance: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["topup-click"]);

const page = usePage();
const dropdownRef = ref(null);
const isDropdownOpen = ref(false);

const isAuthenticated = computed(() => !!page.props.auth?.user);

const userAvatarUrl = computed(() => {
    return resolveAvatarUrl(page.props.auth?.user);
});

const isActive = (href) => {
    return page.url === href;
};

const formattedTokenBalance = computed(() =>
    Number(page.props.tokenBalance ?? 0).toLocaleString("id-ID"),
);

const formattedCatKoinBalance = computed(() =>
    Number(page.props.catKoinBalance ?? 0).toLocaleString("id-ID"),
);

const formattedWalletBalance = computed(() =>
    Number(page.props.walletBalance ?? 0).toLocaleString("id-ID"),
);

const openLoginModal = () => {
    mobileMenuOpen.value = false;
    openAuthModal("login");
};

const openRegisterModal = () => {
    mobileMenuOpen.value = false;
    openAuthModal("register");
};

const handleTopupClick = () => {
    if (!isAuthenticated.value) {
        openLoginModal();
        return;
    }

    emit("topup-click");
};

const handleMenuClick = (menu) => {
    mobileMenuOpen.value = false;

    const publicMenus = [route("home")];

    if (isAuthenticated.value || publicMenus.includes(menu.href)) {
        router.visit(menu.href);
        return;
    }

    openLoginModal();
};

const toggleDropdown = () => {
    isDropdownOpen.value = !isDropdownOpen.value;
};

const closeDropdown = () => {
    isDropdownOpen.value = false;
};

const logout = () => {
    closeDropdown();
    mobileMenuOpen.value = false;
    showLogoutModal.value = true;
};

const closeLogoutModal = () => {
    if (logoutProcessing.value) return;

    showLogoutModal.value = false;
};

const confirmLogout = () => {
    logoutProcessing.value = true;

    setTimeout(() => {
        showLogoutModal.value = false;

        router.post(
            route("logout"),
            {},
            {
                onFinish: () => {
                    logoutProcessing.value = false;
                },
            },
        );
    }, 150);
};

const handleClickOutside = (event) => {
    if (!dropdownRef.value) return;

    if (!dropdownRef.value.contains(event.target)) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleClickOutside);
});
</script>

<template>
    <header class="relative rounded-xl bg-[#101521] px-3 py-3 sm:px-5 sm:py-4">
        <div class="flex items-center justify-between gap-2 min-w-0">
            <!-- kiri -->
            <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-4">
                <button
                    type="button"
                    @click="mobileMenuOpen = true"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/[0.04] text-white sm:h-10 sm:w-10"
                >
                    <i class="bi bi-list text-lg sm:text-xl" />
                </button>

                <Link
                    :href="route('home')"
                    class="min-w-0 truncate text-lg font-bold tracking-wide text-white sm:text-2xl"
                >
                    CATKUJI
                </Link>
            </div>

            <!-- kanan -->
            <div class="flex shrink-0 items-center gap-1 sm:gap-3">
                <div
                    class="flex items-center gap-1 rounded-lg px-1.5 py-1 sm:gap-2 sm:px-2 sm:py-1.5"
                >
                    <img
                        :src="tokenImage"
                        alt="Token"
                        class="h-4 w-4 shrink-0 object-contain sm:h-6 sm:w-6"
                    />
                    <div
                        class="text-sm font-medium whitespace-nowrap text-white sm:text-base"
                    >
                        {{ formattedTokenBalance }}
                    </div>
                </div>

                <div
                    class="flex items-center gap-1 rounded-lg px-1.5 py-1 sm:gap-2 sm:px-2 sm:py-1.5"
                >
                    <img
                        :src="catKoinImage"
                        alt="Cat Koin"
                        class="h-4 w-4 shrink-0 object-contain sm:h-6 sm:w-6"
                    />
                    <div
                        class="text-sm font-medium whitespace-nowrap text-white sm:text-base"
                    >
                        {{ formattedCatKoinBalance }}
                    </div>
                </div>

                <template v-if="showWalletBalance">
                    <div class="hidden h-8 w-px bg-white/10 sm:block"></div>

                    <div class="hidden items-center gap-3 sm:flex">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-[radial-gradient(circle_at_30%_30%,#ffd6a8,#ff8a3d)] text-sm font-bold text-slate-900"
                        >
                            Rp
                        </div>
                        <div class="text-xl font-semibold text-white">
                            {{ formattedWalletBalance }}
                        </div>
                    </div>
                </template>

                <button
                    type="button"
                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white transition hover:bg-white/10"
                    aria-label="Top Up"
                    @click="handleTopupClick"
                >
                    <i class="bi bi-plus-lg text-sm sm:text-lg" />
                </button>

                <div class="hidden h-7 w-px bg-white/10 sm:block"></div>

                <div
                    v-if="isAuthenticated"
                    ref="dropdownRef"
                    class="flex items-center"
                >
                    <button
                        type="button"
                        class="inline-flex h-6 w-6 sm:h-8 sm:w-8 shrink-0 items-center justify-center overflow-hidden rounded-full ring-1 ring-white/10 transition hover:ring-white/20"
                        aria-label="User menu"
                        @click.stop="toggleDropdown"
                    >
                        <img
                            :src="userAvatarUrl"
                            alt="Profile"
                            class="h-full w-full object-cover"
                        />
                    </button>

                    <div
                        v-if="isDropdownOpen"
                        class="absolute right-0 top-[calc(100%+12px)] z-50 min-w-[190px] overflow-hidden rounded-xl border border-white/10 bg-[#111827]"
                    >
                        <div class="border-b border-white/5 px-4 py-3">
                            <p class="text-sm font-semibold text-white">
                                {{ page.props.auth?.user?.name }}
                            </p>
                            <p class="mt-1 text-xs text-white/55">
                                {{ page.props.auth?.user?.email }}
                            </p>
                        </div>

                        <div class="p-2">
                            <Link
                                :href="route('profile.edit')"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/85 transition hover:bg-white/5"
                                @click="closeDropdown"
                            >
                                <i class="bi bi-person-circle text-base" />
                                <span>Profile</span>
                            </Link>

                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm text-[#FF9A92] transition hover:bg-white/5"
                                @click="logout"
                            >
                                <i class="bi bi-box-arrow-right text-base" />
                                <span>Logout</span>
                            </button>
                        </div>
                    </div>
                </div>

                <button
                    v-else
                    type="button"
                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white transition hover:bg-white/10"
                    aria-label="Login"
                    @click.prevent.stop="openLoginModal"
                >
                    <i class="bi bi-person text-base sm:text-lg" />
                </button>
            </div>
        </div>

        <transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileMenuOpen"
                class="fixed inset-0 z-[90] bg-black/40"
                @click="mobileMenuOpen = false"
            />
        </transition>

        <transition
            enter-active-class="transform transition duration-300 ease-out"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transform transition duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
        >
            <aside
                :class="[
                    'fixed left-0 top-0 z-[100] h-dvh w-[300px] overflow-y-auto bg-[#101521] px-6 pb-8 pt-6 text-white shadow-2xl transition-all duration-300 ease-in-out',
                    mobileMenuOpen
                        ? 'translate-x-0 opacity-100 pointer-events-auto'
                        : '-translate-x-full opacity-0 pointer-events-none',
                ]"
            >
                <div class="mb-10 flex items-center gap-3">
                    <button
                        type="button"
                        @click="mobileMenuOpen = false"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/[0.04] text-white"
                    >
                        <i class="bi bi-x-lg"></i>
                    </button>

                    <span
                        class="text-3xl font-semibold tracking-wide text-white"
                    >
                        CATKUJI
                    </span>
                </div>

                <nav class="flex flex-col">
                    <button
                        v-for="menu in menus"
                        :key="menu.label"
                        type="button"
                        @click="handleMenuClick(menu)"
                        class="border-b border-white/10 py-4 text-left text-base font-medium text-white/90 transition hover:text-white"
                        :class="[
                            isActive(menu.href)
                                ? 'text-white'
                                : 'text-white/85',
                        ]"
                    >
                        {{ menu.label }}
                    </button>
                </nav>
            </aside>
        </transition>

        <LogoutConfirmModal
            :show="showLogoutModal"
            :processing="logoutProcessing"
            @close="closeLogoutModal"
            @confirm="confirmLogout"
        />
    </header>
</template>
