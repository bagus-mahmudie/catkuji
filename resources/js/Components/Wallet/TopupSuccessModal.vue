<script setup>
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    show: Boolean,
    transaction: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(["close"]);

const formatRupiah = (value) =>
    `Rp ${Number(value ?? 0).toLocaleString("id-ID")}`;

const formatDateTime = (value) => {
    if (!value) return "-";

    return new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "2-digit",
        year: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
    })
        .format(new Date(value))
        .replace(",", " •");
};

const paymentMethodLabel = (value) => {
    const map = {
        gopay: "Gopay",
        qris: "QRIS",
        bank_transfer: "Bank Transfer",
        credit_card: "Credit Card",
        echannel: "Mandiri Bill",
        cstore: "Convenience Store",
    };

    return map[String(value ?? "").toLowerCase()] ?? value ?? "-";
};

const copyOrderNo = async () => {
    if (!props.transaction?.order_no) return;

    await navigator.clipboard.writeText(props.transaction.order_no);
};
</script>

<template>
    <Modal
        :show="show"
        max-width="md"
        :closeable="false"
        @close="emit('close')"
    >
        <div class="relative font-sans">
            <div
                class="relative overflow-hidden rounded-t-xl bg-[#101521] px-5 pb-11 pt-8 text-white"
            >
                <!-- top success icon -->
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-xl bg-[#281f2d]"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-xl bg-[#F66455] shadow-[0_0_0_12px_rgba(246,100,85,0.08)]"
                    >
                        <i class="bi bi-check text-4xl sm:text-5xl"></i>
                    </div>
                </div>

                <div class="mt-6 text-center">
                    <h2 class="text-xl sm:text-2xl font-medium">
                        Pembayaran Berhasil
                    </h2>

                    <p
                        class="mx-auto mt-3 text-sm sm:text-base leading-snug text-white/55"
                    >
                        Top up koin kamu berhasil diproses. Koin sudah
                        ditambahkan ke akun kamu.
                    </p>

                    <div class="mt-9 text-sm sm:text-base text-white/55">
                        Total Pembayaran
                    </div>

                    <div
                        class="mt-1 sm:text-2xl text-xl font-semibold leading-none"
                    >
                        {{ formatRupiah(transaction.amount) }}
                    </div>
                </div>

                <!-- dotted separator -->
                <div class="relative my-9">
                    <div
                        class="absolute -left-12 top-1/2 h-14 w-14 -translate-y-1/2 rounded-full bg-black/75 backdrop-blur-[6px]"
                    />
                    <div
                        class="absolute -right-12 top-1/2 h-14 w-14 -translate-y-1/2 rounded-full bg-black/75 backdrop-blur-[6px]"
                    />
                    <div
                        class="h-[1px] w-full bg-[repeating-linear-gradient(to_right,rgba(255,255,255,0.3)_0px,rgba(255,255,255,0.3)_11px,transparent_11px,transparent_18px)]"
                    ></div>
                </div>

                <div class="grid grid-cols-2 gap-5">
                    <div class="rounded-lg bg-white/[0.045] p-3">
                        <div class="text-base text-white/55">No. transaksi</div>

                        <div
                            class="mt-1 flex items-center gap-2 text-sm font-normal"
                        >
                            <span class="truncate">
                                {{ transaction.order_no ?? "-" }}
                            </span>

                            <button
                                type="button"
                                class="shrink-0 text-white/80 hover:text-white"
                                @click="copyOrderNo"
                            >
                                <i class="bi bi-copy"></i>
                            </button>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white/[0.045] p-3">
                        <div class="text-base text-white/55">
                            Transaksi dibuat
                        </div>

                        <div class="mt-1 text-sm font-normal">
                            {{ formatDateTime(transaction.created_at) }}
                        </div>
                    </div>

                    <div class="rounded-lg bg-white/[0.045] p-3">
                        <div class="text-base text-white/55">Detail Produk</div>

                        <div class="mt-1 text-sm font-normal">
                            {{ transaction.product ?? "-" }}
                        </div>
                    </div>

                    <div class="rounded-lg bg-white/[0.045] p-3">
                        <div class="text-base text-white/55">Metode Bayar</div>

                        <div class="mt-1 text-sm font-normal">
                            {{ paymentMethodLabel(transaction.payment_type) }}
                        </div>
                    </div>
                </div>

                <a
                    v-if="transaction.receipt_url"
                    :href="transaction.receipt_url"
                    class="mt-8 inline-flex w-full items-center justify-center gap-3 rounded-xl bg-[#F66455] px-4 py-3 text-sm sm:text-base font-medium text-white transition hover:bg-[#ff7d70]"
                >
                    <i class="bi bi-cloud-download text-base"></i>

                    Get PDF Receipt
                </a>
            </div>

            <!-- scallop bottom -->
            <div
                class="h-5 bg-[#101521]"
                style="
                    mask: radial-gradient(
                            10px at 10px 100%,
                            transparent 98%,
                            black
                        ) -10px
                        0 / 47px 100%;
                "
            />

            <div class="flex justify-center pt-8 pb-0">
                <button
                    type="button"
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-[#F66455] text-white transition hover:bg-[#ff7d70]"
                    @click="emit('close')"
                >
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>
        </div>
    </Modal>
</template>
