<script setup>
import { Head, router, useForm } from "@inertiajs/vue3";
import { openAuthModal } from "@/Utils/authModal";

defineProps({
    status: {
        type: String,
        default: "",
    },
});

const form = useForm({
    email: "",
});

const submit = () => {
    form.post(route("password.email"), {
        preserveScroll: true,
        headers: {
            "X-Skip-Global-Loader": "true",
        },
    });
};

const closePage = () => {
    router.visit(route("home"));
};

const backToLogin = () => {
    router.visit(route("home"), {
        onSuccess: () => {
            setTimeout(() => {
                openAuthModal("login");
            }, 50);
        },
    });
};
</script>

<template>
    <Head title="Lupa Kata Sandi" />

    <main
        class="min-h-screen bg-[#070B14] px-4 py-6 text-white sm:flex sm:items-center sm:justify-center"
    >
        <div class="fixed inset-0 overflow-hidden">
            <div
                class="absolute -left-24 top-10 h-72 w-72 rounded-full bg-[#F96855]/20 blur-[90px]"
            ></div>
            <div
                class="absolute -right-24 bottom-10 h-72 w-72 rounded-full bg-[#3B82F6]/10 blur-[90px]"
            ></div>
        </div>

        <section
            class="relative mx-auto flex min-h-[calc(100vh-48px)] w-full max-w-[520px] items-center justify-center sm:min-h-0"
        >
            <div class="w-full overflow-hidden rounded-xl bg-[#101521]/95">
                <div class="px-3 py-3 sm:px-4 sm:py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-medium text-white">
                                Lupa Kata Sandi
                            </h1>

                            <p
                                class="mt-2 text-xs leading-5 text-white/50 sm:text-sm"
                            >
                                Masukkan email yang terdaftar. Kami akan
                                mengirimkan link untuk membuat kata sandi baru.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/5 text-white/60 transition hover:bg-white/10 hover:text-white"
                            aria-label="Tutup halaman"
                            @click="closePage"
                        >
                            <i class="bi bi-x text-2xl leading-none"></i>
                        </button>
                    </div>

                    <div
                        v-if="status"
                        class="mt-5 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm leading-6 text-emerald-200"
                    >
                        {{ status }}
                    </div>

                    <form
                        class="mt-7 space-y-3 sm:space-y-4"
                        @submit.prevent="submit"
                    >
                        <div>
                            <label
                                for="forgot-email"
                                class="block text-sm font-medium text-white sm:text-base"
                            >
                                Alamat Email
                            </label>

                            <div
                                class="mt-2 flex h-[50px] items-center gap-3 rounded-lg border border-white/5 bg-white/5 px-4 transition focus-within:border-[#F96855] focus-within:ring-2 focus-within:ring-[#F96855]/20"
                            >
                                <svg
                                    class="h-5 w-5 shrink-0 text-white/75"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <rect
                                        x="3"
                                        y="5"
                                        width="18"
                                        height="14"
                                        rx="3"
                                    />
                                    <path d="M4 7l8 6 8-6" />
                                </svg>

                                <input
                                    id="forgot-email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="Masukkan Email Anda"
                                    class="h-full w-full border-0 bg-transparent p-0 text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-0 sm:text-base"
                                />
                            </div>

                            <p
                                v-if="form.errors.email"
                                class="mt-2 text-sm text-[#FF9D9D]"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex h-[50px] w-full items-center justify-center rounded-lg bg-[#F96855] text-base font-medium text-white transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span v-if="!form.processing">
                                Kirim Link Reset Password
                            </span>
                            <span v-else>Mengirim...</span>
                        </button>

                        <div class="text-center">
                            <button
                                type="button"
                                class="text-sm font-medium text-white/60 transition hover:text-white"
                                @click="backToLogin"
                            >
                                Kembali ke halaman masuk
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
</template>
