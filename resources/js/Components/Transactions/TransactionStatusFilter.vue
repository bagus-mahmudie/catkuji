<script setup>
const props = defineProps({
    modelValue: {
        type: String,
        default: "all",
    },
});

const emit = defineEmits(["change"]);

const statusOptions = [
    { value: "all", label: "Semua Status" },
    { value: "success", label: "Sukses" },
    { value: "pending", label: "Belum dibayar" },
    { value: "failed", label: "Gagal" },
    { value: "expired", label: "Expired" },
    { value: "processing", label: "Diproses" },
    { value: "shipped", label: "Sedang dikirim" },
    { value: "delivered", label: "Tiba di tujuan" },
    { value: "paid", label: "Sudah dibayar" },
];
</script>

<template>
    <div class="relative w-full sm:w-[260px]">
        <select
            class="w-full appearance-none rounded-xl border border-white/5 bg-[#101521] py-3 pl-5 pr-10 text-base text-white outline-none transition"
            :value="modelValue"
            @change="emit('change', $event.target.value)"
        >
            <option
                v-for="option in statusOptions"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>

        <!-- <i
            class="bi bi-chevron-down pointer-events-none absolute right-5 top-1/2 -translate-y-1/2 text-sm text-white/60"
        /> -->
    </div>
</template>
