<script setup>
import { ref, watch } from "vue";
import axios from "axios";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    shipmentOrderId: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits(["close", "skipped", "submitted", "play-again"]);

const step = ref("review");
const rating = ref(4);
const review = ref("");
const agreeTerms = ref(false);
const processing = ref(false);

watch(
    () => props.show,
    (value) => {
        if (value) {
            step.value = "review";
            rating.value = 4;
            review.value = "";
            agreeTerms.value = false;
            processing.value = false;
        }
    },
);

const selectRating = (value) => {
    rating.value = value;
};

const skip = () => {
    emit("skipped");
};

const submitFeedback = async () => {
    if (!agreeTerms.value || processing.value) return;

    try {
        processing.value = true;

        await axios.post(route("csat-feedback.store"), {
            shipment_order_id: props.shipmentOrderId,
            rating: rating.value,
            review: review.value,
            agree_terms: agreeTerms.value,
        });

        step.value = "thanks";
        emit("submitted");
    } catch (error) {
        alert(error.response?.data?.message || "Gagal menyimpan review.");
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
            <!-- REVIEW -->
            <div
                v-if="step === 'review'"
                class="rounded-xl bg-[#101521] px-5 pb-5 pt-6 text-white"
            >
                <h2 class="text-lg sm:text-2xl font-medium">
                    ❤️ Terima kasih!
                </h2>

                <div class="mt-3 h-px bg-white/10" />

                <p class="mt-5 text-sm text-white/70 sm:text-base">
                    Boleh bantu review kami?
                </p>

                <div class="mt-4 flex gap-3">
                    <button
                        v-for="number in 5"
                        :key="number"
                        type="button"
                        class="flex h-[30px] sm:h-[40px] w-[30px] sm:w-[40px] items-center justify-center rounded-md sm:rounded-lg bg-white/[0.07] transition hover:bg-white/[0.1]"
                        @click="selectRating(number)"
                    >
                        <i
                            class="bi bi-star-fill text-base sm:text-xl"
                            :class="
                                number <= rating
                                    ? 'text-[#FFB020]'
                                    : 'text-white/65'
                            "
                        />
                    </button>
                </div>

                <textarea
                    v-model="review"
                    rows="4"
                    placeholder="Tulis Review kamu..."
                    class="mt-5 w-full resize-none rounded-lg border-0 bg-white/[0.07] px-3 py-3 text-sm text-white placeholder:text-white/45 focus:ring-1 focus:ring-[#F66455] sm:text-base"
                />

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

                <div class="mt-5 h-px bg-white/10" />

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="h-[40px] sm:h-[50px] rounded-xl bg-white/[0.045] text-sm font-medium text-white transition hover:bg-white/[0.08] sm:text-base"
                        :disabled="processing"
                        @click="skip"
                    >
                        Lewati
                    </button>

                    <button
                        type="button"
                        class="h-[40px] sm:h-[50px] rounded-xl bg-[#F66455] text-sm font-medium text-white transition hover:bg-[#ff7668] disabled:cursor-not-allowed disabled:opacity-60 sm:text-base"
                        :disabled="processing || !agreeTerms"
                        @click="submitFeedback"
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
