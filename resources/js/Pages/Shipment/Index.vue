<script setup>
import { Head, router } from "@inertiajs/vue3";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import { resolvePrizeImagePath } from "@/Utils/resolvePrizeImage";

const props = defineProps({
    shipments: {
        type: Object,
        required: true,
    },
});

const openTopupPage = () => {
    window.location.href = route("wallet.index");
};

const goToDetail = (orderNo) => {
    router.visit(route("shipment.show", orderNo));
};

const formatCurrency = (value) =>
    `Rp ${Number(value ?? 0).toLocaleString("id-ID")}`;

const statusLabel = (status) => {
    switch (status) {
        case "pending_payment":
            return "Menunggu Pembayaran";
        case "paid":
            return "Sudah Dibayar";
        case "processing":
            return "Diproses";
        case "packed":
            return "Dikemas";
        case "shipped":
            return "Dikirim";
        case "delivered":
            return "Selesai";
        case "booking_failed":
            return "Gagal Booking";
        case "cancelled":
            return "Dibatalkan";
        default:
            return status ?? "-";
    }
};

const statusClass = (status) => {
    switch (status) {
        case "pending_payment":
            return "bg-yellow-500/15 text-yellow-300";
        case "paid":
        case "processing":
        case "packed":
            return "bg-blue-500/15 text-blue-300";
        case "shipped":
        case "delivered":
            return "bg-green-500/15 text-green-300";
        case "booking_failed":
        case "cancelled":
            return "bg-red-500/15 text-red-300";
        default:
            return "bg-white/10 text-white/70";
    }
};
</script>

<template>
    <Head title="Riwayat Pengiriman" />

    <div class="min-h-screen bg-[#060C18] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar @topup-click="openTopupPage" />

            <section class="mt-8">
                <div class="mb-6">
                    <h1 class="text-[34px] font-semibold tracking-tight">
                        Riwayat Pengiriman
                    </h1>
                    <p class="mt-2 text-sm text-white/55">
                        Lihat status hadiah yang sedang atau sudah dikirim.
                    </p>
                </div>

                <div
                    v-if="!shipments.data.length"
                    class="rounded-[22px] border border-white/5 bg-[#091224] p-8 text-center text-white/60"
                >
                    Belum ada order pengiriman.
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="shipment in shipments.data"
                        :key="shipment.id"
                        class="rounded-[22px] border border-white/5 bg-[#091224] p-5"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-xl font-semibold">
                                        {{ shipment.order_no }}
                                    </h2>

                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                                        :class="statusClass(shipment.status)"
                                    >
                                        {{ statusLabel(shipment.status) }}
                                    </span>
                                </div>

                                <div class="mt-2 text-sm text-white/50">
                                    Dibuat: {{ shipment.created_at || "-" }}
                                </div>

                                <div class="mt-3 text-sm text-white/60">
                                    Kurir:
                                    <span class="font-medium text-white/85">
                                        {{ shipment.courier_name || "-" }}
                                        {{
                                            shipment.service_name
                                                ? `- ${shipment.service_name}`
                                                : ""
                                        }}
                                    </span>
                                </div>

                                <div class="mt-1 text-sm text-white/60">
                                    Ongkir:
                                    <span class="font-medium text-white">
                                        {{
                                            formatCurrency(
                                                shipment.shipping_cost,
                                            )
                                        }}
                                    </span>
                                </div>

                                <div
                                    v-if="shipment.tracking_number"
                                    class="mt-1 text-sm text-white/60"
                                >
                                    Resi:
                                    <span class="font-medium text-white">
                                        {{ shipment.tracking_number }}
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="rounded-xl bg-[#F56D5D] px-5 py-3 text-sm font-medium text-white transition hover:bg-[#ff7d6d]"
                                @click="goToDetail(shipment.order_no)"
                            >
                                Lihat Detail
                            </button>
                        </div>

                        <div
                            class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                        >
                            <div
                                v-for="item in shipment.items"
                                :key="item.id"
                                class="flex gap-3 rounded-xl bg-[#0D1729] p-3"
                            >
                                <img
                                    :src="resolvePrizeImagePath(item.image)"
                                    :alt="item.prize_name_snapshot"
                                    class="h-16 w-16 rounded-xl object-cover"
                                />

                                <div class="min-w-0 flex-1">
                                    <div
                                        class="line-clamp-2 text-sm font-medium text-white"
                                    >
                                        {{ item.prize_name_snapshot }}
                                    </div>
                                    <div class="mt-1 text-xs text-white/50">
                                        Qty {{ item.qty }}
                                    </div>
                                    <div class="mt-1 text-xs text-white/45">
                                        Status item:
                                        {{ item.inventory_status || "-" }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <AppFooter />
    </div>
</template>
