<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import BaseTransactionCard from "./BaseTransactionCard.vue";
import TransactionPrizeItemCard from "./TransactionPrizeItemCard.vue";

defineProps({
    item: {
        type: Object,
        required: true,
    },
    expanded: {
        type: Boolean,
        default: false,
    },
});

defineEmits(["toggle"]);

const shippingRows = computed(() => {
    const rows = props.item?.sections?.shipping?.rows;

    if (Array.isArray(rows) && rows.length > 0) {
        return rows.filter((row) => row && row.label && row.value);
    }

    const shipping = props.item?.sections?.shipping ?? {};

    const fallbackRows = [
        {
            label: "Status order",
            value: shipping.status || props.item?.status_label || "-",
        },
        {
            label: "Kurir",
            value:
                shipping.courier_service ||
                [shipping.courier_name, shipping.service_name]
                    .filter(Boolean)
                    .join(" - ") ||
                "-",
        },
        {
            label: "No. resi",
            value: shipping.tracking_number || "-",
        },
        {
            label: "Tracking status",
            value: shipping.tracking_status || "-",
        },
        {
            label: "No. order",
            value: shipping.order_no || props.item?.reference_no || "-",
        },
    ];

    return fallbackRows;
});
</script>

<template>
    <BaseTransactionCard
        :item="item"
        :expanded="expanded"
        @toggle="$emit('toggle')"
    >
        <div class="mt-8 grid grid-cols-5 gap-y-6 sm:grid-cols-5">
            <div
                v-for="(step, index) in item.sections.shipping.timeline"
                :key="step.label"
                class="relative text-center"
            >
                <div
                    v-if="index !== item.sections.shipping.timeline.length - 1"
                    class="absolute top-6 hidden sm:block"
                    style="left: calc(50% + 32px); width: calc(100% - 64px)"
                >
                    <div
                        class="h-0 border-t border-dashed border-white/20"
                    ></div>
                </div>

                <div class="flex items-center justify-center">
                    <div
                        class="relative z-10 flex h-12 w-12 items-center justify-center rounded-xl"
                        :class="
                            step.active
                                ? 'bg-[#172033] text-white'
                                : 'bg-white/[0.04] text-white/30'
                        "
                    >
                        <i :class="[step.icon, 'text-lg']" />
                    </div>
                </div>

                <div
                    class="mt-3 text-[10px] sm:text-sm"
                    :class="step.active ? 'text-white' : 'text-white/40'"
                >
                    {{ step.label }}
                </div>
            </div>
        </div>

        <div class="mt-8">
            <div class="text-xl font-medium text-white">Alamat pengiriman</div>

            <div class="mt-4 border-t border-white/10 pt-4 text-white/80">
                {{ item.sections.shipping.address.name }},
                {{ item.sections.shipping.address.phone }}
            </div>

            <div
                class="mt-4 rounded-xl bg-white/[0.04] px-4 py-4 text-white/75"
            >
                {{ item.sections.shipping.address.full_address }}
            </div>
        </div>

        <div class="mt-8">
            <div class="text-xl font-medium text-white">Status Pengiriman</div>

            <div class="mt-4 border-t border-white/10 pt-5">
                <div
                    class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:text-base"
                >
                    <template
                        v-for="row in item.sections.shipping.rows"
                        :key="row.label"
                    >
                        <div class="text-white/55">{{ row.label }}</div>

                        <div
                            class="break-all text-right font-medium text-white"
                        >
                            <Link
                                v-if="row.type === 'link' && row.href"
                                :href="row.href"
                                class="text-[#F66455] underline underline-offset-4 transition hover:text-[#ff7d70]"
                            >
                                {{ row.value }}
                            </Link>

                            <span v-else>
                                {{ row.value }}
                            </span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="mt-8">
            <div class="text-xl font-medium text-white">Produk Dipesan</div>

            <div class="mt-4 space-y-4 border-t border-white/10 pt-5">
                <TransactionPrizeItemCard
                    v-for="(product, index) in item.sections.shipping.items"
                    :key="index"
                    :product="product"
                />
            </div>
        </div>

        <div
            v-if="
                item.sections.shipping.payment_url &&
                item.status_key === 'pending'
            "
            class="mt-6"
        >
            <Link
                :href="item.sections.shipping.payment_url"
                class="inline-flex min-w-full items-center justify-center rounded-xl bg-[#F66455] px-6 py-3 text-base font-medium text-white transition hover:bg-[#ff7d70]"
            >
                Bayar Pengiriman
            </Link>
        </div>
    </BaseTransactionCard>
</template>
