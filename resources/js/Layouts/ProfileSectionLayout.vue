<script setup>
import AppNavbar from "@/Components/AppNavbar.vue";
import AppFooter from "@/Components/AppFooter.vue";
import ProfileSectionHeader from "@/Components/Profile/ProfileSectionHeader.vue";
import ProfileSectionSidebar from "@/Components/Profile/ProfileSectionSidebar.vue";

const props = defineProps({
    active: {
        type: String,
        required: true,
    },
    profile: {
        type: Object,
        required: true,
    },
    tokenBalance: {
        type: Number,
        default: 0,
    },
    catKoinBalance: {
        type: Number,
        default: 0,
    },
});

const openTopupPage = () => {
    window.location.href = route("wallet.index");
};
</script>

<template>
    <div class="min-h-screen bg-[#0C0F19] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar @topup-click="openTopupPage" />

            <div class="mt-8 space-y-4 sm:space-y-6">
                <ProfileSectionHeader
                    :profile="profile"
                    :token-balance="tokenBalance"
                    :cat-koin-balance="catKoinBalance"
                />

                <div
                    class="grid items-start gap-4 sm:gap-6 lg:grid-cols-[320px_minmax(0,1fr)]"
                >
                    <div class="min-w-0 overflow-hidden">
                        <ProfileSectionSidebar :active="active" />
                    </div>

                    <section class="min-w-0 rounded-xl bg-[#101521] p-5 md:p-6">
                        <slot />
                    </section>
                </div>
            </div>
        </div>

        <AppFooter />
    </div>
</template>
