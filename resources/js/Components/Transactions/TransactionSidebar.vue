<script setup>
import { nextTick, onMounted, ref, watch } from "vue";

const props = defineProps({
    activeService: {
        type: String,
        default: "all",
    },
});

const emit = defineEmits(["change"]);

const services = [
    { key: "all", label: "Semua layanan", icon: "bi bi-grid" },
    { key: "purchase", label: "Pembelian", icon: "bi bi-cart3" },
    {
        key: "sellback",
        label: "Jual kembali",
        icon: "bi bi-credit-card-2-front",
    },
    {
        key: "digital_claim",
        label: "Klaim hadiah digital",
        icon: "bi bi-box-seam",
    },
    { key: "shipping", label: "Pengiriman", icon: "bi bi-truck" },
    { key: "topup", label: "Top up", icon: "bi bi-wallet2" },
];

const itemRefs = ref({});

const setItemRef = (el, key) => {
    if (el) {
        itemRefs.value[key] = el;
    }
};

const scrollToActive = async () => {
    await nextTick();

    const activeEl = itemRefs.value[props.activeService];

    if (activeEl) {
        activeEl.scrollIntoView({
            behavior: "smooth",
            inline: "center",
            block: "nearest",
        });
    }
};

onMounted(scrollToActive);

watch(
    () => props.activeService,
    () => {
        scrollToActive();
    },
);
</script>

<template>
    <div class="min-w-0 max-w-full">
        <!-- MOBILE SLIDER -->
        <div class="overflow-hidden lg:hidden">
            <div
                class="no-scrollbar flex snap-x snap-mandatory gap-3 overflow-x-auto pl-0 p-2 sm:pb-2"
            >
                <button
                    v-for="menu in services"
                    :key="menu.key"
                    :ref="(el) => setItemRef(el, menu.key)"
                    type="button"
                    class="w-[190px] shrink-0 snap-center rounded-xl border border-white/5 bg-[#101521] px-4 py-3 shadow-[0_10px_30px_rgba(0,0,0,0.25)] transition"
                    :class="
                        activeService === menu.key
                            ? 'text-[#F66455] ring-1 ring-inset ring-[#F66455]/40'
                            : 'text-white/70 hover:bg-white/[0.03] hover:text-white'
                    "
                    @click="emit('change', menu.key)"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex min-w-0 items-center gap-3">
                            <i :class="[menu.icon, 'shrink-0 text-base']" />
                            <span class="truncate text-sm font-medium">
                                {{ menu.label }}
                            </span>
                        </span>

                        <i class="bi bi-chevron-right shrink-0 text-xs" />
                    </div>
                </button>
            </div>
        </div>

        <!-- DESKTOP SIDEBAR -->
        <aside class="hidden h-fit rounded-xl bg-[#101521] p-4 lg:block">
            <div v-for="(menu, index) in services" :key="menu.key">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-xl px-4 py-5 text-left transition"
                    :class="
                        activeService === menu.key
                            ? 'font-semibold text-[#F66455]'
                            : 'text-white/65 hover:bg-white/[0.03]'
                    "
                    @click="emit('change', menu.key)"
                >
                    <div class="flex items-center gap-4">
                        <i :class="[menu.icon, 'text-xl']" />
                        <span class="text-base font-medium">
                            {{ menu.label }}
                        </span>
                    </div>

                    <i class="bi bi-chevron-right text-base" />
                </button>

                <div
                    v-if="index !== services.length - 1"
                    class="mx-4 h-px bg-white/10"
                />
            </div>
        </aside>
    </div>
</template>
