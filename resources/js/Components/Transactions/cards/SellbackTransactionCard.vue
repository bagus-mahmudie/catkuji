<script setup>
import BaseTransactionCard from "./BaseTransactionCard.vue";
import TransactionPrizeItemCard from "./TransactionPrizeItemCard.vue";
import catKoinImage from "@/assets/cat-koin.png";

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
        <div class="space-y-4">
            <TransactionPrizeItemCard
                v-for="(product, index) in item.sections.sellback.items"
                :key="index"
                :product="product"
            />
        </div>

        <div class="mt-6 border-t border-white/10 pt-5">
            <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:text-base">
                <template
                    v-for="row in item.sections.sellback.rows"
                    :key="row.label"
                >
                    <div class="text-white/55">{{ row.label }}</div>

                    <div class="text-right font-medium text-white break-all">
                        <template v-if="row.is_coin">
                            <span
                                class="inline-flex items-center justify-end gap-2"
                            >
                                <img
                                    :src="catKoinImage"
                                    alt="Cat Koin"
                                    class="h-4 w-4 object-contain"
                                />
                                <span>{{ row.value }} Coin</span>
                            </span>
                        </template>

                        <template v-else>
                            {{ row.value }}
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </BaseTransactionCard>
</template>
