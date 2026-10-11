<script setup>
import BaseTransactionCard from "./BaseTransactionCard.vue";

defineProps({
    item: {
        type: Object,
        required: true,
    },
    expanded: {
        type: Boolean,
        default: false,
    },
});

defineEmits(["toggle"]);
</script>

<template>
    <BaseTransactionCard
        :item="item"
        :expanded="expanded"
        @toggle="$emit('toggle')"
    >
        <template v-if="item.sections?.digital_claim">
            <div class="text-[18px] leading-8 text-white/70">
                {{ item.sections.digital_claim.description }}
            </div>

            <div class="mt-6 border-t border-white/10 pt-5">
                <div class="text-white/55">Kedaluwarsa</div>
                <div class="mt-2 text-[34px] font-semibold text-white">
                    {{ item.sections.digital_claim.expired_at }}
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button
                    type="button"
                    class="inline-flex min-w-[180px] items-center justify-center rounded-xl px-6 py-3 text-base font-medium"
                    :class="
                        item.sections.digital_claim.claimed
                            ? 'bg-white/[0.06] text-white/70'
                            : 'bg-[#F66455] text-white hover:bg-[#ff7d70]'
                    "
                    :disabled="item.sections.digital_claim.claimed"
                >
                    {{
                        item.sections.digital_claim.claimed
                            ? "Berhasil di Klaim"
                            : "Klaim"
                    }}
                </button>
            </div>
        </template>

        <template v-else>
            <div class="text-white/60">Belum ada klaim hadiah digital.</div>
        </template>
    </BaseTransactionCard>
</template>
