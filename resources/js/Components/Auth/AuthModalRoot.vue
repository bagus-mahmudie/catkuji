<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import LoginModalForm from "@/Components/Auth/LoginModalForm.vue";
import RegisterModalForm from "@/Components/Auth/RegisterModalForm.vue";
import EmailVerificationModal from "@/Components/Auth/EmailVerificationModal.vue";
import {
    AUTH_MODAL_CLOSE_EVENT,
    AUTH_MODAL_OPEN_EVENT,
} from "@/Utils/authModal";

const page = usePage();

const isOpen = ref(false);
const mode = ref("login");
const redirectTo = ref("/");

const showVerificationModal = ref(false);
const verificationEmail = ref("");
const verificationContext = ref("login");
const verificationStatus = ref("");

const getCurrentUrl = () => {
    const current =
        page.url ||
        `${window.location.pathname}${window.location.search}${window.location.hash}`;

    if (!current || current === "/login" || current === "/register") {
        return route("home");
    }

    return current;
};

const open = (detail = {}) => {
    mode.value = detail?.mode === "register" ? "register" : "login";
    redirectTo.value = detail?.redirectTo || getCurrentUrl();
    isOpen.value = true;
};

const close = () => {
    isOpen.value = false;
};

const handleOpen = (event) => {
    open(event.detail || {});
};

const handleClose = () => {
    close();
};

const handleEscape = (event) => {
    if (event.key === "Escape" && isOpen.value) {
        close();
    }
};

watch(
    [isOpen, showVerificationModal],
    ([authOpen, verificationOpen]) => {
        document.body.style.overflow =
            authOpen || verificationOpen ? "hidden" : "";
    },
    { immediate: true },
);

watch(
    () => page.props.auth?.user,
    (user) => {
        if (user) {
            close();
            showVerificationModal.value = false;
        }
    },
);

watch(
    () => page.props.flash,
    (flash) => {
        const shouldShow = !!flash?.showEmailVerificationModal;
        verificationEmail.value = flash?.verificationEmail || "";
        verificationContext.value = flash?.verificationContext || "login";
        verificationStatus.value = flash?.status || "";

        if (shouldShow && verificationEmail.value) {
            isOpen.value = false;
            showVerificationModal.value = true;
        }
    },
    { immediate: true, deep: true },
);

onMounted(() => {
    window.addEventListener(AUTH_MODAL_OPEN_EVENT, handleOpen);
    window.addEventListener(AUTH_MODAL_CLOSE_EVENT, handleClose);
    window.addEventListener("keydown", handleEscape);
});

onBeforeUnmount(() => {
    window.removeEventListener(AUTH_MODAL_OPEN_EVENT, handleOpen);
    window.removeEventListener(AUTH_MODAL_CLOSE_EVENT, handleClose);
    window.removeEventListener("keydown", handleEscape);
    document.body.style.overflow = "";
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[300] overflow-y-auto bg-black/75 px-4 py-4 backdrop-blur-[6px] sm:py-8"
                @click="close"
            >
                <div class="flex min-h-full items-center justify-center">
                    <Transition
                        appear
                        enter-active-class="transform transition duration-200 ease-out"
                        enter-from-class="translate-y-3 scale-[0.98] opacity-0"
                        enter-to-class="translate-y-0 scale-100 opacity-100"
                        leave-active-class="transform transition duration-150 ease-in"
                        leave-from-class="translate-y-0 scale-100 opacity-100"
                        leave-to-class="translate-y-3 scale-[0.98] opacity-0"
                    >
                        <div
                            class="relative w-full max-w-[600px] overflow-hidden rounded-xl bg-[#101521]"
                            @click.stop
                        >
                            <button
                                type="button"
                                class="absolute right-4 top-4 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/5 text-white/70 transition hover:bg-white/10 hover:text-white"
                                aria-label="Tutup modal"
                                @click="close"
                            >
                                <i class="bi bi-x text-2xl leading-none" />
                            </button>

                            <div
                                class="max-h-[calc(100vh-32px)] overflow-y-auto no-scrollbar"
                            >
                                <LoginModalForm
                                    v-if="mode === 'login'"
                                    :redirect-to="redirectTo"
                                    @success="close"
                                    @close="close"
                                    @switch="mode = 'register'"
                                />

                                <RegisterModalForm
                                    v-else
                                    :redirect-to="redirectTo"
                                    @success="close"
                                    @close="close"
                                    @switch="mode = 'login'"
                                />
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>

        <EmailVerificationModal
            :show="showVerificationModal"
            :email="verificationEmail"
            :status="verificationStatus"
            :context="verificationContext"
            @close="showVerificationModal = false"
        />
    </Teleport>
</template>
