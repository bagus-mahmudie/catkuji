<script setup>
import { useForm } from "@inertiajs/vue3";
import { computed, nextTick, ref, watch } from "vue";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    email: {
        type: String,
        default: "",
    },
    status: {
        type: String,
        default: "",
    },
    context: {
        type: String,
        default: "login",
    },
});

const emit = defineEmits(["close"]);

const inputRefs = ref([]);
const codeInputs = ref(["", "", "", "", "", ""]);

const form = useForm({
    email: props.email,
    code: "",
    context: props.context,
});

watch(
    () => props.email,
    (value) => {
        form.email = value || "";
    },
    { immediate: true },
);

watch(
    () => props.context,
    (value) => {
        form.context = value || "login";
    },
    { immediate: true },
);

watch(
    () => props.show,
    async (value) => {
        if (value) {
            codeInputs.value = ["", "", "", "", "", ""];
            form.code = "";
            form.clearErrors();

            await nextTick();
            inputRefs.value[0]?.focus();
        }
    },
);

const fullCode = computed(() => codeInputs.value.join(""));

const syncCode = () => {
    form.code = fullCode.value;
};

const handleInput = async (index, event) => {
    const raw = String(event.target.value || "");
    const digit = raw.replace(/\D/g, "").slice(-1);

    codeInputs.value[index] = digit;
    syncCode();

    if (digit && index < 5) {
        await nextTick();
        inputRefs.value[index + 1]?.focus();
    }
};

const handleKeydown = async (index, event) => {
    if (event.key === "Backspace" && !codeInputs.value[index] && index > 0) {
        await nextTick();
        inputRefs.value[index - 1]?.focus();
    }

    if (event.key === "ArrowLeft" && index > 0) {
        event.preventDefault();
        inputRefs.value[index - 1]?.focus();
    }

    if (event.key === "ArrowRight" && index < 5) {
        event.preventDefault();
        inputRefs.value[index + 1]?.focus();
    }

    if (event.key === "Escape") {
        emit("close");
    }
};

const handlePaste = (event) => {
    const pasted = String(event.clipboardData?.getData("text") || "")
        .replace(/\D/g, "")
        .slice(0, 6);

    if (!pasted) return;

    event.preventDefault();

    for (let i = 0; i < 6; i++) {
        codeInputs.value[i] = pasted[i] ?? "";
    }

    syncCode();

    nextTick(() => {
        inputRefs.value[Math.min(pasted.length - 1, 5)]?.focus();
    });
};

const submit = () => {
    syncCode();

    form.post(route("verification.code.verify"), {
        preserveScroll: true,
        headers: {
            "X-Skip-Global-Loader": "true",
        },
        onSuccess: () => {
            emit("close");
        },
    });
};

const resend = () => {
    form.post(route("verification.code.resend"), {
        preserveScroll: true,
        preserveState: true,
        headers: {
            "X-Skip-Global-Loader": "true",
        },
        onSuccess: async () => {
            codeInputs.value = ["", "", "", "", "", ""];
            form.code = "";
            await nextTick();
            inputRefs.value[0]?.focus();
        },
    });
};
</script>

<template>
    <Teleport to="body">
        <transition
            enter-active-class="duration-200 ease-out"
            enter-from-class="opacity-0 scale-[0.98]"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-[0.98]"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-[999] flex items-center justify-center px-4"
            >
                <div
                    class="absolute inset-0 bg-black/75 backdrop-blur-[2px]"
                    @click="emit('close')"
                ></div>

                <div
                    class="relative w-full max-w-[520px] rounded-[18px] border border-white/5 bg-[#101521]/95 px-5 py-5 sm:px-6 sm:py-6"
                >
                    <button
                        type="button"
                        class="absolute right-4 top-4 text-white/40 transition hover:text-white"
                        @click="emit('close')"
                    >
                        ✕
                    </button>

                    <div>
                        <h1 class="text-2xl font-medium text-white">
                            Verifikasi
                        </h1>
                        <p class="mt-2 text-sm sm:text-base text-white/50">
                            Masukkan kode verifikasi yang kamu terima
                        </p>
                        <p class="mt-1 text-sm text-white/35">
                            Periksa Kotak Masuk Email : {{ email }}
                        </p>
                    </div>

                    <div
                        v-if="status"
                        class="mt-4 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200"
                    >
                        {{ status }}
                    </div>

                    <div
                        v-if="form.errors.email"
                        class="mt-4 rounded-xl border border-red-400/20 bg-red-400/10 px-4 py-3 text-xs sm:text-sm text-red-200"
                    >
                        {{ form.errors.email }}
                    </div>

                    <div
                        v-if="form.errors.code"
                        class="mt-4 rounded-xl border border-red-400/20 bg-red-400/10 px-4 py-3 text-xs sm:text-sm text-red-200"
                    >
                        {{ form.errors.code }}
                    </div>

                    <form class="mt-6" @submit.prevent="submit">
                        <input v-model="form.email" type="hidden" />
                        <input v-model="form.context" type="hidden" />

                        <div
                            class="flex items-center justify-between gap-2 sm:gap-3"
                            @paste="handlePaste"
                        >
                            <input
                                v-for="(_, index) in codeInputs"
                                :key="index"
                                :ref="(el) => (inputRefs[index] = el)"
                                v-model="codeInputs[index]"
                                type="text"
                                inputmode="numeric"
                                maxlength="1"
                                autocomplete="one-time-code"
                                class="h-12 w-12 rounded-xl border border-white/5 bg-white/5 text-center text-lg font-semibold text-white outline-none transition focus:border-[#F96855] focus:bg-white/[0.07] focus:ring-2 focus:ring-[#F96855]/20 sm:h-14 sm:w-14 sm:text-xl"
                                @input="handleInput(index, $event)"
                                @keydown="handleKeydown(index, $event)"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing || fullCode.length !== 6"
                            class="mt-6 flex h-[50px] w-full items-center justify-center rounded-xl bg-[#F96855] text-base font-medium text-white transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Lanjut
                        </button>
                    </form>

                    <div
                        class="mt-2 sm:mt-4 flex items-center justify-center gap-4"
                    >
                        <button
                            type="button"
                            class="text-sm sm:text-base text-white/60 transition hover:text-white"
                            :disabled="form.processing"
                            @click="resend"
                        >
                            Kirim ulang kode
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
