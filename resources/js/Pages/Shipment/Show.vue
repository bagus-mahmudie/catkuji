<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import TransactionPrizeItemCard from "@/Components/Transactions/cards/TransactionPrizeItemCard.vue";

const props = defineProps({
    shipment: {
        type: Object,
        required: true,
    },
});

const page = usePage();

const showSuccessModal = ref(false);
const isPaying = ref(false);
const paymentNotice = ref("");

const trackingEvents = computed(() => props.shipment.tracking_events ?? []);

const bookingFailed = computed(
    () => props.shipment.status === "booking_failed",
);

const openTopupPage = () => {
    window.location.href = route("wallet.index");
};

const hasPendingPayment = computed(
    () => props.shipment?.payment?.status === "pending",
);

const hasSnapToken = computed(() => !!props.shipment?.payment?.snap_token);

const paymentPending = computed(
    () => hasPendingPayment.value && hasSnapToken.value,
);

const shippingReady = computed(
    () =>
        ["processing", "packed", "shipped", "delivered"].includes(
            props.shipment.status,
        ) && !!props.shipment.external_order_id,
);

const shouldShowSuccessModal = computed(() => {
    const url = new URL(page.url, window.location.origin);

    return (
        url.searchParams.get("show_success") === "1" &&
        url.searchParams.get("payment_status") === "paid" &&
        (props.shipment?.payment?.status === "paid" || shippingReady.value)
    );
});

const syncSuccessModal = () => {
    showSuccessModal.value = shouldShowSuccessModal.value;
};

const reloadShipmentPage = () => {
    router.visit(
        route("shipment.show", {
            orderNo: props.shipment.order_no,
        }),
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
        },
    );
};

const payNow = async () => {
    if (!paymentPending.value || isPaying.value) return;

    paymentNotice.value = "";
    isPaying.value = true;

    try {
        if (!window.snap) {
            throw new Error(
                "Midtrans Snap tidak tersedia. Pastikan script Snap dimuat di app.blade.php.",
            );
        }

        window.snap.pay(props.shipment.payment.snap_token, {
            onSuccess: () => {
                isPaying.value = false;

                setTimeout(() => {
                    router.visit(
                        route("shipment.show", {
                            orderNo: props.shipment.order_no,
                            show_success: 1,
                            payment_status: "paid",
                        }),
                        {
                            preserveScroll: true,
                            preserveState: false,
                            replace: true,
                        },
                    );
                }, 500);
            },

            onPending: () => {
                isPaying.value = false;
                paymentNotice.value =
                    "Pembayaran masih pending. Silakan cek status transaksi beberapa saat lagi.";

                router.visit(
                    route("shipment.show", {
                        orderNo: props.shipment.order_no,
                    }),
                    {
                        preserveScroll: true,
                        preserveState: false,
                        replace: true,
                    },
                );
            },

            onError: (result) => {
                isPaying.value = false;
                console.error("Midtrans error:", result);
                paymentNotice.value =
                    "Pembayaran gagal atau ditolak. Silakan coba metode pembayaran lain.";
            },

            onClose: () => {
                isPaying.value = false;

                router.visit(
                    route("shipment.show", {
                        orderNo: props.shipment.order_no,
                    }),
                    {
                        preserveScroll: true,
                        preserveState: true,
                        replace: true,
                    },
                );
            },
        });
    } catch (error) {
        isPaying.value = false;
        console.error("Gagal memuat Midtrans Snap:", error);
        paymentNotice.value =
            "Terjadi kendala saat memuat pembayaran. Silakan coba lagi.";
    }
};

onMounted(async () => {
    const snapOpenKey = `shipment-snap-opened-${props.shipment.order_no}`;
    const url = new URL(page.url, window.location.origin);
    const forceOpenPayment = url.searchParams.get("open_payment") === "1";

    syncSuccessModal();

    if (!paymentPending.value) return;

    if (forceOpenPayment) {
        sessionStorage.removeItem(snapOpenKey);
        sessionStorage.setItem(snapOpenKey, "1");
        await payNow();
        return;
    }

    if (!sessionStorage.getItem(snapOpenKey)) {
        sessionStorage.setItem(snapOpenKey, "1");
        await payNow();
    }
});

