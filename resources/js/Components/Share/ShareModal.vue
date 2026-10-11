<script setup>
import Modal from "@/Components/Modal.vue";

import whatsappIcon from "@/assets/social/whatsapp.png";
import instagramIcon from "@/assets/social/instagram.png";
import facebookIcon from "@/assets/social/facebook.png";
import tiktokIcon from "@/assets/social/tiktok.png";
import telegramIcon from "@/assets/social/telegram.png";
import discordIcon from "@/assets/social/discord.png";
import { ref } from "vue";

const showCopied = ref(false);

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    url: {
        type: String,
        default: "",
    },
    title: {
        type: String,
        default: "Catkuji",
    },
});

const emit = defineEmits(["close"]);

const shareItems = [
    {
        label: "WhatsApp",
        icon: whatsappIcon,
        type: "whatsapp",
    },
    {
        label: "Instagram",
        icon: instagramIcon,
        type: "instagram",
    },
    {
        label: "Facebook",
        icon: facebookIcon,
        type: "facebook",
    },
    {
        label: "Tiktok",
        icon: tiktokIcon,
        type: "tiktok",
    },
    {
        label: "Telegram",
        icon: telegramIcon,
        type: "telegram",
    },
    {
        label: "Discord",
        icon: discordIcon,
        type: "discord",
    },
];

const encodedUrl = () => encodeURIComponent(props.url);
const encodedText = () => encodeURIComponent(`${props.title} ${props.url}`);

const openShare = (type) => {
    let shareUrl = "";

    if (type === "whatsapp") {
        shareUrl = `https://wa.me/?text=${encodedText()}`;
    }

    if (type === "facebook") {
        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl()}`;
    }

    if (type === "telegram") {
        shareUrl = `https://t.me/share/url?url=${encodedUrl()}&text=${encodeURIComponent(props.title)}`;
    }

    if (type === "instagram") {
        copyLink();
        return;
    }

    if (type === "tiktok") {
        copyLink();
        return;
    }

    if (type === "discord") {
        copyLink();
        return;
    }

    if (shareUrl) {
        window.open(shareUrl, "_blank", "noopener,noreferrer");
    }
};

const copyLink = async () => {
    if (!props.url) return;

    try {
        await navigator.clipboard.writeText(props.url);

        showCopied.value = true;

        setTimeout(() => {
            showCopied.value = false;
        }, 2000);
    } catch (error) {
        console.error("Gagal copy link:", error);
    }
};
</script>

<template>
    <Modal :show="show" max-width="md" :closeable="true" @close="emit('close')">
        <div class="relative">
            <div class="rounded-xl bg-[#101521] px-5 pb-5 pt-6 text-white">
                <h2 class="text-base sm:text-lg font-medium leading-none">
                    Share
                </h2>

                <div class="mt-5 grid grid-cols-3 gap-x-8 gap-y-8">
                    <button
                        v-for="item in shareItems"
                        :key="item.label"
                        type="button"
                        class="flex flex-col items-center text-center"
                        @click="openShare(item.type)"
                    >
                        <div
                            class="flex h-16 w-16 items-center justify-center rounded-full bg-white/[0.045] transition hover:bg-white/[0.07]"
                        >
                            <img
                                :src="item.icon"
                                :alt="item.label"
                                class="h-8 w-8 object-contain"
                            />
                        </div>

                        <span
                            class="mt-3 text-sm sm:text-base font-normal leading-none text-white/75"
                        >
                            {{ item.label }}
                        </span>
                    </button>
                </div>

                <div class="mt-8">
                    <div class="text-base sm:text-lg font-medium text-white">
                        Page Link
                    </div>

                    <div
                        class="mt-2 flex h-[50px] items-center justify-between rounded-lg bg-white/[0.045] px-6"
                    >
                        <div
                            class="min-w-0 truncate text-sm sm:text-base text-white/75"
                        >
                            {{ url }}
                        </div>

                        <button
                            type="button"
                            class="ml-4 shrink-0 text-[#F66455]"
                            @click="copyLink"
                        >
                            <i class="bi bi-copy text-base" />
                        </button>
                    </div>
                </div>
            </div>

            <Transition
                enter-active-class="transition duration-300"
                enter-from-class="opacity-0 translate-y-3"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showCopied"
                    class="absolute bottom-[110px] left-1/2 -translate-x-1/2 rounded-lg bg-[#1F2A44] px-5 py-3 text-sm text-white shadow-lg"
                >
                    ✅ Link berhasil disalin
                </div>
            </Transition>

            <div class="flex justify-center pt-6">
                <button
                    type="button"
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-[#F66455] text-white transition hover:bg-[#ff7668]"
                    @click="emit('close')"
                >
                    <i class="bi bi-x text-3xl leading-none" />
                </button>
            </div>
        </div>
    </Modal>
</template>
