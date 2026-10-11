<script setup>
import { Link } from "@inertiajs/vue3";
import footerImage1 from "@/assets/footer-icon/gopay.png";
import footerImage2 from "@/assets/footer-icon/ovo.png";
import footerImage3 from "@/assets/footer-icon/dana.png";
import footerImage4 from "@/assets/footer-icon/shopee.png";
import footerImage5 from "@/assets/footer-icon/qris.png";
</script>

<template>
    <footer class="relative z-20 mt-12 overflow-hidden bg-[#101521]">
        <div class="mx-auto max-w-[1280px]">
            <div
                class="grid gap-10 px-6 py-10 lg:grid-cols-[1.2fr_1fr_1fr_1fr_1fr]"
            >
                <div>
                    <div
                        class="text-2xl sm:text-4xl font-bold tracking-wide text-white"
                    >
                        CATKUJI
                    </div>
                    <p
                        class="mt-5 max-w-[280px] text-sm sm:text-base leading-6 sm:leading-8 text-white/70"
                    >
                        Platform hiburan interaktif terpercaya! Nikmati sensasi
                        bermain, hadiah eksklusif, dan menangkap keuntungan
                        setiap hari.
                    </p>
                </div>

                <div>
                    <div
                        class="mb-6 sm:mb-8 border-l-2 border-[#F66455] pl-3 text-lg sm:text-2xl font-semibold text-white"
                    >
                        Quick Links
                    </div>
                    <div class="space-y-3 text-sm sm:text-base text-white/80">
                        <div><Link :href="route('home')">Homepage</Link></div>
                        <div>
                            <Link :href="route('transactions.index')"
                                >Transaksi</Link
                            >
                        </div>
                        <div>
                            <Link :href="route('inventory.index')"
                                >Inventory</Link
                            >
                        </div>
                        <div>
                            <Link :href="route('profile.address.edit')"
                                >Akun Saya</Link
                            >
                        </div>
                    </div>
                </div>

                <div>
                    <div
                        class="mb-6 sm:mb-8 border-l-2 border-[#F66455] pl-3 text-lg sm:text-2xl font-semibold text-white"
                    >
                        Tentang
                    </div>
                    <div class="space-y-3 text-sm sm:text-base text-white/80">
                        <div><a href="#">Cara Main</a></div>
                        <div><a href="#">Tentang Kami</a></div>
                        <div><a href="#">Blog Update</a></div>
                        <div><a href="#">Info Karir</a></div>
                    </div>
                </div>

                <div>
                    <div
                        class="mb-6 sm:mb-8 border-l-2 border-[#F66455] pl-3 text-lg sm:text-2xl font-semibold text-white"
                    >
                        Transparasi
                    </div>
                    <div class="space-y-3 text-sm sm:text-base text-white/80">
                        <div><a href="#">Pusat Bantuan (FAQ)</a></div>
                        <div><a href="#">Sistem Fairness</a></div>
                        <div><a href="#">Terms &amp; Conditions</a></div>
                        <div><a href="#">Privacy Policy</a></div>
                    </div>
                </div>

                <div>
                    <div
                        class="mb-6 sm:mb-8 border-l-2 border-[#F66455] pl-3 text-lg sm:text-2xl font-semibold text-white"
                    >
                        Community
                    </div>
                    <div class="space-y-3 text-sm sm:text-base text-white/80">
                        <div><a href="#">Instagram</a></div>
                        <div><a href="#">Facebook</a></div>
                        <div><a href="#">TikTok</a></div>
                        <div><a href="#">X / Twitter</a></div>
                    </div>
                </div>
            </div>

            <div class="border-t border-white/10 px-6 py-6">
                <div class="flex flex-wrap items-center gap-3">
                    <div
                        class="flex h-10 w-auto px-2 items-center justify-center rounded-md bg-white"
                    >
                        <img
                            :src="footerImage1"
                            alt="icon"
                            class="h-auto object-contain"
                        />
                    </div>

                    <div
                        class="flex h-10 w-auto px-2 items-center justify-center rounded-md bg-white"
                    >
                        <img
                            :src="footerImage2"
                            alt="icon"
                            class="h-auto object-contain"
                        />
                    </div>

                    <div
                        class="flex h-10 w-auto px-2 items-center justify-center rounded-md bg-white"
                    >
                        <img
                            :src="footerImage3"
                            alt="icon"
                            class="h-auto object-contain"
                        />
                    </div>

                    <div
                        class="flex h-10 w-auto px-2 items-center justify-center rounded-md bg-white"
                    >
                        <img
                            :src="footerImage4"
                            alt="icon"
                            class="h-auto object-contain"
                        />
                    </div>

                    <div
                        class="flex h-10 w-auto px-2 items-center justify-center rounded-md bg-white"
                    >
                        <img
                            :src="footerImage5"
                            alt="icon"
                            class="h-auto object-contain"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div
            class="bg-[#F66455] px-6 py-2 sm:py-4 text-center text-sm sm:text-base font-medium text-white"
        >
            © 2026 Catkuji. All rights reserved.
        </div>
    </footer>
</template>
