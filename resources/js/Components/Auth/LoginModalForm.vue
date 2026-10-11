<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import { ref, watch } from "vue";

const redirectToOauth = (provider) => {
    const url =
        route("oauth.redirect", provider) +
        `?redirect_to=${encodeURIComponent(props.redirectTo || "/")}`;

    globalThis.location.href = url;
};

const props = defineProps({
    redirectTo: {
        type: String,
        default: "/",
    },
});

const emit = defineEmits(["switch", "success", "close"]);

const showPassword = ref(false);

const form = useForm({
    email: "",
    password: "",
    remember: false,
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
    form.post(route("login.store"), {
        preserveScroll: true,
        headers: {
            "X-Skip-Global-Loader": "true",
        },
        onSuccess: () => emit("success"),
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <div class="px-5 py-6 sm:px-8 sm:py-8">
        <div>
            <h1 class="text-xl font-medium text-white sm:text-2xl">Masuk</h1>
            <p class="mt-2 text-sm text-white/55">
                Login sekarang dan buka peluang menang
            </p>
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <div>
                <label
                    for="login-email"
                    class="block text-sm font-medium text-white sm:text-base"
                >
                    Alamat Email
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
                        id="login-email"
                        v-model="form.email"
                        type="email"
                        autofocus
                        required
                        autocomplete="username"
                        placeholder="Masukkan Email Anda"
                        class="h-full w-full border-0 bg-transparent p-0 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-0 sm:text-base"
                    />
                </div>

                <p v-if="form.errors.email" class="mt-2 text-sm text-[#FF9D9D]">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label
                    for="login-password"
                    class="block text-sm font-medium text-white sm:text-base"
                >
                    Kata Sandi
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
                        id="login-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
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

            <div class="flex items-center justify-between gap-4">
                <label class="inline-flex items-center gap-3">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="h-5 w-5 rounded border-white/20 bg-transparent text-[#F96855] focus:ring-[#F96855] focus:ring-offset-0"
                    />
                    <span class="text-sm text-white/70">Ingat saya</span>
                </label>

                <Link
                    :href="route('password.request')"
                    class="text-sm font-medium text-[#F96855] transition hover:text-[#ff836f]"
                    @click="emit('close')"
                >
                    Lupa Kata Sandi?
                </Link>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="flex h-[50px] w-full items-center justify-center rounded-xl bg-[#F96855] text-base font-medium text-white transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-70"
            >
                Masuk
            </button>

            <div class="flex items-center gap-4 py-1">
                <div class="h-px flex-1 bg-white/10"></div>
                <span class="text-base text-white/55">Or</span>
                <div class="h-px flex-1 bg-white/10"></div>
            </div>

            <button
                type="button"
                class="flex h-[50px] w-full items-center justify-center gap-3 rounded-xl border border-white/5 bg-white/5 text-sm sm:text-base font-medium text-white transition hover:border-white/10 hover:bg-[#16203A]"
                @click="redirectToOauth('google')"
            >
                <svg class="h-5 w-5" viewBox="0 0 48 48">
                    <path
                        fill="#FFC107"
                        d="M43.6 20.5H42V20H24v8h11.3C33.6 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12S17.4 12 24 12c3 0 5.7 1.1 7.8 3l5.7-5.7C34.1 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.3-.4-3.5Z"
                    />
                    <path
                        fill="#FF3D00"
                        d="M6.3 14.7l6.6 4.8C14.7 15 18.9 12 24 12c3 0 5.7 1.1 7.8 3l5.7-5.7C34.1 6.1 29.3 4 24 4c-7.7 0-14.3 4.3-17.7 10.7Z"
                    />
                    <path
                        fill="#4CAF50"
                        d="M24 44c5.2 0 9.9-2 13.5-5.2l-6.2-5.2c-2 1.5-4.5 2.4-7.3 2.4-5.2 0-9.5-3.3-11.1-8l-6.5 5c3.3 6.5 10 11 17.6 11Z"
                    />
                    <path
                        fill="#1976D2"
                        d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.1 5.6l.1-.1 6.2 5.2C37.1 38.4 44 33 44 24c0-1.3-.1-2.3-.4-3.5Z"
                    />
                </svg>

                <span>Continue dengan Google</span>
            </button>

            <button
                type="button"
                class="flex h-[50px] w-full items-center justify-center gap-3 rounded-xl border border-white/5 bg-white/5 text-sm sm:text-base font-medium text-white transition hover:border-white/10 hover:bg-[#16203A]"
                @click="redirectToOauth('facebook')"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="12" fill="#1877F2" />
                    <path
                        d="M13.4 20v-7h2.3l.4-2.7h-2.7V8.6c0-.8.2-1.3 1.3-1.3H16V4.9c-.2 0-.9-.1-1.8-.1-1.8 0-3 1.1-3 3.2v2.3H9V13h2.4v7h2Z"
                        fill="white"
                    />
                </svg>

                <span>Continue dengan Facebook</span>
            </button>

            <div class="pt-2 text-center text-sm text-white/55">
                Belum punya akun?
                <button
                    type="button"
                    class="ml-1 font-medium text-[#F96855] transition hover:text-[#ff836f]"
                    @click="emit('switch', 'register')"
                >
                    Daftar
                </button>
            </div>
        </form>
    </div>
</template>
