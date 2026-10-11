<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import catKoinImage from "@/assets/cat-koin.png";
import topUpImage2 from "@/assets/icon-top-up-2.png";
import TopupSuccessModal from "@/Components/Wallet/TopupSuccessModal.vue";

const props = defineProps({
    initialAmount: {
        type: Number,
        default: 10000,
    },
});

const emit = defineEmits(["back", "topup-success"]);
const showSuccessModal = ref(false);
const successTransaction = ref(null);

const amount = ref(props.initialAmount); // Rupiah untuk pembayaran Midtrans
const loading = ref(false);
const dropdownOpen = ref(false);
const dropdownRef = ref(null);

const coinRate = 1000;

const coinPackageOptions = [
    { label: "10 Coin", coin: 10, rupiah: 10000 },
    { label: "20 Coin", coin: 20, rupiah: 20000 },
    { label: "50 Coin", coin: 50, rupiah: 50000 },
    { label: "100 Coin", coin: 100, rupiah: 100000 },
];

const selectedPackage = computed({
    get() {
        return String(amount.value);
    },
    set(value) {
        amount.value = Number(value);
    },
});

const selectedPackageLabel = computed(() => {
    const found = coinPackageOptions.find(
        (item) => item.rupiah === Number(amount.value),
    );

    return found
        ? found.label
        : `${Math.floor(Number(amount.value) / coinRate)} Coin`;
});

const formatRupiah = (value) =>
    `Rp ${Number(value ?? 0).toLocaleString("id-ID")}`;
const formatCoinFromRupiah = (value) =>
    `${Math.floor(Number(value ?? 0) / coinRate).toLocaleString("id-ID")} Coin`;

const selectPackage = (value) => {
    selectedPackage.value = String(value);
    dropdownOpen.value = false;
};

const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
};

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        dropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleClickOutside);
});

const syncPayment = async (orderId) => {
    if (!orderId) return null;

    try {
        const response = await axios.post(route("wallet.topup.sync"), {
            order_id: orderId,
        });

        return response.data?.data ?? null;
    } catch (error) {
        console.error("Gagal sync payment:", error);
        return null;
    }
};

const proceedTopup = async () => {
    loading.value = true;

    try {
        const response = await axios.post(route("wallet.topup.store"), {
            amount: amount.value,
        });

        const snapToken = response.data?.data?.snap_token;
        const orderId = response.data?.data?.order_no;

        if (!snapToken) {
            alert("Snap token tidak ditemukan.");
            return;
        }

        window.snap.pay(snapToken, {
            onSuccess: async function () {
                const syncedPayment = await syncPayment(orderId);

                successTransaction.value = {
                    id: response.data?.data?.id,
                    order_no:
                        syncedPayment?.order_no ??
                        response.data?.data?.order_no,
                    amount:
                        syncedPayment?.amount ??
                        response.data?.data?.amount ??
                        amount.value,
                    product:
                        syncedPayment?.product ??
                        formatCoinFromRupiah(
                            syncedPayment?.amount ??
                                response.data?.data?.amount ??
                                amount.value,
                        ),
                    payment_type: syncedPayment?.payment_type,
                    created_at:
                        syncedPayment?.created_at ?? new Date().toISOString(),
                    receipt_url:
                        syncedPayment?.id || response.data?.data?.id
                            ? route("transactions.receipt.topup", {
                                  paymentTransaction:
                                      syncedPayment?.id ??
                                      response.data?.data?.id,
                              })
                            : null,
                };

                showSuccessModal.value = true;
                emit("topup-success");
            },

            onPending: async function () {
                await syncPayment(orderId);
                window.location.href = route("wallet.index");
            },

            onError: function () {
                alert("Pembayaran gagal.");
            },

            onClose: function () {
                console.log("Popup ditutup.");
            },
        });
    } catch (error) {
        console.error(error);
        alert(
            error?.response?.data?.message ?? "Gagal membuat transaksi top up.",
        );
    } finally {
        loading.value = false;
    }
};

