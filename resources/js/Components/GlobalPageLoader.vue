<script setup>
import logoCatkuji from "@/assets/logo-catkuji.png";

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <Transition name="global-loader-fade">
        <div
            v-if="show"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-[#070B14]/85 px-6 backdrop-blur-md"
        >
            <div class="flex flex-col items-center gap-5">
                <div
                    class="relative flex h-24 w-24 items-center justify-center"
                >
                    <div
                        class="absolute inset-0 rounded-full border-4 border-white/10"
                    />

                    <div
                        class="absolute inset-0 rounded-full border-4 border-transparent border-t-[#FB6758] animate-global-loader-spin"
                    />

                    <div
                        class="absolute h-14 w-14 rounded-full bg-[#FB6758]/20 blur-xl"
                    />

                    <div
                        class="relative flex h-20 w-20 items-center justify-center p-2"
                    >
                        <img
                            :src="logoCatkuji"
                            alt="Catkuji"
                            class="h-full w-full object-contain"
                        />
                    </div>
                </div>

                <div class="text-center">
                    <p
                        class="text-sm font-medium uppercase tracking-[0.15em] text-white/45"
                    >
                        Loading
                    </p>

                    <h2
                        class="mt-2 text-base sm:text-lg font-normal text-white/85"
                    >
                        Mohon tunggu sebentar
                    </h2>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.global-loader-fade-enter-active,
.global-loader-fade-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}

.global-loader-fade-enter-from,
.global-loader-fade-leave-to {
    opacity: 0;
    transform: scale(1.01);
}

.animate-global-loader-spin {
    animation: global-loader-spin 850ms linear infinite;
}

@keyframes global-loader-spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}
</style>
