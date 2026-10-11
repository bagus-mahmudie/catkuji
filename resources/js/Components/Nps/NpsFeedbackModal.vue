<script setup>
import { computed, ref, watch } from "vue";
import axios from "axios";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    show: Boolean,
    eventId: {
        type: [Number, String],
        default: null,
    },
    pageUrl: {
        type: String,
        default: "",
    },
});

const copyPageLink = async () => {
    if (!props.pageUrl) return;

    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(props.pageUrl);
        } else {
            const input = document.createElement("input");
            input.value = props.pageUrl;
            document.body.appendChild(input);
            input.select();
            document.execCommand("copy");
            document.body.removeChild(input);
        }
    } catch (error) {
        console.error("Gagal copy link:", error);
    }
};
const emit = defineEmits(["close", "play-again", "submitted", "skipped"]);

const step = ref("score");
const score = ref(null);
const feedback = ref("");
const agreeTerms = ref(false);
const processing = ref(false);

watch(
    () => props.show,
    (value) => {
        if (value) {
            step.value = "score";
            score.value = null;
            feedback.value = "";
            agreeTerms.value = false;
            processing.value = false;
        }
    },
);

const feedbackType = computed(() => {
    if (score.value <= 6) return "detractor";
    if (score.value <= 8) return "passive";
    return "promoter";
});

const currentContent = computed(() => {
    if (feedbackType.value === "detractor") {
        return {
            emoji: "😔",
            title: "Kami ingin memperbaiki ini",
            question: "Apa yang membuat pengalamanmu kurang baik?",
            share: false,
        };
    }

    if (feedbackType.value === "passive") {
        return {
            emoji: "❤️",
            title: "Terima kasih!",
            question:
                "Kami ingin jadi lebih baik lagi, Apa yang bisa kami tingkatkan?",
            share: false,
        };
    }

    return {
        emoji: "😆",
        title: "Senang kamu suka!",
        question: "Apa yang paling kamu suka dari produk ini?",
        share: true,
    };
});

const selectScore = (value) => {
    score.value = value;
};

const goNext = async () => {
    if (step.value === "score") {
        if (!score.value) return;
        step.value = "feedback";
        return;
    }

    await submitFeedback();
};

const skip = () => {
    emit("skipped");
};

const submitFeedback = async (isSkipped = false) => {
    if (!score.value) return;

    try {
        processing.value = true;

        await axios.post(route("nps-feedback.store"), {
            gacha_event_id: props.eventId,
            score: score.value,
            feedback: isSkipped ? null : feedback.value,
            page_url: props.pageUrl,
            agree_terms: agreeTerms.value,
        });

        step.value = "thanks";
        emit("submitted");
    } catch (error) {
        alert(error.response?.data?.message || "Gagal menyimpan feedback.");
    } finally {
        processing.value = false;
    }
};

const closeModal = () => {
    emit("close");
};

const playAgain = () => {
    emit("play-again");
};
</script>