const closeSuccessModal = () => {
    showSuccessModal.value = false;
    window.location.href = route("wallet.index");
};
</script>

<template>
    <div
        class="rounded-xl bg-[#101521] p-5 shadow-[0_12px_40px_rgba(0,0,0,0.28)]"
    >
        <div
            class="mb-5 pb-3 flex items-center justify-between border-b border-white/10"
        >
            <div>
                <div class="text-xl font-medium text-white">
                    Mau top up berapa ?
                </div>
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center rounded-xl bg-white/[0.05] px-4 py-2 text-sm font-medium text-white transition hover:bg-white/[0.08]"
                @click="$emit('back')"
            >
                Kembali
            </button>
        </div>

        <div class="space-y-4">
            <div class="relative grid gap-5">
                <!-- Select Coin Package -->
                <div class="rounded-xl bg-white/[0.04] px-4 py-4">
                    <div class="text-base text-white/55">
                        Select Coin Package
                    </div>

                    <div ref="dropdownRef" class="relative mt-2">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-xl bg-transparent text-left"
                            @click.stop="toggleDropdown"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <img
                                    :src="catKoinImage"
                                    alt="Cat Koin"
                                    class="h-8 w-8 shrink-0 object-contain"
                                />

                                <div
                                    class="truncate text-xl font-semibold text-white"
                                >
                                    {{ formatCoinFromRupiah(amount) }}
                                </div>
                            </div>

                            <svg
                                class="h-5 w-5 shrink-0 text-white transition"
                                :class="{ 'rotate-180': dropdownOpen }"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </button>

                        <div
                            v-if="dropdownOpen"
                            class="absolute left-0 right-0 top-[calc(100%+12px)] z-30 overflow-hidden rounded-xl border border-white/10 bg-[#161d34] shadow-[0_18px_40px_rgba(0,0,0,0.45)]"
                        >
                            <button
                                v-for="option in coinPackageOptions"
                                :key="option.rupiah"
                                type="button"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-white/[0.06]"
                                :class="
                                    Number(amount) === option.rupiah
                                        ? 'bg-white/[0.05]'
                                        : ''
                                "
                                @click="selectPackage(option.rupiah)"
                            >
                                <img
                                    :src="catKoinImage"
                                    alt="Cat Koin"
                                    class="h-7 w-7 shrink-0 object-contain"
                                />

                                <div class="flex-1">
                                    <div
                                        class="text-lg font-semibold text-white"
                                    >
                                        {{
                                            option.coin.toLocaleString("id-ID")
                                        }}
                                        Coin
                                    </div>

                                    <div class="text-sm text-white/45">
                                        {{ formatRupiah(option.rupiah) }}
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Icon tengah -->
                <div
                    class="pointer-events-none space-y-0 absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-1/2"
                >
                    <div
                        class="flex h-16 w-16 items-center justify-center rounded-full border-2 border-white/10 bg-[#101521] text-white"
                    >
                        <img
                            :src="topUpImage2"
                            alt="top up"
                            class="h-5 w-5 shrink-0 object-contain"
                        />
                    </div>
                </div>

                <!-- You Pay -->
                <div class="rounded-xl bg-white/[0.04] px-4 py-4">
                    <div class="text-base text-white/55">You Pay</div>
                    <div class="mt-2 text-xl font-semibold text-white">
                        {{ formatRupiah(amount) }}
                    </div>
                </div>
            </div>

            <div class="border-t border-white/10 pt-4">
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-lg text-white/70">Total Bayar :</div>
                    <div class="text-2xl font-semibold text-white">
                        {{ formatRupiah(amount) }}
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-3 rounded-xl bg-[#F66455] px-6 py-3 text-base font-medium text-white transition hover:bg-[#ff7d70] disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="loading"
                    @click="proceedTopup"
                >
                    <span>{{
                        loading ? "Memproses..." : "Lanjutkan Pembayaran"
                    }}</span>
                </button>
            </div>
        </div>
    </div>

    <TopupSuccessModal
        :show="showSuccessModal"
        :transaction="successTransaction"
        @close="closeSuccessModal"
    />
</template>