const replaceShipmentPageUrl = () => {
    router.visit(
        route("shipment.show", {
            orderNo: props.shipment.order_no,
        }),
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

watch(
    () => page.url,
    () => {
        syncSuccessModal();
    },
);

const closeSuccessModalToInventory = () => {
    showSuccessModal.value = false;

    sessionStorage.setItem("show_csat_after_shipment", "1");
    sessionStorage.setItem("csat_shipment_order_id", props.shipment.id);

    router.visit(route("inventory.index"));
};

const goToShippingTransaction = () => {
    showSuccessModal.value = false;

    router.visit(
        route("transactions.index", {
            service: "shipping",
            open: `shipment-${props.shipment.id}`,
            order_no: props.shipment.order_no,
        }),
    );
};
</script>

<template>
    <Head :title="`Shipment ${shipment.order_no}`" />

    <div class="min-h-screen bg-[#060C18] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar @topup-click="openTopupPage" />

            <section
                class="mt-8 grid items-start grid-cols-1 gap-6 lg:grid-cols-[1.03fr_1fr]"
            >
                <div class="space-y-6">
                    <div
                        class="rounded-xl border border-white/5 bg-[#101521] p-5"
                    >
                        <h2
                            class="border-b border-white/10 pb-4 text-lg font-medium sm:text-2xl"
                        >
                            Alamat pengiriman
                        </h2>

                        <div class="mt-3 text-base font-medium">
                            {{ shipment.recipient_name_snapshot }},
                            {{ shipment.recipient_phone_snapshot }}
                        </div>

                        <div
                            class="mt-4 rounded-xl bg-white/5 px-4 py-4 text-sm leading-7 text-white/55"
                        >
                            {{ shipment.address_snapshot_json?.full_address }}
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-white/5 bg-[#101521] p-5"
                    >
                        <h2
                            class="border-b border-white/10 pb-4 text-lg font-medium sm:text-2xl"
                        >
                            Status Pengiriman
                        </h2>

                        <div class="mt-5 space-y-2 text-sm text-white/70">
                            <div>
                                Status order:
                                <span class="font-medium text-white">
                                    {{ shipment.status }}
                                </span>
                            </div>

                            <div>
                                Kurir:
                                <span class="font-medium text-white">
                                    {{ shipment.courier_name }} -
                                    {{ shipment.service_name }}
                                </span>
                            </div>

                            <div v-if="shipment.tracking_number">
                                Resi:
                                <span class="font-medium text-white">
                                    {{ shipment.tracking_number }}
                                </span>
                            </div>

                            <div v-if="shipment.tracking_status">
                                Tracking status:
                                <span class="font-medium text-white">
                                    {{ shipment.tracking_status }}
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="paymentNotice"
                            class="mt-4 rounded-xl border border-[#F56D5D]/20 bg-[#F56D5D]/10 px-4 py-3 text-sm text-[#FFD4CE]"
                        >
                            {{ paymentNotice }}
                        </div>

                        <button
                            v-if="hasPendingPayment"
                            type="button"
                            class="mt-6 rounded-xl bg-[#F56D5D] px-6 py-4 text-base font-medium text-white transition hover:bg-[#ff7d6d] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isPaying"
                            @click="payNow"
                        >
                            {{
                                isPaying
                                    ? "Memproses Pembayaran..."
                                    : "Bayar Ongkir Sekarang"
                            }}
                        </button>

                        <div
                            v-if="hasPendingPayment && !hasSnapToken"
                            class="mt-4 text-sm text-red-300"
                        >
                            Pembayaran pending, tetapi Snap token belum
                            tersedia.
                        </div>
                    </div>

                    <div
                        class="mt-6 rounded-xl border border-white/5 bg-[#101521] p-5"
                    >
                        <h2
                            class="border-b border-white/10 pb-4 text-lg font-medium sm:text-2xl"
                        >
                            Timeline Pengiriman
                        </h2>

                        <div
                            v-if="trackingEvents.length"
                            class="mt-5 space-y-4"
                        >
                            <div
                                v-for="event in trackingEvents"
                                :key="event.id"
                                class="rounded-xl bg-white/5 px-4 py-4"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div
                                        class="min-w-0 flex-1 pr-2 text-base font-medium leading-6 text-white"
                                    >
                                        {{
                                            event.status_label ||
                                            event.status_code ||
                                            "Update status"
                                        }}
                                    </div>

                                    <div
                                        class="w-[88px] shrink-0 text-right text-[11px] leading-4 text-white/45 sm:w-auto sm:text-xs"
                                    >
                                        {{ event.event_time || "-" }}
                                    </div>
                                </div>

                                <div
                                    v-if="event.description"
                                    class="mt-2 text-sm leading-5 text-white/60"
                                >
                                    {{ event.description }}
                                </div>

                                <div
                                    class="mt-2 text-xs uppercase text-white/35"
                                >
                                    {{ event.source }}
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="mt-5 rounded-xl bg-white/5 px-4 py-4 text-sm text-white/60"
                        >
                            Belum ada riwayat tracking untuk shipment ini.
                        </div>
                    </div>

                    <div
                        v-if="bookingFailed"
                        class="mt-4 rounded-xl border border-red-400/20 bg-red-500/10 px-4 py-4 text-sm text-red-200"
                    >
                        Booking pengiriman ke ekspedisi gagal. Anda perlu
                        mencoba booking ulang dari sistem admin atau endpoint
                        retry.
                    </div>
                </div>

                <div
                    class="self-start rounded-xl border border-white/5 bg-[#101521] p-5"
                >
                    <h2
                        class="border-b border-white/10 pb-4 text-lg font-medium sm:text-2xl"
                    >
                        Produk Dikirim
                    </h2>

                    <div class="space-y-4 pt-5">
                        <TransactionPrizeItemCard
                            v-for="item in shipment.items"
                            :key="item.id"
                            :product="item"
                        />
                    </div>

                    <div class="mt-5 rounded-xl bg-white/5 px-4 py-4">
                        <div
                            class="flex items-center justify-between text-sm text-white/55"
                        >
                            <span>Biaya Kirim</span>
                            <span class="font-medium text-white">
                                Rp
                                {{
                                    Number(
                                        shipment.shipping_cost ?? 0,
                                    ).toLocaleString("id-ID")
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <AppFooter />

        <div
            v-if="showSuccessModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 px-4"
            @click.self="closeSuccessModalToInventory"
        >
            <div
                class="relative w-full max-w-[420px] rounded-xl bg-[#101521] p-8 text-center shadow-2xl"
            >
                <button
                    type="button"
                    class="absolute -bottom-16 left-1/2 flex h-14 w-14 -translate-x-1/2 items-center justify-center rounded-full bg-[#F56D5D] text-2xl text-white"
                    @click="closeSuccessModalToInventory"
                >
                    ×
                </button>

                <div
                    class="mx-auto flex h-20 w-20 items-center justify-center rounded-xl bg-[#1C2236]"
                >
                    <div
                        class="flex h-16 w-16 items-center justify-center rounded-[18px] bg-[#F56D5D] text-3xl text-white"
                    >
                        <i class="bi bi-truck"></i>
                    </div>
                </div>

                <h3 class="mt-8 text-2xl font-medium text-white">
                    Hadiah Dikirim
                </h3>

                <p class="mt-3 text-sm leading-5 text-white/60">
                    Produk hadiahmu segera dikirim. Cek status pengiriman di
                    halaman pengiriman.
                </p>

                <button
                    type="button"
                    class="mt-7 w-full rounded-xl bg-[#F56D5D] px-6 py-3 text-base font-medium text-white transition hover:bg-[#ff7d6d]"
                    @click="goToShippingTransaction"
                >
                    Lihat Pengiriman
                </button>
            </div>
        </div>
    </div>
</template>
