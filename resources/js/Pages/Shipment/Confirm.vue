<script setup>
import { computed, ref, watch } from "vue";
import axios from "axios";
import { Head, router } from "@inertiajs/vue3";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import catKoinImage from "@/assets/cat-koin.png";
import { resolvePrizeImagePath } from "@/Utils/resolvePrizeImage";

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    addresses: {
        type: Array,
        default: () => [],
    },
    defaultAddressId: {
        type: Number,
        default: null,
    },
});

const selectedAddressId = ref(
    props.defaultAddressId ?? props.addresses?.[0]?.id ?? null,
);
const rates = ref([]);
const ratesLoading = ref(false);
const selectedRateKey = ref(null);
const note = ref("");
const checkoutLoading = ref(false);
const ratesError = ref("");

const selectedAddress = computed(
    () =>
        props.addresses.find((item) => item.id === selectedAddressId.value) ??
        null,
);

const productCount = computed(() =>
    props.items.reduce((sum, item) => sum + Number(item.qty ?? 0), 0),
);

const selectedRate = computed(() => {
    return (
        rates.value.find((item) => item.key === selectedRateKey.value) ?? null
    );
});

const subtotalValue = computed(() => 0);
const discountValue = computed(() => 0);
const shippingValue = computed(() =>
    Number(selectedRate.value?.shipping_cost ?? 0),
);
const totalValue = computed(
    () => subtotalValue.value + shippingValue.value - discountValue.value,
);

const formatCurrency = (value) =>
    `Rp ${Number(value ?? 0).toLocaleString("id-ID")}`;

const rarityLabel = (rarity) => {
    const raw = String(rarity ?? "")
        .toUpperCase()
        .trim();

    switch (raw) {
        case "UR":
            return "Last One";
        case "SSR":
            return "Gold";
        case "SR":
            return "Silver";
        case "R":
        case "C":
        default:
            return "Bronze";
    }
};

const rarityClass = (rarity) => {
    const label = rarityLabel(rarity);

    switch (label) {
        case "Last One":
            return "text-[#FF7A66]";
        case "Gold":
            return "text-[#F4B000]";
        case "Silver":
            return "text-[#B9C2D0]";
        default:
            return "text-[#A56A43]";
    }
};

const openTopupPage = () => {
    window.location.href = route("wallet.index");
};

const normalizeRate = (rate, index) => {
    const courierName =
        rate.courier_name || rate.company || rate.courier_code || "Courier";
    const serviceName = rate.service_name || rate.service_code || "Service";
    const estimate =
        rate.estimated_days ||
        rate.shipment_duration_range ||
        rate.duration ||
        "-";

    return {
        ...rate,
        key: `${rate.courier_code}-${rate.service_code}-${index}`,
        courier_name: courierName,
        service_name: serviceName,
        shipping_cost: Number(rate.shipping_cost ?? 0),
        estimated_text: String(estimate),
    };
};

const fetchRates = async () => {
    if (!selectedAddressId.value) return;

    ratesLoading.value = true;
    ratesError.value = "";
    rates.value = [];
    selectedRateKey.value = null;

    try {
        const response = await axios.post(route("shipment.rates"), {
            address_id: selectedAddressId.value,
        });

        const normalized = (response.data?.rates ?? []).map(normalizeRate);
        rates.value = normalized;

        if (normalized.length > 0) {
            selectedRateKey.value = normalized[0].key;
        }
    } catch (error) {
        ratesError.value =
            error?.response?.data?.message ||
            "Gagal mengambil jasa pengiriman dari Biteship.";
    } finally {
        ratesLoading.value = false;
    }
};

watch(
    () => selectedAddressId.value,
    () => {
        if (selectedAddressId.value) {
            fetchRates();
        }
    },
    { immediate: true },
);

