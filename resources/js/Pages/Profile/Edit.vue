<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import { resolveAvatarUrl } from "@/Utils/avatar";

const page = usePage();

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
    profile: {
        type: Object,
        required: true,
    },
});

const activeTab = ref("profile");
const previewAvatar = ref(null);
const fileName = ref("");

const profileForm = useForm({
    name: props.profile?.name ?? "",
    email: props.profile?.email ?? "",
});

const avatarForm = useForm({
    avatar: null,
});

const openTopupPage = () => {
    window.location.href = route("wallet.index");
};

const submitProfile = () => {
    profileForm.patch(route("profile.update"), {
        preserveScroll: true,
    });
};

const selectAvatarFile = (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    avatarForm.avatar = file;
    fileName.value = file.name;

    const reader = new FileReader();
    reader.onload = (e) => {
        previewAvatar.value = e.target?.result;
    };
    reader.readAsDataURL(file);
};

const submitAvatar = () => {
    avatarForm.post(route("profile.avatar.update"), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            avatarForm.reset("avatar");
            fileName.value = "";
        },
    });
};

const currentAvatar = computed(() => {
    if (previewAvatar.value) {
        return previewAvatar.value;
    }

    return resolveAvatarUrl(props.profile);
});
</script>

<template>
    <Head title="Edit Profile" />

    <div class="min-h-screen bg-[#0C0F19] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar @topup-click="openTopupPage" />

            <div class="mt-8 grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                <aside class="h-fit rounded-xl bg-[#101521] p-5">
                    <div class="space-y-2">
                        <button
                            type="button"
                            class="flex w-full items-center gap-4 rounded-xl px-4 py-3 text-left transition"
                            :class="
                                activeTab === 'profile'
                                    ? 'bg-white/[0.04] text-white'
                                    : 'text-white/55 hover:bg-white/[0.03] hover:text-white'
                            "
                            @click="activeTab = 'profile'"
                        >
                            <span
                                class="h-8 w-[3px] rounded-full"
                                :class="
                                    activeTab === 'profile'
                                        ? 'bg-[#F66455]'
                                        : 'bg-white/60'
                                "
                            />
                            <span class="text-base font-medium">
                                Edit profile
                            </span>
                        </button>

                        <button
                            type="button"
                            class="flex w-full items-center gap-4 rounded-xl px-4 py-3 text-left transition"
                            :class="
                                activeTab === 'avatar'
                                    ? 'bg-white/[0.04] text-white'
                                    : 'text-white/55 hover:bg-white/[0.03] hover:text-white'
                            "
                            @click="activeTab = 'avatar'"
                        >
                            <span
                                class="h-8 w-[3px] rounded-full"
                                :class="
                                    activeTab === 'avatar'
                                        ? 'bg-[#F66455]'
                                        : 'bg-white/60'
                                "
                            />
                            <span class="text-base font-medium">
                                Gambar Profile
                            </span>
                        </button>
                    </div>
                </aside>

                <section class="rounded-xl bg-[#101521] p-5 md:p-6">
                    <div
                        v-if="page.props.flash?.success"
                        class="mb-5 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300"
                    >
                        {{ page.props.flash.success }}
                    </div>

                    <div
                        v-if="page.props.flash?.error"
                        class="mb-5 rounded-xl border border-red-400/20 bg-red-500/10 px-4 py-3 text-sm text-red-300"
                    >
                        {{ page.props.flash.error }}
                    </div>

                    <template v-if="activeTab === 'profile'">
                        <div class="border-b border-white/10 pb-4">
                            <h1 class="text-xl font-medium text-white">
                                Informasi Dasar
                            </h1>
                        </div>

                        <form
                            class="mt-6 space-y-6"
                            @submit.prevent="submitProfile"
                        >
                            <div>
                                <label class="mb-2 block text-sm text-white/80">
                                    Nama
                                </label>
                                <input
                                    v-model="profileForm.name"
                                    type="text"
                                    class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                                    placeholder="John doe"
                                />
                                <p
                                    v-if="profileForm.errors.name"
                                    class="mt-2 text-sm text-[#FF8D85]"
                                >
                                    {{ profileForm.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm text-white/80">
                                    Email
                                </label>
                                <input
                                    v-model="profileForm.email"
                                    type="email"
                                    class="w-full rounded-xl border border-white/10 bg-[#161C2B] px-4 py-3 text-white outline-none transition placeholder:text-white/25 focus:border-[#F66455]"
                                    placeholder="example@gmail.com"
                                />
                                <p
                                    v-if="profileForm.errors.email"
                                    class="mt-2 text-sm text-[#FF8D85]"
                                >
                                    {{ profileForm.errors.email }}
                                </p>
                            </div>

                            <div class="pt-2">
                                <button
                                    type="submit"
                                    :disabled="profileForm.processing"
                                    class="inline-flex items-center justify-center rounded-xl bg-[#F66455] px-8 py-3 text-base font-medium text-white transition hover:bg-[#ff7a6d] disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {{
                                        profileForm.processing
                                            ? "Menyimpan..."
                                            : "Simpan Perubahan"
                                    }}
                                </button>
                            </div>
                        </form>
                    </template>

                    <template v-else>
                        <div
                            class="rounded-xl border border-dashed border-white/15 bg-white/[0.02] p-5 md:p-8"
                        >
                            <div
                                class="mx-auto flex max-w-[420px] flex-col items-center justify-center text-center"
                            >
                                <div
                                    class="mb-6 flex h-32 w-32 items-center justify-center overflow-hidden rounded-full border border-white/10 bg-[#161C2B]"
                                >
                                    <img
                                        :src="currentAvatar"
                                        alt="Preview Avatar"
                                        class="h-full w-full object-cover"
                                    />
                                </div>

                                <label
                                    class="flex w-full max-w-[260px] cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-white/20 bg-[#161C2B] px-6 py-8 text-center transition hover:border-[#F66455]/50"
                                >
                                    <div
                                        class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-white/5"
                                    >
                                        <i
                                            class="bi bi-camera text-2xl text-white"
                                        />
                                    </div>

                                    <p class="text-lg font-medium text-white">
                                        Tambah Foto
                                    </p>

                                    <input
                                        type="file"
                                        class="hidden"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        @change="selectAvatarFile"
                                    />
                                </label>

                                <div class="mt-8 text-center">
                                    <p class="text-lg font-medium text-white">
                                        Upload file atau seret ke sini
                                    </p>
                                    <p class="mt-2 text-sm text-white/50">
                                        Format JPEG/PNG/WEBP (maks. 50 MB)
                                    </p>
                                </div>

                                <p
                                    v-if="fileName"
                                    class="mt-4 text-sm text-white/65"
                                >
                                    File dipilih: {{ fileName }}
                                </p>

                                <p
                                    v-if="avatarForm.errors.avatar"
                                    class="mt-4 text-sm text-[#FF8D85]"
                                >
                                    {{ avatarForm.errors.avatar }}
                                </p>

                                <div class="mt-8">
                                    <button
                                        type="button"
                                        :disabled="
                                            !avatarForm.avatar ||
                                            avatarForm.processing
                                        "
                                        class="inline-flex items-center justify-center rounded-xl bg-[#202739] px-8 py-3 text-base font-medium text-white transition hover:bg-[#2a3348] disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="submitAvatar"
                                    >
                                        {{
                                            avatarForm.processing
                                                ? "Mengupload..."
                                                : "Pilih File"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </section>
            </div>
        </div>

        <AppFooter />
    </div>
</template>
