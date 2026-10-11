<script setup>
import Modal from "@/Components/Modal.vue";

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close", "confirm"]);
</script>

<template>
    <Modal
        :show="show"
        max-width="md"
        :closeable="!processing"
        @close="emit('close')"
    >
        <div
            class="rounded-xl bg-[#101521] px-5 pb-5 pt-8 text-center text-white"
        >
            <div
                class="mx-auto flex h-24 w-24 items-center justify-center rounded-xl bg-[#281f2d]"
            >
                <div
                    class="flex h-20 w-20 items-center justify-center rounded-xl bg-[#F66455]"
                >
                    <i class="bi bi-box-arrow-right text-4xl"></i>
                </div>
            </div>

            <h2 class="mt-6 text-xl sm:text-2xl font-medium">
                Keluar dari akun
            </h2>

            <p
                class="mx-auto mt-5 text-sm sm:text-base leading-snug text-white/55"
            >
                Kamu akan keluar dari akun ini dan perlu login kembali nanti
            </p>

            <div class="mt-9 space-y-3">
                <button
                    type="button"
                    class="w-full rounded-xl bg-[#F66455] px-4 py-3 text-sm sm:text-base font-medium text-white transition hover:bg-[#ff7668] disabled:opacity-60"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ processing ? "Memproses..." : "Ya, keluar" }}
                </button>

                <button
                    type="button"
                    class="w-full rounded-xl bg-white/[0.045] px-4 py-3 text-sm sm:text-base font-medium text-white transition hover:bg-white/[0.08] disabled:opacity-60"
                    :disabled="processing"
                    @click="emit('close')"
                >
                    Batal
                </button>
            </div>
        </div>
    </Modal>
</template>