const submitCheckout = () => {
    if (
        !selectedAddressId.value ||
        !selectedRate.value ||
        checkoutLoading.value
    )
        return;

    checkoutLoading.value = true;

    router.post(
        route("shipment.checkout"),
        {
            address_id: selectedAddressId.value,
            courier_code: selectedRate.value.courier_code,
            courier_name: selectedRate.value.courier_name,
            service_code: selectedRate.value.service_code,
            service_name: selectedRate.value.service_name,
            shipping_cost: selectedRate.value.shipping_cost,
            note: note.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                checkoutLoading.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Konfirmasi Pengiriman" />

    <div class="min-h-screen bg-[#060C18] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar @topup-click="openTopupPage" />

            <section
                class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[1.03fr_1fr]"
            >
                <div class="space-y-6">
                    <div class="rounded-xl bg-[#101521] p-5">
                        <div
                            class="flex items-center justify-between gap-4 border-b border-white/10 pb-4"
                        >
                            <h2 class="text-xl font-medium">
                                Alamat pengiriman
                            </h2>

                            <a
                                :href="route('profile.address.edit')"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#F56D5D] px-5 py-3 text-sm font-medium text-white transition hover:bg-[#ff7d6d]"
                            >
                                <i class="bi bi-pencil-square text-base"></i>
                                <span>Edit</span>
                            </a>
                        </div>

                        <template v-if="addresses.length">
                            <div class="mt-5 grid gap-3">
                                <label
                                    v-for="address in addresses"
                                    :key="address.id"
                                    class="cursor-pointer rounded-xl px-4 py-4 transition"
                                    :class="
                                        address.id === selectedAddressId
                                            ? ' bg-white/5'
                                            : ' bg-[#0D1729] '
                                    "
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="text-base font-medium text-white"
                                            >
                                                {{ address.recipient_name }},
                                                {{ address.recipient_phone }}
                                            </div>

                                            <div
                                                class="mt-3 rounded-xl bg-white/5 px-4 py-3 text-sm leading-7 text-white/55"
                                            >
                                                {{ address.full_address }}
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </template>

                        <div
                            v-else
                            class="mt-5 rounded-xl border border-dashed border-white/10 px-4 py-5 text-sm text-white/60"
                        >
                            Anda belum memiliki alamat. Tambahkan alamat
                            terlebih dahulu.
                        </div>
                    </div>

                    <div class="rounded-xl bg-[#101521] p-5">
                        <div class="border-b border-white/10 pb-4">
                            <h2 class="text-xl font-medium">
                                Pilih Jasa Pengiriman
                            </h2>
                        </div>

                        <div v-if="ratesLoading" class="space-y-3 pt-5">
                            <div
                                v-for="n in 4"
                                :key="n"
                                class="h-[86px] animate-pulse rounded-xl bg-white/5"
                            />
                        </div>

                        <div
                            v-else-if="ratesError"
                            class="pt-5 text-sm text-red-300"
                        >
                            {{ ratesError }}
                        </div>

                        <div
                            v-else-if="!rates.length"
                            class="pt-5 text-sm text-white/60"
                        >
                            Belum ada jasa pengiriman tersedia untuk alamat ini.
                        </div>

                        <div v-else class="space-y-4 pt-5">
                            <label
                                v-for="rate in rates"
                                :key="rate.key"
                                class="block cursor-pointer rounded-xl border px-4 py-4 transition"
                                :class="
                                    rate.key === selectedRateKey
                                        ? ' bg-white/5'
                                        : 'border-white/10 bg-white/5 hover:border-white/20'
                                "
                            >
                                <div class="flex items-center gap-4">
                                    <input
                                        v-model="selectedRateKey"
                                        :value="rate.key"
                                        type="radio"
                                        class="h-4 w-4 accent-[#F56D5D]"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="text-lg font-medium text-white"
                                        >
                                            {{
                                                formatCurrency(
                                                    rate.shipping_cost,
                                                )
                                            }}
                                            <span class="px-2 text-white/40"
                                                >•</span
                                            >
                                            {{ rate.service_name }}
                                        </div>

                                        <div class="mt-1 text-sm text-white/45">
                                            Garansi tiba
                                            {{ rate.estimated_text }}
                                        </div>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <div
                                            class="text-sm font-medium text-white/75"
                                        >
                                            {{ rate.courier_name }}
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="rounded-xl bg-[#101521] p-5">
                        <div class="border-b border-white/10 pb-4">
                            <h2 class="text-xl font-medium">Pesan</h2>
                        </div>

                        <div class="pt-5">
                            <textarea
                                v-model="note"
                                rows="4"
                                class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-4 text-sm text-white outline-none placeholder:text-white/30"
                                placeholder="(Opsional) Tinggalkan Pesan"
                            />
                        </div>
                    </div>
                </div>

                <div>
                    <div class="sticky top-6 rounded-xl bg-[#101521] p-5">
                        <div class="border-b border-white/10 pb-4">
                            <h2 class="text-xl font-medium">Produk Dikirim</h2>
                        </div>

                        <div class="space-y-2 pt-5">
                            <div
                                v-for="item in items"
                                :key="`${item.gacha_prize_id}-${item.qty}`"
                                class="flex gap-4 rounded-xl bg-[#0C0F19] p-4"
                            >
                                <div
                                    class="h-32 w-32 shrink-0 overflow-hidden rounded-[16px]"
                                >
                                    <img
                                        v-if="resolvePrizeImagePath(item.image)"
                                        :src="resolvePrizeImagePath(item.image)"
                                        :alt="item.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center bg-[#0b1324] px-2 text-center text-xs text-white/45"
                                    >
                                        No Image
                                    </div>
                                </div>

                                <div
                                    class="flex min-w-0 flex-1 flex-col justify-between"
                                >
                                    <div>
                                        <div
                                            :class="[
                                                'text-sm font-medium sm:text-base',
                                                rarityClass(
                                                    item.rarity_code ??
                                                        item.rarity,
                                                ),
                                            ]"
                                        >
                                            {{
                                                rarityLabel(
                                                    item.rarity_code ??
                                                        item.rarity,
                                                )
                                            }}
                                        </div>

                                        <h3
                                            class="mt-1 line-clamp-2 text-base leading-tight text-white sm:text-lg sm:leading-6"
                                        >
                                            {{ item.name }}
                                        </h3>
                                    </div>

                                    <div>
                                        <div
                                            class="text-sm text-white/55 sm:text-base"
                                        >
                                            Harga Jual
                                        </div>

                                        <div
                                            class="flex items-center gap-3 text-base font-medium leading-none text-white sm:text-lg"
                                        >
                                            <span>{{ item.qty }}X</span>

                                            <span
                                                class="flex items-center gap-1 text-white"
                                            >
                                                <img
                                                    :src="catKoinImage"
                                                    alt="Cat Koin"
                                                    class="h-4 w-4 sm:h-5 sm:w-5 object-contain"
                                                />
                                                <span>{{
                                                    Number(
                                                        item.sellback_price ??
                                                            0,
                                                    ).toLocaleString("id-ID")
                                                }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="shrink-0 text-sm font-medium text-[#FF5A5A] sm:text-base"
                                >
                                    {{ item.qty }}X
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 rounded-xl bg-white/5 px-4 py-4">
                            <div
                                class="flex items-center justify-between text-sm text-white/55"
                            >
                                <span
                                    >Ongkir ({{
                                        selectedRate?.service_name ?? "-"
                                    }})</span
                                >
                                <span class="font-medium text-white">{{
                                    formatCurrency(shippingValue)
                                }}</span>
                            </div>

                            <div
                                class="mt-3 flex items-center justify-between text-sm text-white/55"
                            >
                                <span>Diskon Promo</span>
                                <span class="font-medium text-white"
                                    >- {{ formatCurrency(discountValue) }}</span
                                >
                            </div>

                            <div
                                class="mt-4 flex items-center justify-between border-t border-white/10 pt-4 text-lg font-medium text-white"
                            >
                                <span>Total Bayar</span>
                                <span>{{ formatCurrency(totalValue) }}</span>
                            </div>
                        </div>

                        <!-- <div
                            class="mt-5 rounded-xl bg-[#F56D5D] px-4 py-3 text-sm font-medium text-white"
                        >
                            Voucher sukses digunakan
                        </div> -->

                        <button
                            type="button"
                            class="mt-3 flex w-full items-center justify-between rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/80"
                        >
                            <span>Masukkan voucher</span>
                            <span>›</span>
                        </button>

                        <button
                            type="button"
                            class="mt-6 w-full rounded-xl bg-[#F56D5D] px-6 py-4 text-base font-medium text-white transition hover:bg-[#ff7d6d] disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="
                                !selectedAddressId ||
                                !selectedRate ||
                                checkoutLoading
                            "
                            @click="submitCheckout"
                        >
                            {{
                                checkoutLoading
                                    ? "Memproses..."
                                    : "Buat Pesanan →"
                            }}
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <AppFooter />
    </div>
</template>
