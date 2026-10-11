<script setup>
import AppNavbar from "@/Components/AppNavbar.vue";
import ProfileSectionSidebar from "@/Components/Profile/ProfileSectionSidebar.vue";
</script>

<template>
    <div class="min-h-screen bg-[#0B0F1A] text-white">
        <div class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8">
            <AppNavbar @topup-click="openTopupPage" />

            <div class="mx-auto max-w-7xl px-4 py-8">
                <div class="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                    <!-- Sidebar -->
                    <ProfileSectionSidebar active="customer-service" />

                    <!-- Content -->
                    <div class="rounded-xl bg-[#101521] p-6">
                        <h1 class="text-xl font-semibold">Customer Service</h1>
                        <p class="mt-2 text-white/60">
                            Halaman customer service akan ditampilkan di sini.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
