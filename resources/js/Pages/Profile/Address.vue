<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import ProfileSectionLayout from "@/Layouts/ProfileSectionLayout.vue";

const page = usePage();

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
    address: {
        type: Object,
        default: null,
    },
    tokenBalance: {
        type: Number,
        default: 0,
    },
    catKoinBalance: {
        type: Number,
        default: 0,
    },
    walletBalance: {
        type: Number,
        default: 0,
    },
});

const form = useForm({
    recipient_name: props.address?.recipient_name ?? "",
    recipient_phone: props.address?.recipient_phone ?? "",
    province: props.address?.province ?? "",
    city: props.address?.city ?? "",
    district: props.address?.district ?? "",
    subdistrict: props.address?.subdistrict ?? "",
    postal_code: props.address?.postal_code ?? "",
    address_line: props.address?.address_line ?? "",
    address_details: props.address?.address_details ?? "",
    notes: props.address?.notes ?? "",
});

const submit = () => {
    form.put(route("profile.address.update"), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Alamat Pengiriman" />

    <ProfileSectionLayout
        active="address"
        :profile="profile"
        :token-balance="tokenBalance"
        :cat-koin-balance="catKoinBalance"
    >
        <div class="border-b border-white/10 pb-4">
            <h2 class="text-xl sm:text-2xl font-medium text-white">
                Alamat pengiriman
            </h2>
        </div>

        <div
            v-if="page.props.flash?.success"
            class="mt-5 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300"
        >
            {{ page.props.flash.success }}
        </div>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm text-white/80">
                        Nama lengkap
                    </label>
                    <input
                        v-model="form.recipient_name"
                        type="text"
                        class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                        placeholder="Budi Santoso"
                    />
                    <p
                        v-if="form.errors.recipient_name"
                        class="mt-2 text-sm text-[#FF8D85]"
                    >
                        {{ form.errors.recipient_name }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm text-white/80">
                        Nomor WhatsApp
                    </label>
                    <input
                        v-model="form.recipient_phone"
                        type="text"
                        class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                        placeholder="+62 812 3456 7890"
                    />
                    <p
                        v-if="form.errors.recipient_phone"
                        class="mt-2 text-sm text-[#FF8D85]"
                    >
                        {{ form.errors.recipient_phone }}
                    </p>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm text-white/80">
                        Provinsi
                    </label>
                    <input
                        v-model="form.province"
                        type="text"
                        class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                        placeholder="DKI Jakarta"
                    />
                    <p
                        v-if="form.errors.province"
                        class="mt-2 text-sm text-[#FF8D85]"
                    >
                        {{ form.errors.province }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm text-white/80">
                        Kota / Kabupaten
                    </label>
                    <input
                        v-model="form.city"
                        type="text"
                        class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                        placeholder="Kota Jakarta Selatan"
                    />
                    <p
                        v-if="form.errors.city"
                        class="mt-2 text-sm text-[#FF8D85]"
                    >
                        {{ form.errors.city }}
                    </p>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm text-white/80">
                        Kecamatan
                    </label>
                    <input
                        v-model="form.district"
                        type="text"
                        class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                        placeholder="Pasar Minggu"
                    />
                    <p
                        v-if="form.errors.district"
                        class="mt-2 text-sm text-[#FF8D85]"
                    >
                        {{ form.errors.district }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm text-white/80">
                        Kelurahan
                    </label>
                    <input
                        v-model="form.subdistrict"
                        type="text"
                        class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                        placeholder="Pejaten Barat"
                    />
                    <p
                        v-if="form.errors.subdistrict"
                        class="mt-2 text-sm text-[#FF8D85]"
                    >
                        {{ form.errors.subdistrict }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm text-white/80">
                        Kode Pos
                    </label>
                    <input
                        v-model="form.postal_code"
                        type="text"
                        class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                        placeholder="12510"
                    />
                    <p
                        v-if="form.errors.postal_code"
                        class="mt-2 text-sm text-[#FF8D85]"
                    >
                        {{ form.errors.postal_code }}
                    </p>
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm text-white/80">
                    Nama jalan, Gedung, No. Rumah
                </label>
                <input
                    v-model="form.address_line"
                    type="text"
                    class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                    placeholder="Jl. Melati No. 12, Ruko Green Garden Blok B"
                />
                <p
                    v-if="form.errors.address_line"
                    class="mt-2 text-sm text-[#FF8D85]"
                >
                    {{ form.errors.address_line }}
                </p>
            </div>

            <div>
                <label class="mb-2 block text-sm text-white/80">
                    Detail Lainnya (Cth: Blok / Unit No., Patokan)
                </label>
                <input
                    v-model="form.address_details"
                    type="text"
                    class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                    placeholder="Blok A3, dekat Indomaret, sebelah rumah sakit"
                />
                <p
                    v-if="form.errors.address_details"
                    class="mt-2 text-sm text-[#FF8D85]"
                >
                    {{ form.errors.address_details }}
                </p>
            </div>

            <div>
                <label class="mb-2 block text-sm text-white/80">
                    Catatan (opsional)
                </label>
                <textarea
                    v-model="form.notes"
                    rows="5"
                    class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                    placeholder="Cth: blok, unit, patokan"
                />
                <p v-if="form.errors.notes" class="mt-2 text-sm text-[#FF8D85]">
                    {{ form.errors.notes }}
                </p>
            </div>

            <p
                class="flex items-center justify-center text-sm leading-6 text-white/55"
            >
                Pastikan alamat kamu lengkap dan benar agar kurir mudah
                menemukan lokasi pengiriman.
            </p>

            <div class="flex flex-col gap-3 sm:flex-row">
                <Link
                    :href="route('home')"
                    class="flex w-full items-center justify-center rounded-xl bg-white/5 px-6 py-3 text-base font-medium text-white/80 transition hover:bg-white/10"
                >
                    Nanti Saja
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#F66455] px-8 py-3 text-base font-medium text-white transition hover:bg-[#ff7a6d] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span>{{
                        form.processing ? "Menyimpan..." : "Simpan"
                    }}</span>
                    <i class="bi bi-arrow-right" />
                </button>
            </div>
        </form>
    </ProfileSectionLayout>
</template>
