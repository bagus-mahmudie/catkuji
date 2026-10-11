<script setup>
import { useForm } from "@inertiajs/vue3";
import { ref, watch } from "vue";

const props = defineProps({
    redirectTo: {
        type: String,
        default: "/",
    },
});

const emit = defineEmits(["switch", "success", "close"]);

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const form = useForm({
    email: "",
    phone: "",
    password: "",
    password_confirmation: "",
    terms: false,
    redirect_to: props.redirectTo,
});

watch(
    () => props.redirectTo,
    (value) => {
        form.redirect_to = value || "/";
    },
    { immediate: true },
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        phone: String(data.phone ?? "")
            .replace(/\D+/g, "")
            .replace(/^0+/, "")
            .replace(/^62/, ""),
    })).post(route("register.store"), {
        preserveScroll: true,
        headers: {
            "X-Skip-Global-Loader": "true",
        },
        onSuccess: () => emit("success"),
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <div class="px-5 py-6 sm:px-8 sm:py-8">
        <div>
            <h1 class="text-xl font-medium text-white sm:text-2xl">Daftar</h1>
            <p class="mt-2 text-sm text-white/55">
                Mulai bermain dan kumpulkan hadiah langka
            </p>
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <div>
                <label
                    for="register-email"
                    class="block text-sm font-medium text-white sm:text-base"
                >
                    Email
                </label>

                <div
                    class="mt-2 flex h-[50px] items-center gap-3 rounded-xl border border-white/5 bg-white/5 px-4 transition focus-within:border-[#F96855] focus-within:ring-2 focus-within:ring-[#F96855]/20"
                >
                    <svg
                        class="h-5 w-5 shrink-0 text-white/75"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <rect x="3" y="5" width="18" height="14" rx="3" />
                        <path d="M4 7l8 6 8-6" />
                    </svg>

                    <input
                        id="register-email"
                        v-model="form.email"
                        type="email"
                        autofocus
                        required
                        autocomplete="username"
                        placeholder="Masukkan Email Anda"
                        class="h-full w-full border-0 bg-transparent p-0 text-sm sm:text-base text-white placeholder:text-white/40 focus:outline-none focus:ring-0"
                    />
                </div>

                <p v-if="form.errors.email" class="mt-2 text-sm text-[#FF9D9D]">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label
                    for="register-phone"
                    class="block text-sm font-medium text-white sm:text-base"
                >
                    Nomor HP (opsional)
                </label>

                <div
                    class="mt-2 flex h-[50px] items-center gap-3 rounded-xl border border-white/5 bg-white/5 px-4 transition focus-within:border-[#F96855] focus-within:ring-2 focus-within:ring-[#F96855]/20"
                >
                    <svg
                        class="h-5 w-5 shrink-0 text-white/75"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            d="M5 4h3l1.5 4-2 1.5a16 16 0 0 0 7 7l1.5-2L20 16v3a2 2 0 0 1-2.2 2A16.8 16.8 0 0 1 3 6.2 2 2 0 0 1 5 4Z"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>

                    <span class="text-sm text-white/70 sm:text-base">+62</span>

                    <input
                        id="register-phone"
                        v-model="form.phone"
                        type="text"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        placeholder="xxx-xxxx-xxxx"
                        class="h-full w-full border-0 bg-transparent p-0 text-sm sm:text-base text-white placeholder:text-white/40 focus:outline-none focus:ring-0"
                    />
                </div>

                <p v-if="form.errors.phone" class="mt-2 text-sm text-[#FF9D9D]">
                    {{ form.errors.phone }}
                </p>
            </div>

            <div>
                <label
                    for="register-password"
                    class="block text-sm font-medium text-white sm:text-base"
                >
                    Password
                </label>

                <div
                    class="mt-2 flex h-[50px] items-center gap-3 rounded-xl border border-white/5 bg-white/5 px-4 transition focus-within:border-[#F96855] focus-within:ring-2 focus-within:ring-[#F96855]/20"
                >
                    <svg
                        class="h-5 w-5 shrink-0 text-white/75"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            d="M7 10V8a5 5 0 0 1 10 0v2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <rect x="4" y="10" width="16" height="10" rx="3" />
                        <circle cx="12" cy="15" r="1" />
                    </svg>

                    <input
                        id="register-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autocomplete="new-password"
                        placeholder="Masukkan Password"
                        class="h-full w-full border-0 bg-transparent p-0 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-0 sm:text-base"
                    />

                    <button
                        type="button"
                        class="shrink-0 text-white/60 transition hover:text-white"
                        @click="showPassword = !showPassword"
                    >
                        <svg
                            v-if="!showPassword"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"
                            />
                            <circle cx="12" cy="12" r="3" />
                        </svg>

                        <svg
                            v-else
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M3 3l18 18" />
                            <path
                                d="M10.6 10.6A2 2 0 0 0 12 14a2 2 0 0 0 1.4-.6"
                            />
                            <path
                                d="M9.9 5.2A11.1 11.1 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3 3.8"
                            />
                            <path
                                d="M6.2 6.2C3.8 7.8 2 12 2 12s3.5 7 10 7c1.6 0 3-.3 4.2-.8"
                            />
                        </svg>
                    </button>
                </div>

                <p
                    v-if="form.errors.password"
                    class="mt-2 text-sm text-[#FF9D9D]"
                >
                    {{ form.errors.password }}
                </p>
            </div>

            <div>
                <label
                    for="register-password-confirmation"
                    class="block text-sm font-medium text-white sm:text-base"
                >
                    Konfirmasi Password
                </label>

                <div
                    class="mt-2 flex h-[50px] items-center gap-3 rounded-xl border border-white/5 bg-white/5 px-4 transition focus-within:border-[#F96855] focus-within:ring-2 focus-within:ring-[#F96855]/20"
                >
                    <svg
                        class="h-5 w-5 shrink-0 text-white/75"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            d="M7 10V8a5 5 0 0 1 10 0v2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <rect x="4" y="10" width="16" height="10" rx="3" />
                        <circle cx="12" cy="15" r="1" />
                    </svg>

                    <input
                        id="register-password-confirmation"
                        v-model="form.password_confirmation"
                        :type="showPasswordConfirmation ? 'text' : 'password'"
                        required
                        autocomplete="new-password"
                        placeholder="Ketik Ulang Password"
                        class="h-full w-full border-0 bg-transparent p-0 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-0 sm:text-base"
                    />

                    <button
                        type="button"
                        class="shrink-0 text-white/60 transition hover:text-white"
                        @click="
                            showPasswordConfirmation = !showPasswordConfirmation
                        "
                    >
                        <svg
                            v-if="!showPasswordConfirmation"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"
                            />
                            <circle cx="12" cy="12" r="3" />
                        </svg>

                        <svg
                            v-else
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M3 3l18 18" />
                            <path
                                d="M10.6 10.6A2 2 0 0 0 12 14a2 2 0 0 0 1.4-.6"
                            />
                            <path
                                d="M9.9 5.2A11.1 11.1 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3 3.8"
                            />
                            <path
                                d="M6.2 6.2C3.8 7.8 2 12 2 12s3.5 7 10 7c1.6 0 3-.3 4.2-.8"
                            />
                        </svg>
                    </button>
                </div>

                <p
                    v-if="form.errors.password_confirmation"
                    class="mt-2 text-sm text-[#FF9D9D]"
                >
                    {{ form.errors.password_confirmation }}
                </p>
            </div>

            <div>
                <label class="flex items-center gap-3">
                    <input
                        v-model="form.terms"
                        type="checkbox"
                        class="h-4 w-4 rounded border-white/20 bg-transparent text-[#F96855] focus:ring-[#F96855] focus:ring-offset-0"
                    />

                    <span class="text-sm leading-2 text-white/55">
                        Dengan mendaftar, kamu menyetujui syarat dan ketentuan
                        serta kebijakan privasi catkuji
                    </span>
                </label>

                <p v-if="form.errors.terms" class="mt-2 text-sm text-[#FF9D9D]">
                    {{ form.errors.terms }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="flex h-[50px] w-full items-center justify-center rounded-xl bg-[#F96855] text-base font-medium text-white transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-70"
            >
                Daftar
            </button>

            <div class="pt-2 text-center text-sm text-white/55">
                sudah punya akun?
                <button
                    type="button"
                    class="ml-1 font-medium text-[#F96855] transition hover:text-[#ff836f]"
                    @click="emit('switch', 'login')"
                >
                    Masuk
                </button>
            </div>
        </form>
    </div>
</template>
