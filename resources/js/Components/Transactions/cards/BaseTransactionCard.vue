<script setup>
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
    <div class="overflow-hidden rounded-xl bg-[#101521] shadow-xl">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left"
            @click="$emit('toggle')"
        >
            <div class="flex min-w-0 items-center gap-4">
                <div
                    class="flex h-[56px] w-[56px] shrink-0 items-center justify-center rounded-xl bg-[#F66455] text-white"
                >
                    <i :class="[item.icon, 'text-[22px]']" />
                </div>

                <div class="min-w-0">
                    <div
                        class="truncate text-[17px] sm:text-xl font-medium text-white"
                    >
                        {{ item.service_label }}
                    </div>
                    <div
                        class="mt-1 truncate text-sm sm:text-base text-white/70"
                    >
                        {{ item.summary }}
                    </div>
                </div>
            </div>

            <i
                class="bi bi-chevron-down shrink-0 text-lg text-white/80 transition duration-200"
                :class="{ 'rotate-180': expanded }"
            />
        </button>

        <div v-if="expanded" class="border-t border-white/10 px-4 py-4">
            <slot />
        </div>
    </div>
</template>
