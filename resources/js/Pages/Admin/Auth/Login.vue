<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import logoCatkuji from "@/assets/logo-catkuji.png";
import adminLoginIllustration from "@/assets/admin/admin-login-illustration.png";

const authMode = ref("login");

const showPassword = ref(false);
const showRegisterPassword = ref(false);
const showRegisterPasswordConfirmation = ref(false);

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const registerForm = useForm({
    email: "",
    phone: "",
    password: "",
    password_confirmation: "",
    terms: false,
});

const submit = () => {
    form.post(route("admin.login.store"), {
        onFinish: () => {
            form.reset("password");
        },
    });
};

const submitRegister = () => {
    registerForm.post(route("admin.register.store"), {
        preserveScroll: true,
        onSuccess: () => {
            registerForm.reset();
            authMode.value = "login";

            router.visit(route("admin.login"), {
                replace: true,
                preserveScroll: false,
            });
        },
        onFinish: () => {
            registerForm.reset("password", "password_confirmation");
        },
    });
};
</script>

<template>
    <Head title="Admin Login" />

    <main class="min-h-screen overflow-hidden bg-[#20211f] text-white">
        <Link
            href="/"
            class="absolute left-8 top-8 z-20 flex items-center gap-3"
        >
            <img
                :src="logoCatkuji"
                alt="Catkuji"
                class="h-12 w-12 object-contain"
            />

            <span class="text-3xl font-extrabold tracking-wide text-white">
                CATKUJI
            </span>
        </Link>

        <div
            class="grid min-h-screen grid-cols-1 items-center px-3 py-3 lg:grid-cols-[1fr_1fr]"
        >
            <!-- Left Login -->
            <section class="mx-auto w-full max-w-[500px]">
                <!-- Login Form -->
                <div v-if="authMode === 'login'">
                    <div>
                        <h1 class="text-2xl font-medium text-white sm:text-3xl">
                            Masuk
                        </h1>

                        <p class="mt-1 text-base text-white/45">
                            Login sekarang dan buka peluang menang
                        </p>
                    </div>

                    <form class="mt-9 space-y-5" @submit.prevent="submit">
                        <div>
                            <label class="text-base font-medium text-white">
                                Alamat Email
                            </label>

                            <div
                                class="mt-2 flex h-12 items-center rounded-lg border border-transparent bg-[#2b2c2a] px-4 transition focus-within:border-[#ff675c] focus-within:ring-1 focus-within:ring-[#ff675c]"
                            >
                                <i
                                    class="bi bi-envelope text-xl text-white/80"
                                ></i>

                                <input
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="username"
                                    class="ml-5 w-full border-0 bg-transparent p-0 text-base text-white placeholder:text-white/40 focus:border-0 focus:outline-none focus:ring-0"
                                    placeholder="alex.thompson@email.com"
                                />
                            </div>

                            <p
                                v-if="form.errors.email"
                                class="mt-2 text-sm text-red-400"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div>
                            <label class="text-base font-medium text-white">
                                Kata Sandi
                            </label>

                            <div
                                class="mt-2 flex h-12 items-center rounded-lg border border-transparent bg-[#2b2c2a] px-4 transition focus-within:border-[#ff675c] focus-within:ring-1 focus-within:ring-[#ff675c]"
                            >
                                <i class="bi bi-lock text-xl text-white/80"></i>

                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    class="ml-5 w-full border-0 bg-transparent p-0 text-base text-white placeholder:text-white/40 focus:border-0 focus:outline-none focus:ring-0"
                                    placeholder="••••••••"
                                />

                                <button
                                    type="button"
                                    class="ml-4 text-xl text-white/70 hover:text-white focus:outline-none"
                                    @click="showPassword = !showPassword"
                                >
                                    <i
                                        class="bi"
                                        :class="
                                            showPassword
                                                ? 'bi-eye'
                                                : 'bi-eye-slash'
                                        "
                                    ></i>
                                </button>
                            </div>

                            <p
                                v-if="form.errors.password"
                                class="mt-2 text-sm text-red-400"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between">
                            <label
                                class="flex items-center gap-3 text-sm text-white/45"
                            >
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                    class="h-4 w-4 rounded-lg border-white/30 bg-transparent text-[#ff675c] focus:ring-[#ff675c]"
                                />

                                Ingat saya
                            </label>

                            <!-- <Link
                                :href="route('password.request')"
                                class="text-sm font-medium text-[#ff675c] hover:text-[#ff7a70]"
                            >
                                Lupa Kata Sandi?
                            </Link> -->
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex h-12 w-full items-center justify-center rounded-lg bg-[#ff6259] text-base font-medium text-white transition hover:bg-[#ff5147] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span v-if="form.processing">Memproses...</span>
                            <span v-else>Masuk</span>
                        </button>
                    </form>

                    <p class="mt-7 text-center text-sm text-white/45">
                        Belum punya akun?
                        <button
                            type="button"
                            class="font-medium text-[#ff675c] hover:text-[#ff7a70]"
                            @click="authMode = 'register'"
                        >
                            Daftar
                        </button>
                    </p>
                </div>

                <!-- Register Form -->
                <div v-else>
                    <div>
                        <h1 class="text-2xl font-medium text-white sm:text-3xl">
                            Daftar
                        </h1>

                        <p class="mt-1 text-base text-white/45">
                            Mulai bermain dan kumpulkan hadiah langka
                        </p>
                    </div>

                    <form
                        class="mt-7 space-y-5"
                        @submit.prevent="submitRegister"
                    >
                        <div>
                            <label class="text-base font-medium text-white">
                                Alamat Email
                            </label>

                            <div
                                class="mt-2 flex h-12 items-center rounded-lg border border-transparent bg-[#2b2c2a] px-4 transition focus-within:border-[#ff675c] focus-within:ring-1 focus-within:ring-[#ff675c]"
                            >
                                <i
                                    class="bi bi-envelope text-xl text-white/80"
                                ></i>

                                <input
                                    v-model="registerForm.email"
                                    type="email"
                                    autocomplete="username"
                                    class="ml-5 w-full border-0 bg-transparent p-0 text-base text-white placeholder:text-white/40 focus:border-0 focus:outline-none focus:ring-0"
                                    placeholder="alex.thompson@email.com"
                                />
                            </div>

                            <p
                                v-if="registerForm.errors.email"
                                class="mt-2 text-sm text-red-400"
                            >
                                {{ registerForm.errors.email }}
                            </p>
                        </div>

                        <div>
                            <label class="text-base font-medium text-white">
                                Nomor HP (opsional)
                            </label>

                            <div
                                class="mt-2 flex h-12 items-center rounded-lg border border-transparent bg-[#2b2c2a] px-4 transition focus-within:border-[#ff675c] focus-within:ring-1 focus-within:ring-[#ff675c]"
                            >
                                <i
                                    class="bi bi-telephone text-xl text-white/80"
                                ></i>

                                <input
                                    v-model="registerForm.phone"
                                    type="tel"
                                    autocomplete="tel"
                                    class="ml-5 w-full border-0 bg-transparent p-0 text-base text-white placeholder:text-white/40 focus:border-0 focus:outline-none focus:ring-0"
                                    placeholder="+62"
                                />
                            </div>

                            <p
                                v-if="registerForm.errors.phone"
                                class="mt-2 text-sm text-red-400"
                            >
                                {{ registerForm.errors.phone }}
                            </p>
                        </div>

                        <div>
                            <label class="text-base font-medium text-white">
                                Kata Sandi
                            </label>

                            <div
                                class="mt-2 flex h-12 items-center rounded-lg border border-transparent bg-[#2b2c2a] px-4 transition focus-within:border-[#ff675c] focus-within:ring-1 focus-within:ring-[#ff675c]"
                            >
                                <i class="bi bi-lock text-xl text-white/80"></i>

                                <input
                                    v-model="registerForm.password"
                                    :type="
                                        showRegisterPassword
                                            ? 'text'
                                            : 'password'
                                    "
                                    autocomplete="new-password"
                                    class="ml-5 w-full border-0 bg-transparent p-0 text-base text-white placeholder:text-white/40 focus:border-0 focus:outline-none focus:ring-0"
                                    placeholder="••••••••"
                                />

                                <button
                                    type="button"
                                    class="ml-4 text-xl text-white/70 hover:text-white focus:outline-none"
                                    @click="
                                        showRegisterPassword =
                                            !showRegisterPassword
                                    "
                                >
                                    <i
                                        class="bi"
                                        :class="
                                            showRegisterPassword
                                                ? 'bi-eye'
                                                : 'bi-eye-slash'
                                        "
                                    ></i>
                                </button>
                            </div>

                            <p
                                v-if="registerForm.errors.password"
                                class="mt-2 text-sm text-red-400"
                            >
                                {{ registerForm.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label class="text-base font-medium text-white">
                                Konfirmasi Kata Sandi
                            </label>

                            <div
                                class="mt-2 flex h-12 items-center rounded-lg border border-transparent bg-[#2b2c2a] px-4 transition focus-within:border-[#ff675c] focus-within:ring-1 focus-within:ring-[#ff675c]"
                            >
                                <i class="bi bi-lock text-xl text-white/80"></i>

                                <input
                                    v-model="registerForm.password_confirmation"
                                    :type="
                                        showRegisterPasswordConfirmation
                                            ? 'text'
                                            : 'password'
                                    "
                                    autocomplete="new-password"
                                    class="ml-5 w-full border-0 bg-transparent p-0 text-base text-white placeholder:text-white/40 focus:border-0 focus:outline-none focus:ring-0"
                                    placeholder="••••••••"
                                />

                                <button
                                    type="button"
                                    class="ml-4 text-xl text-white/70 hover:text-white focus:outline-none"
                                    @click="
                                        showRegisterPasswordConfirmation =
                                            !showRegisterPasswordConfirmation
                                    "
                                >
                                    <i
                                        class="bi"
                                        :class="
                                            showRegisterPasswordConfirmation
                                                ? 'bi-eye'
                                                : 'bi-eye-slash'
                                        "
                                    ></i>
                                </button>
                            </div>

                            <p
                                v-if="registerForm.errors.password_confirmation"
                                class="mt-2 text-sm text-red-400"
                            >
                                {{ registerForm.errors.password_confirmation }}
                            </p>
                        </div>

                        <label
                            class="flex items-start gap-3 text-sm leading-5 text-white/45"
                        >
                            <input
                                v-model="registerForm.terms"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-white/30 bg-transparent text-[#ff675c] focus:ring-[#ff675c]"
                            />

                            <span>
                                Dengan mendaftar, kamu menyetujui syarat dan
                                ketentuan serta kebijakan privasi catkuji
                            </span>
                        </label>

                        <p
                            v-if="registerForm.errors.terms"
                            class="text-sm text-red-400"
                        >
                            {{ registerForm.errors.terms }}
                        </p>

                        <button
                            type="submit"
                            :disabled="registerForm.processing"
                            class="flex h-12 w-full items-center justify-center rounded-lg bg-[#ff6259] text-base font-medium text-white transition hover:bg-[#ff5147] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span v-if="registerForm.processing"
                                >Memproses...</span
                            >
                            <span v-else>Lanjut</span>
                        </button>
                    </form>

                    <p class="mt-7 text-center text-sm text-white/45">
                        sudah punya akun?
                        <button
                            type="button"
                            class="font-medium text-[#ff675c] hover:text-[#ff7a70]"
                            @click="authMode = 'login'"
                        >
                            Masuk
                        </button>
                    </p>
                </div>
            </section>

            <!-- Right Illustration -->
            <section
                class="relative hidden h-full items-center justify-center lg:flex"
            >
                <div
                    class="flex h-full w-full max-w-full flex-col items-center justify-center rounded-lg bg-[#2b2c2a] px-10"
                >
                    <img
                        :src="adminLoginIllustration"
                        alt="Admin login illustration"
                        class="max-h-full max-w-full object-contain"
                    />

                    <div class="mt-16 max-w-[750px] text-center">
                        <h2
                            class="text-3xl font-medium leading-snug text-white"
                        >
                            Kelola event, hadiah, transaksi, & pengiriman dalam
                            satu dashboard.
                        </h2>

                        <p
                            class="mx-auto mt-6 max-w-[500px] text-sm leading-6 text-white/30"
                        >
                            Login khusus admin untuk mengatur mekanisme gacha,
                            produk, pembayaran, inventory, dan operasional
                            platform.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
