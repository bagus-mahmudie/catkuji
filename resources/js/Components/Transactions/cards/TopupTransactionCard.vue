<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";
import BaseTransactionCard from "./BaseTransactionCard.vue";
import TopupSuccessModal from "@/Components/Wallet/TopupSuccessModal.vue";

const showSuccessModal = ref(false);
const successTransaction = ref(null);

const props = defineProps({
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

const isPaying = ref(false);

const syncPayment = async () => {
    if (!props.item?.order_no) return null;

    try {
        const response = await axios.post(route("wallet.topup.sync"), {
            order_id: props.item.order_no,
        });

        return response.data?.data ?? null;
    } catch (error) {
        console.error("Gagal sync payment:", error);
        return null;
    }
};

const payTopup = () => {
    const snapToken = props.item?.sections?.topup?.snap_token;

    if (!snapToken) {
        alert("Snap token tidak ditemukan.");
        return;
    }

    if (!window.snap) {
        alert("Midtrans Snap belum termuat. Silakan refresh halaman.");
        return;
    }

    isPaying.value = true;

    window.snap.pay(snapToken, {
        onSuccess: async function () {
            const syncedPayment = await syncPayment();

            successTransaction.value = {
                id: syncedPayment?.id ?? props.item?.id,
                order_no: syncedPayment?.order_no ?? props.item?.order_no,
                amount: syncedPayment?.amount ?? props.item?.amount,
                product:
                    syncedPayment?.product ??
                    props.item?.sections?.topup?.product ??
                    props.item?.title,
                payment_type:
                    syncedPayment?.payment_type ??
                    props.item?.sections?.topup?.payment_type,
                created_at:
                    syncedPayment?.created_at ??
                    props.item?.created_at ??
                    new Date().toISOString(),
                receipt_url:
                    (syncedPayment?.id ?? props.item?.id)
                        ? route("transactions.receipt.topup", {
                              paymentTransaction:
                                  syncedPayment?.id ?? props.item?.id,
                          })
                        : null,
            };

            showSuccessModal.value = true;
            isPaying.value = false;
        },

        onPending: async function () {
            await syncPayment();

            router.reload({
                preserveScroll: true,
                preserveState: false,
            });
        },

        onError: function () {
            alert("Pembayaran gagal.");
        },

        onClose: function () {
            isPaying.value = false;
        },
    });
};

const closeSuccessModal = () => {
    showSuccessModal.value = false;

    router.reload({
        preserveScroll: true,
        preserveState: false,
    });
};
</script>

<template>
    <BaseTransactionCard
        :item="item"
        :expanded="expanded"
        @toggle="$emit('toggle')"
    >
        <div class="grid grid-cols-2 gap-x-4 gap-y-4 text-sm sm:text-base">
            <template v-for="row in item.sections.topup.rows" :key="row.label">
                <div class="text-white/55">{{ row.label }}</div>
                <div class="text-right font-medium text-white break-all">
                    {{ row.value }}
                </div>
            </template>
        </div>

        <div
            v-if="
                item.sections.topup.receipt_url ||
                item.sections.topup.action_url
            "
            class="mt-6 flex flex-col gap-3 sm:flex-row"
        >
            <a
                v-if="item.sections.topup.receipt_url"
                :href="item.sections.topup.receipt_url"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#F66455] px-6 py-3 text-base font-medium text-white transition hover:bg-[#ff7d70]"
            >
                <i class="bi bi-download text-lg"></i>
                <span>Download Receipt</span>
            </a>

            <button
                v-else-if="item.sections.topup.snap_token"
                type="button"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#F66455] px-6 py-3 text-base font-medium text-white transition hover:bg-[#ff7d70] disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="isPaying"
                @click="payTopup"
            >
                <i class="bi bi-credit-card text-lg"></i>
                <span>{{
                    isPaying ? "Memproses..." : item.sections.topup.action_label
                }}</span>
            </button>

            <a
                :href="item.sections.topup.support_url || '#'"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-white/[0.06] px-6 py-3 text-base font-medium text-white transition hover:bg-white/[0.1]"
            >
                <i class="bi bi-headset text-lg"></i>
                <span>Contact Support</span>
            </a>
        </div>
    </BaseTransactionCard>

    <TopupSuccessModal
        :show="showSuccessModal"
        :transaction="successTransaction"
        @close="closeSuccessModal"
    />
</template>