<template>
    <Modal :show="show" max-width="2xl" :closeable="false" @close="closeModal">
        <div class="relative">
            <!-- STEP 1 -->
            <div
                v-if="step === 'score'"
                class="rounded-xl bg-[#101521] px-5 pb-5 pt-6 text-white"
            >
                <h2 class="text-lg sm:text-2xl font-medium">
                    ✨ Kami ingin feedback kamu!
                </h2>

                <div class="mt-3 h-px bg-white/10" />

                <p class="mt-5 text-sm sm:text-base text-white">
                    Seberapa besar kemungkinan kamu merekomendasikan produk ini?
                </p>

                <div class="mt-5 grid grid-cols-10 gap-2 sm:gap-4">
                    <button
                        v-for="number in 10"
                        :key="number"
                        type="button"
                        class="flex h-[30px] sm:h-[50px] items-center justify-center rounded-md sm:rounded-xl text-sm sm:text-lg transition"
                        :class="
                            score === number
                                ? 'bg-[#F66455] text-white'
                                : 'bg-white/[0.07] text-white/60 hover:bg-white/[0.1] hover:text-white'
                        "
                        @click="selectScore(number)"
                    >
                        {{ number }}
                    </button>
                </div>

                <div class="mt-6 h-px bg-white/10" />

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="h-[40px] sm:h-[50px] rounded-xl bg-white/[0.045] text-sm sm:text-base font-medium text-white transition hover:bg-white/[0.08]"
                        @click="skip"
                    >
                        Lewati
                    </button>

                    <button
                        type="button"
                        class="h-[40px] sm:h-[50px] rounded-xl bg-[#F66455] text-sm sm:text-base font-medium text-white transition hover:bg-[#ff7668] disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!score"
                        @click="goNext"
                    >
                        Lanjutkan
                    </button>
                </div>
            </div>

            <!-- STEP 2 -->
            <div
                v-else-if="step === 'feedback'"
                class="rounded-xl bg-[#101521] px-5 pb-5 pt-6 text-white"
            >
                <h2 class="text-lg sm:text-2xl font-medium">
                    {{ currentContent.emoji }} {{ currentContent.title }}
                </h2>

                <div class="mt-3 h-px bg-white/10" />

                <p class="mt-5 text-sm sm:text-base text-white">
                    {{ currentContent.question }}
                </p>

                <textarea
                    v-model="feedback"
                    rows="3"
                    placeholder="Tulis masukan kamu..."
                    class="mt-4 w-full resize-none rounded-lg border-0 bg-white/[0.07] px-3 py-3 text-sm sm:text-base text-white placeholder:text-white/45 focus:ring-1 focus:ring-[#F66455]"
                />

                <template v-if="currentContent.share">
                    <p class="mt-5 text-sm sm:text-base text-white">
                        Mau ajak teman kamu pakai produk ini?
                    </p>

                    <div
                        class="mt-3 flex h-[40px] sm:h-[50px] items-center justify-between rounded-lg bg-white/[0.07] px-6"
                    >
                        <span class="min-w-0 truncate text-sm sm:text-base">
                            {{ pageUrl }}
                        </span>

                        <button
                            type="button"
                            class="ml-4 text-[#F66455]"
                            @click="copyPageLink"
                        >
                            <i class="bi bi-copy text-sm sm:text-base" />
                        </button>
                    </div>
                </template>

                <label
                    class="mt-5 flex items-center gap-2 text-xs sm:text-sm text-white/65"
                >
                    <input
                        v-model="agreeTerms"
                        type="checkbox"
                        class="h-4 w-4 rounded border-white/60 bg-transparent text-[#F66455] focus:ring-[#F66455]"
                    />
                    <span>I agree to the terms and conditions</span>
                </label>

                <div class="mt-3 h-px bg-white/10" />

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="h-[40px] sm:h-[50px] rounded-xl bg-white/[0.045] text-sm sm:text-base font-medium text-white transition hover:bg-white/[0.08]"
                        :disabled="processing"
                        @click="skip"
                    >
                        Lewati
                    </button>

                    <button
                        type="button"
                        class="h-[40px] sm:h-[50px] rounded-xl bg-[#F66455] text-sm sm:text-base font-medium text-white transition hover:bg-[#ff7668] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="processing || !agreeTerms"
                        @click="goNext"
                    >
                        {{ processing ? "Memproses..." : "Lanjutkan" }}
                    </button>
                </div>
            </div>

            <!-- THANKS -->
            <div v-else class="relative">
                <div
                    class="rounded-xl bg-[#101521] px-5 pb-5 pt-6 text-center text-white"
                >
                    <h2 class="text-lg sm:text-2xl font-medium">
                        💌 Appreciate It
                    </h2>

                    <p
                        class="mx-auto mt-4 max-w-[440px] text-sm sm:text-base leading-snug text-white/55"
                    >
                        Terima kasih sudah share feedback, kami akan pakai ini
                        untuk improve layanan kami
                    </p>

                    <button
                        type="button"
                        class="mt-10 h-[40px] sm:h-[50px] w-full rounded-xl bg-[#F66455] text-sm sm:text-base font-medium text-white transition hover:bg-[#ff7668]"
                        @click="playAgain"
                    >
                        Lanjut Main Yuk
                    </button>
                </div>

                <div class="flex justify-center pt-5">
                    <button
                        type="button"
                        class="flex h-[50px] w-[50px] items-center justify-center rounded-full bg-[#F66455] text-white transition hover:bg-[#ff7668]"
                        @click="closeModal"
                    >
                        <i class="bi bi-x text-2xl leading-none" />
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
