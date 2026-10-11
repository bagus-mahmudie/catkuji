<script setup>
import { router } from "@inertiajs/vue3";
import { onMounted, nextTick, ref } from "vue";
import LogoutConfirmModal from "@/Components/Auth/LogoutConfirmModal.vue";

const props = defineProps({
    active: String,
});

const whatsappUrl = "https://wa.me/6281234567890";

const itemRefs = ref({});

const setItemRef = (el, key) => {
    if (el) {
        itemRefs.value[key] = el;
    }
};

const showLogoutModal = ref(false);
const logoutProcessing = ref(false);

const scrollToActive = async (behavior = "auto") => {
    await nextTick();

    const activeEl = itemRefs.value[props.active];

    if (activeEl) {
        activeEl.scrollIntoView({
            behavior,
            inline: "center",
            block: "nearest",
        });
    }
};

onMounted(() => {
    scrollToActive("auto");
});

const goToMenu = async (menu) => {
    if (menu.external) {
        window.open(menu.href, "_blank", "noopener,noreferrer");
        return;
    }

    const targetEl = itemRefs.value[menu.key];

    if (targetEl) {
        targetEl.scrollIntoView({
            behavior: "smooth",
            inline: "center",
            block: "nearest",
        });
    }

    setTimeout(() => {
        router.visit(menu.href, {
            preserveScroll: true,
        });
    }, 120);
};

const menuItems = [
    {
        key: "token",
        label: "Token",
        icon: "bi bi-coin",
        href: route("profile.token"),
        external: false,
    },
    {
        key: "orders",
        label: "Order History",
        icon: "bi bi-bag",
        href: route("profile.orders"),
        external: false,
    },
    {
        key: "wishlist",
        label: "Saved Wishlist",
        icon: "bi bi-heart",
        href: route("profile.wishlist"),
        external: false,
    },
    {
        key: "address",
        label: "Alamat Pengiriman",
        icon: "bi bi-geo-alt",
        href: route("profile.address.edit"),
        external: false,
    },
    {
        key: "customer-service",
        label: "Customer Service",
        icon: "bi bi-headset",
        href: whatsappUrl,
        external: true,
    },
    {
        key: "language",
        label: "Language",
        icon: "bi bi-globe2",
        href: route("profile.language"),
        external: false,
    },
    {
        key: "voucher",
        label: "Cat Voucher",
        icon: "bi bi-patch-check",
        href: route("profile.voucher"),
        external: false,
    },
    {
        key: "terms",
        label: "Syarat dan Ketentuan",
        icon: "bi bi-file-earmark-text",
        href: route("profile.terms"),
        external: false,
    },
    {
        key: "privacy",
        label: "Kebijakan Privasi",
        icon: "bi bi-shield-check",
        href: route("profile.privacy"),
        external: false,
    },
];

const logout = () => {
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
</script>

<template>
    <div>
        <!-- MOBILE SLIDER -->
        <div class="lg:hidden">
            <div
                class="no-scrollbar flex snap-x snap-mandatory gap-3 overflow-x-auto p-2 sm:p-2"
            >
                <button
                    v-for="menu in menuItems"
                    :key="menu.key"
                    :ref="(el) => setItemRef(el, menu.key)"
                    type="button"
                    class="w-[190px] shrink-0 snap-center rounded-xl border border-white/5 bg-[#101521] px-4 py-3 text-left shadow-[0_10px_30px_rgba(0,0,0,0.25)] transition"
                    :class="
                        active === menu.key
                            ? 'text-[#F66455] ring-1 ring-inset ring-[#F66455]/30'
                            : 'text-white/70 hover:bg-white/[0.03] hover:text-white'
                    "
                    @click="goToMenu(menu)"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex min-w-0 items-center gap-3">
                            <i :class="[menu.icon, 'text-base']" />
                            <span class="truncate text-sm font-medium">
                                {{ menu.label }}
                            </span>
                        </span>

                        <i class="bi bi-chevron-right shrink-0 text-xs" />
                    </div>
                </button>

                <button
                    type="button"
                    class="w-[190px] shrink-0 snap-start rounded-xl border border-white/5 bg-[#101521] px-4 py-3 text-left text-[#F66455] shadow-[0_10px_30px_rgba(0,0,0,0.25)] transition hover:bg-white/[0.03]"
                    @click="logout"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex min-w-0 items-center gap-3">
                            <i class="bi bi-box-arrow-right text-base" />
                            <span class="truncate text-sm font-medium">
                                Keluar
                            </span>
                        </span>

                        <i class="bi bi-chevron-right shrink-0 text-xs" />
                    </div>
                </button>
            </div>
        </div>

        <!-- DESKTOP SIDEBAR -->
        <aside class="hidden h-fit rounded-xl bg-[#101521] p-4 lg:block">
            <div v-for="menu in menuItems" :key="menu.key">
                <a
                    :href="menu.href"
                    :target="menu.external ? '_blank' : null"
                    :rel="menu.external ? 'noopener noreferrer' : null"
                    class="flex w-full items-center justify-between rounded-xl px-4 py-5 text-left transition"
                    :class="
                        active === menu.key
                            ? 'font-semibold text-[#F66455]'
                            : 'text-white/65 hover:bg-white/[0.03] hover:text-white'
                    "
                >
                    <div class="flex items-center gap-4">
                        <i :class="[menu.icon, 'text-xl']" />
                        <span class="text-base font-medium">
                            {{ menu.label }}
                        </span>
                    </div>

                    <i class="bi bi-chevron-right text-base" />
                </a>

                <div class="mx-4 h-px bg-white/10" />
            </div>

            <button
                type="button"
                class="flex w-full items-center justify-between rounded-xl px-4 py-5 text-left text-[#F66455] transition hover:bg-white/[0.03]"
                @click="logout"
            >
                <div class="flex items-center gap-4">
                    <i class="bi bi-box-arrow-right text-xl" />
                    <span class="text-base font-medium">Keluar</span>
                </div>

                <i class="bi bi-chevron-right text-base" />
            </button>
        </aside>

        <LogoutConfirmModal
            :show="showLogoutModal"
            :processing="logoutProcessing"
            @close="closeLogoutModal"
            @confirm="confirmLogout"
        />
    </div>
</template>
