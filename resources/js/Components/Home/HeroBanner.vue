<script setup>
import { computed, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import { Carousel, Slide } from "vue3-carousel";
import "vue3-carousel/dist/carousel.css";
import { openAuthModal } from "@/Utils/authModal";

const props = defineProps({
    banners: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentSlide = ref(0);

const isAuthenticated = computed(() => {
    return !!page.props.auth?.user;
});

const openRegisterModal = () => {
    openAuthModal("register");
};

const slides = computed(() => {
    return (props.banners || [])
        .filter((banner) => !!banner.image)
        .map((banner, index) => ({
            id: banner.id ?? index + 1,
            image: banner.image,
            url: banner.url || "#",
            alt: banner.alt_text || banner.title || "Hero Banner",
        }));
});

const goToSlide = (index) => {
    currentSlide.value = index;
};
</script>

<template>
    <section v-if="slides.length" class="mt-5 sm:mt-8">
        <div class="relative w-full overflow-hidden rounded-xl">
            <Carousel
                v-model="currentSlide"
                :items-to-show="1"
                :wrap-around="true"
                :autoplay="4500"
                :pause-autoplay-on-hover="true"
                :transition="600"
                class="hero-carousel w-full"
            >
                <Slide
                    v-for="slide in slides"
                    :key="slide.id"
                    class="hero-slide"
                >
                    <div class="relative w-full">
                        <a :href="slide.url || '#'" class="block w-full">
                            <div
                                class="relative h-[180px] w-full overflow-hidden rounded-xl bg-slate-900 sm:h-[260px] lg:h-[400px]"
                            >
                                <img
                                    :src="slide.image"
                                    :alt="slide.alt || 'Hero Banner'"
                                    class="block h-full w-full max-w-none object-cover"
                                    draggable="false"
                                />
                            </div>
                        </a>

                        <div
                            v-if="!isAuthenticated"
                            class="absolute bottom-2 left-3 z-20 flex items-center gap-3 md:bottom-3 md:left-3 lg:bottom-8 lg:left-8"
                        >
                            <button
                                type="button"
                                @click="openRegisterModal"
                                class="inline-flex h-6 items-center justify-center rounded-lg bg-[#F56B5C] px-6 text-xs font-normal text-white transition hover:bg-[#ff7a6d] sm:h-12 sm:min-w-[156px] sm:rounded-xl sm:text-lg sm:font-medium"
                            >
                                Daftar
                            </button>

                            <button
                                type="button"
                                class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-[#1A2233]/85 text-white backdrop-blur-lg transition hover:bg-[#222c42] sm:h-12 sm:w-12 sm:rounded-xl"
                                aria-label="Login dengan Google"
                            >
                                <i class="bi bi-google text-sm sm:text-lg" />
                            </button>

                            <button
                                type="button"
                                class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-[#1A2233]/85 text-white backdrop-blur-lg transition hover:bg-[#222c42] sm:h-12 sm:w-12 sm:rounded-xl"
                                aria-label="Login dengan Facebook"
                            >
                                <i class="bi bi-facebook text-sm sm:text-lg" />
                            </button>
                        </div>
                    </div>
                </Slide>
            </Carousel>

            <div
                class="absolute bottom-8 right-8 z-20 flex items-center gap-3 md:bottom-10 md:right-10"
            >
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.id"
                    type="button"
                    @click="goToSlide(index)"
                    class="flex items-center justify-center"
                    :aria-label="`Pindah ke slide ${index + 1}`"
                >
                    <span
                        :class="[
                            'block rounded-full transition-all duration-200',
                            currentSlide === index
                                ? 'h-[6px] w-[30px] bg-[#F56B5C]'
                                : 'h-[8px] w-[8px] bg-white/90',
                        ]"
                    />
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped>
:deep(.hero-carousel) {
    width: 100%;
}

:deep(.hero-carousel .carousel__viewport) {
    width: 100%;
    overflow: hidden;
    border-radius: 12px;
}

:deep(.hero-carousel .carousel__track) {
    width: 100%;
    align-items: stretch;
}

:deep(.hero-carousel .carousel__slide) {
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
    flex-basis: 100% !important;
    padding: 0 !important;
}

:deep(.hero-carousel .carousel__slide > *) {
    width: 100%;
}
</style>
