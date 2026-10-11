<script setup>
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppNavbar from "@/Components/AppNavbar.vue";
import catKoinImage from "@/assets/cat-koin.png";
import tokenImage from "@/assets/token-silver.png";
import { computed, ref, watch } from "vue";

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    product: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    rarityOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

watch(
    () => page.props.flash?.created_category_id,
    (categoryId) => {
        if (categoryId) {
            form.category_id = categoryId;
        }
    },
);

const isEdit = computed(() => props.mode === "edit");
const showNewCategory = ref(false);
const showPreviewModal = ref(false);
const previewQuantity = ref(1);

const form = useForm({
    name: props.product.name || "",
    description: props.product.description || "",
    category_id: props.product.category_id || "",
    new_category_name: "",

    thumbnail_image: null,
    banner_image: null,

    badge_label: props.product.badge_label || "Aktif",
    opened_text: props.product.opened_text || "Dibuka",
    stock_total: props.product.stock_total || null,
    price_per_draw: props.product.price_per_draw || 50,
    price_per_multi_draw: props.product.price_per_multi_draw || null,
    draw_mode: props.product.draw_mode || "both",
    event_type: props.product.event_type || "",
    start_at: props.product.start_at || "",
    end_at: props.product.end_at || "",
    status: props.product.status || "draft",
    sellback_enabled: Boolean(props.product.sellback_enabled ?? true),
    shipping_enabled: Boolean(props.product.shipping_enabled ?? true),
    max_draw_per_user_per_day: props.product.max_draw_per_user_per_day || null,
    sort_order: props.product.sort_order || 0,

    prizes: props.product.prizes?.length
        ? props.product.prizes.map((item) => ({
              ...item,
              image: null,
              preview_url: item.image_url || null,
              _delete: false,
          }))
        : [],
});

const categoryForm = useForm({
    name: "",
    slug: "",
    description: "",
    card_style: "standard",
    promo_left_bg: null,
    category_glow_color: "#F66455",
    category_glow_position: "right",
    promo_starts_at: "",
    promo_ends_at: "",
});

const thumbnailPreview = ref(props.product.thumbnail_url || null);
const bannerPreview = ref(props.product.banner_url || null);
const promoLeftBgPreview = ref(null);

watch(
    () => categoryForm.card_style,
    (style) => {
        if (style !== "promo") {
            categoryForm.promo_left_bg = null;
            promoLeftBgPreview.value = null;
            categoryForm.promo_starts_at = "";
            categoryForm.promo_ends_at = "";
        }
    },
);

const previewSelectedImage = computed(() => {
    return (
        thumbnailPreview.value ||
        bannerPreview.value ||
        previewPrizes.value?.[0]?.image ||
        null
    );
});

const previewPrizes = computed(() => {
    return form.prizes
        .filter((item) => !item._delete)
        .map((item, index) => {
            const stock = Number(item.stock || 0);

            return {
                id: item.id || `preview-${index}`,
                name: item.item_name || "Reward Item",
                description:
                    item.description || "Deskripsi hadiah akan tampil di sini.",
                rarity_code: item.rarity_code || "R",
                image: item.preview_url || null,
                stock_left: stock,
                stock_total: stock,
                sort_order: index,
                coin_price: Number(
                    item.coin_price ||
                        item.sellback_price ||
                        item.market_price ||
                        0,
                ),
                token_reward: Number(item.token_reward || 0),
                draw_weight: Number(item.draw_weight || 0),
            };
        });
});

const previewOrderedPrizes = computed(() => {
    const rarityOrder = ["UR", "SSR", "SR", "R", "C"];

    return [...previewPrizes.value].sort((a, b) => {
        const rarityA = rarityOrder.indexOf(
            String(a.rarity_code ?? "").toUpperCase(),
        );
        const rarityB = rarityOrder.indexOf(
            String(b.rarity_code ?? "").toUpperCase(),
        );

        const normalizedA = rarityA === -1 ? 999 : rarityA;
        const normalizedB = rarityB === -1 ? 999 : rarityB;

        if (normalizedA !== normalizedB) {
            return normalizedA - normalizedB;
        }

        return Number(a.sort_order || 0) - Number(b.sort_order || 0);
    });
});

const previewGroupedPrizes = computed(() => {
    const grouped = {
        "Last One": [],
        Gold: [],
        Silver: [],
        Bronze: [],
    };

    previewOrderedPrizes.value.forEach((item) => {
        const label = normalizeRarityLabel(item.rarity_code);
        grouped[label].push(item);
    });

    return Object.fromEntries(
        Object.entries(grouped).filter(([, items]) => items.length > 0),
    );
});

const previewStockPercentage = computed(() => {
    const left = Number(totalPrizeStock.value || 0);
    const total = Number(totalPrizeStock.value || 0);

    if (total <= 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, (left / total) * 100));
});

const previewUnitCoinPrice = computed(() => {
    return Number(form.price_per_draw || 0);
});

const previewTotalCoinPrice = computed(() => {
    return previewUnitCoinPrice.value * Number(previewQuantity.value || 1);
});

const formattedPreviewTotalCoinPrice = computed(() => {
    return Number(previewTotalCoinPrice.value || 0).toLocaleString("id-ID");
});

const previewCanIncrease = computed(() => previewQuantity.value < 10);
const previewCanDecrease = computed(() => previewQuantity.value > 1);

const increasePreviewQty = () => {
    if (previewQuantity.value < 10) {
        previewQuantity.value += 1;
    }
};

const decreasePreviewQty = () => {
    if (previewQuantity.value > 1) {
        previewQuantity.value -= 1;
    }
};

const previewStockText = (item) => {
    return `${item.stock_left ?? 0}/${item.stock_total ?? 0}`;
};

const resolvePreviewPrizeImage = (image) => {
    return image || null;
};

const getSectionTheme = (label) => {
    switch (label) {
        case "Last One":
            return {
                title: "text-[#FF7A66]",
                line: "bg-[#FF7A66]",
                border: "border-[#FF7A66]",
                badge: "bg-[#FF7A66] text-white",
            };
        case "Gold":
            return {
                title: "text-[#F4B000]",
                line: "bg-[#F4B000]",
                border: "border-[#F4B000]",
                badge: "bg-[#F4B000] text-white",
            };
        case "Silver":
            return {
                title: "text-[#B9C2D0]",
                line: "bg-[#B9C2D0]",
                border: "border-[#B9C2D0]",
                badge: "bg-[#B9C2D0] text-[#13203A]",
            };
        default:
            return {
                title: "text-[#A56A43]",
                line: "bg-[#A56A43]",
                border: "border-[#A56A43]",
                badge: "bg-[#A56A43] text-white",
            };
    }
};

function normalizeRarityLabel(rarity) {
    switch (
        String(rarity ?? "")
            .toUpperCase()
            .trim()
    ) {
        case "UR":
            return "Last One";
        case "SSR":
            return "Gold";
        case "SR":
            return "Silver";
        default:
            return "Bronze";
    }
}

const openPreview = () => {
    showPreviewModal.value = true;
};

const closePreview = () => {
    showPreviewModal.value = false;
};

const setImage = (event, field) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    form[field] = file;

    const url = URL.createObjectURL(file);

    if (field === "thumbnail_image") {
        thumbnailPreview.value = url;
    }

    if (field === "banner_image") {
        bannerPreview.value = url;
    }
};

const setCategoryPromoLeftBg = (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    categoryForm.promo_left_bg = file;
    promoLeftBgPreview.value = URL.createObjectURL(file);
};

const setPrizeImage = (event, index) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    form.prizes[index].image = file;
    form.prizes[index].preview_url = URL.createObjectURL(file);
};

const addPrize = () => {
    form.prizes.push({
        id: null,
        event_prize_id: null,
        gacha_prize_id: null,

        image: null,
        preview_url: null,

        item_name: "",
        description: "",
        rarity_code: "R",
        prize_type: "physical",

        stock: 1,
        draw_weight: 100,

        market_price: 0,
        sellback_price: 0,
        coin_price: 0,
        token_reward: 0,

        is_token_redeemable: false,
        token_redeem_price: 0,
        redeem_stock_total: 0,
        is_featured: false,

        _delete: false,
    });
};

const activePrizeCount = computed(() => {
    return form.prizes.filter((item) => !item._delete).length;
});

const removePrize = (index) => {
    if (activePrizeCount.value <= 1) {
        alert("Minimal harus ada satu prize aktif.");
        return;
    }

    const prize = form.prizes[index];

    if (!prize.event_prize_id && !prize.gacha_prize_id) {
        form.prizes.splice(index, 1);
        return;
    }

    prize._delete = true;
};

const createCategory = () => {
    categoryForm.post(route("admin.products.categories.store"), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            categoryForm.reset();
            promoLeftBgPreview.value = null;
            showNewCategory.value = false;
        },
    });
};

const activePrizes = computed(() => {
    return form.prizes.filter((item) => !item._delete);
});

const totalPrizeStock = computed(() =>
    form.prizes
        .filter((prize) => !prize._delete)
        .reduce((total, prize) => total + Number(prize.stock || 0), 0),
);

const totalWeight = computed(() => {
    return activePrizes.value.reduce(
        (total, item) => total + Number(item.draw_weight || 0),
        0,
    );
});

const chance = (weight) => {
    if (!totalWeight.value) {
        return "0%";
    }

    return `${((Number(weight || 0) / totalWeight.value) * 100).toFixed(2)}%`;
};

const formatCoin = (value) => {
    return new Intl.NumberFormat("id-ID").format(Number(value || 0));
};

const submit = (targetStatus) => {
    form.status = targetStatus;

    form.stock_total = totalPrizeStock.value;

    if (form.processing) {
        return;
    }

    if (isEdit.value) {
        form.post(route("admin.products.update.multipart", props.product.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.clearErrors();
            },
            onError: (errors) => {
                console.log("Product update validation errors:", errors);
            },
        });

        return;
    }

    form.post(route("admin.products.store"), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.clearErrors();
        },
        onError: (errors) => {
            console.log("Product store validation errors:", errors);
        },
    });
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Product' : 'Add Product'" />

    <AdminLayout
        :title="isEdit ? 'Edit Product' : 'Add Product'"
        :breadcrumb="[
            { label: 'Product', href: route('admin.products.index') },
            { label: isEdit ? 'Edit Product' : 'Add Product' },
        ]"
    >
        <div class="min-w-0 space-y-5">
            <section
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <h1
                    class="text-xl font-medium leading-tight sm:text-2xl lg:text-3xl"
                >
                    {{ isEdit ? "Edit Product" : "Add Product" }}
                </h1>

                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        :href="route('admin.products.index')"
                        class="grid h-10 w-10 place-items-center rounded-lg bg-white/5 text-white/75 hover:bg-white/10"
                    >
                        <i class="bi bi-arrow-left"></i>
                    </Link>

                    <!-- <button
                        type="button"
                        class="grid h-12 w-12 place-items-center rounded-lg bg-white/5 text-white/75 hover:bg-red-500/20 hover:text-red-300"
                    >
                        <i class="bi bi-trash"></i>
                    </button> -->

                    <button
                        type="button"
                        class="h-10 rounded-lg bg-white/5 px-8 text-sm font-medium text-white hover:bg-white/10"
                        @click="openPreview"
                    >
                        Preview
                    </button>

                    <button
                        type="button"
                        class="h-10 rounded-lg bg-[#ff675c] px-8 text-sm font-medium text-white hover:bg-[#ff5145] disabled:opacity-60"
                        :disabled="form.processing"
                        @click="submit('published')"
                    >
                        {{ form.processing ? "Saving..." : "Publish" }}
                    </button>
                </div>
            </section>

            <section
                v-if="Object.keys(form.errors).length"
                class="rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300"
            >
                <div class="font-semibold">Validasi gagal:</div>

                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="(message, field) in form.errors" :key="field">
                        {{ field }}: {{ message }}
                    </li>
                </ul>
            </section>

            <section class="grid gap-5 xl:grid-cols-[1fr_365px]">
                <div class="space-y-5">
                    <article class="overflow-hidden rounded-xl bg-[#1d1e1c]">
                        <div class="bg-[#1d1e1c] px-4 py-3">
                            <h2 class="text-base font-medium text-white">
                                Product Details
                            </h2>
                        </div>
                        <div class="rounded-xl bg-[#393836] p-4">
                            <div class="space-y-3 sm:space-y-5">
                                <div>
                                    <label class="text-sm text-white/80">
                                        Product Name
                                    </label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        class="mt-2 w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                        placeholder="Enter product name"
                                    />
                                    <p
                                        v-if="form.errors.name"
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-sm text-white/80">
                                        Total Stock
                                    </label>
                                    <input
                                        :value="totalPrizeStock"
                                        type="number"
                                        readonly
                                        class="mt-2 w-full cursor-not-allowed rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/70 placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                        placeholder="Total stock otomatis"
                                    />
                                    <p class="mt-2 text-xs text-white/35">
                                        Total Stock dihitung otomatis dari
                                        jumlah semua stock prize aktif. Ubah
                                        stock pada masing-masing prize untuk
                                        mengubah total ini.
                                    </p>
                                    <p
                                        v-if="form.errors.stock_total"
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{ form.errors.stock_total }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-sm text-white/80">
                                        Product Description
                                    </label>
                                    <textarea
                                        v-model="form.description"
                                        rows="7"
                                        class="mt-2 w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                        placeholder="Describe the product, rewards, and what users can expect"
                                    ></textarea>
                                    <p
                                        v-if="form.errors.description"
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{ form.errors.description }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-sm text-white/80">
                                        Price per Draw / Coin
                                    </label>

                                    <input
                                        v-model="form.price_per_draw"
                                        type="number"
                                        min="1"
                                        class="mt-2 w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                        placeholder="Enter coin price per draw"
                                    />

                                    <p
                                        v-if="form.errors.price_per_draw"
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{ form.errors.price_per_draw }}
                                    </p>

                                    <p class="mt-2 text-xs text-white/35">
                                        Total biaya draw akan dihitung dari
                                        harga ini dikali jumlah draw.
                                    </p>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="text-sm text-white/80">
                                            Start At
                                        </label>
                                        <input
                                            v-model="form.start_at"
                                            type="datetime-local"
                                            class="mt-2 w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                        />
                                        <p
                                            v-if="form.errors.start_at"
                                            class="mt-2 text-sm text-red-400"
                                        >
                                            {{ form.errors.start_at }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="text-sm text-white/80">
                                            End At
                                        </label>
                                        <input
                                            v-model="form.end_at"
                                            type="datetime-local"
                                            class="mt-2 w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="overflow-hidden rounded-xl bg-[#1d1e1c]">
                        <div
                            class="flex items-center justify-between bg-[#1d1e1c] px-4 py-3"
                        >
                            <div>
                                <h2 class="text-base font-medium">
                                    Set Up Prize Tiers
                                </h2>
                                <p class="mt-0 text-xs text-white/35">
                                    Total weight: {{ totalWeight }}. Chance
                                    dihitung otomatis dari draw weight.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="rounded-lg bg-white/5 px-2 sm:px-4 py-2 text-sm sm:text-base text-white/75 hover:bg-white/10"
                                @click="addPrize"
                            >
                                <i class="bi bi-plus-lg"></i>
                                <span class="hidden sm:inline">Add Prize</span>
                                <span class="sm:hidden">Add</span>
                            </button>
                        </div>

                        <div
                            class="grid gap-4 p-4 rounded-xl bg-[#393836] lg:grid-cols-3"
                        >
                            <div
                                v-for="(prize, index) in form.prizes"
                                v-show="!prize._delete"
                                :key="index"
                                class="rounded-lg border border-white/10 bg-white/5 p-3"
                            >
                                <div
                                    class="relative grid h-[320px] place-items-center overflow-hidden rounded-lg bg-white/5"
                                >
                                    <img
                                        v-if="prize.preview_url"
                                        :src="prize.preview_url"
                                        class="h-full w-full object-cover"
                                        alt="Prize"
                                        @error="
                                            $event.target.src =
                                                '/images/placeholder-prize.png'
                                        "
                                    />

                                    <div v-else class="text-center">
                                        <i
                                            class="bi bi-image text-4xl text-white/50"
                                        ></i>
                                        <p class="mt-4 text-sm text-white">
                                            Upload file
                                        </p>
                                        <p class="mt-2 text-xs text-white/30">
                                            Format JPEG, PNG maks. 50 MB
                                        </p>
                                    </div>
                                </div>

                                <label
                                    class="mt-3 flex cursor-pointer items-center justify-center rounded-lg bg-[#2d2e2b] px-4 py-3 text-sm text-white hover:bg-white/10"
                                >
                                    Pilih File
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="setPrizeImage($event, index)"
                                    />
                                </label>

                                <div class="mt-4 space-y-3 sm:space-y-4">
                                    <div>
                                        <label class="text-sm text-white/80">
                                            Tier Name
                                        </label>
                                        <select
                                            v-model="prize.rarity_code"
                                            class="mt-1 w-full rounded-lg bg-white/5 border-none px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                        >
                                            <option
                                                v-for="option in rarityOptions"
                                                :key="option.value"
                                                class="bg-[#2b2c29]"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="text-sm text-white/80">
                                            Item Name
                                        </label>
                                        <input
                                            v-model="prize.item_name"
                                            type="text"
                                            class="mt-1 w-full rounded-lg bg-white/5 border-none px-4 py-3 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                            placeholder="Enter reward item name"
                                        />
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label
                                                class="text-sm text-white/80"
                                            >
                                                Stock
                                            </label>
                                            <input
                                                v-model="prize.stock"
                                                type="number"
                                                min="1"
                                                class="mt-1 w-full rounded-lg border-none bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="text-sm text-white/80"
                                            >
                                                Weight
                                            </label>
                                            <input
                                                v-model="prize.draw_weight"
                                                type="number"
                                                min="1"
                                                class="mt-1 w-full rounded-lg border-none bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label
                                                class="text-sm text-white/80"
                                            >
                                                Sellback
                                            </label>
                                            <input
                                                v-model="prize.sellback_price"
                                                type="number"
                                                min="0"
                                                class="mt-1 w-full rounded-lg border-none bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="text-sm text-white/80"
                                            >
                                                Token
                                            </label>
                                            <input
                                                v-model="prize.token_reward"
                                                type="number"
                                                min="0"
                                                class="mt-1 w-full rounded-lg border-none bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-lg bg-black/20 p-3 text-sm text-white/50"
                                    >
                                        Chance :
                                        <span
                                            class="font-semibold text-[#ff675c]"
                                        >
                                            {{ chance(prize.draw_weight) }}
                                        </span>
                                    </div>

                                    <button
                                        type="button"
                                        class="w-full rounded-lg bg-red-500/10 px-4 py-2 text-sm text-red-300 hover:bg-red-500/20"
                                        @click="removePrize(index)"
                                    >
                                        Remove Prize
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <aside class="space-y-5">
                    <article class="overflow-hidden rounded-xl bg-[#1d1e1c]">
                        <div class="bg-[#1d1e1c] px-4 py-3">
                            <h2 class="text-base font-medium text-white">
                                Thumbnail
                            </h2>
                        </div>
                        <div class="rounded-xl bg-[#393836] p-3">
                            <div
                                class="grid h-[240px] place-items-center overflow-hidden rounded-lg bg-white/5"
                            >
                                <img
                                    v-if="thumbnailPreview"
                                    :src="thumbnailPreview"
                                    class="h-full w-full object-cover"
                                    alt="Thumbnail"
                                />

                                <div v-else class="text-center">
                                    <i
                                        class="bi bi-image text-4xl text-white/50"
                                    ></i>
                                    <p class="mt-4 text-sm text-white">
                                        Upload file atau seret ke sini
                                    </p>
                                    <p class="mt-2 text-xs text-white/30">
                                        Format JPEG, PNG maks. 50 MB
                                    </p>
                                </div>
                            </div>

                            <label
                                class="mt-4 flex cursor-pointer items-center justify-center rounded-lg bg-white/5 px-4 py-3 text-sm text-white hover:bg-white/10"
                            >
                                Pilih File
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="
                                        setImage($event, 'thumbnail_image')
                                    "
                                />
                            </label>

                            <p
                                v-if="form.errors.thumbnail_image"
                                class="mt-2 text-sm text-red-400"
                            >
                                {{ form.errors.thumbnail_image }}
                            </p>
                        </div>
                    </article>

                    <article class="overflow-hidden rounded-xl bg-[#1d1e1c]">
                        <div class="bg-[#1d1e1c] px-4 py-3">
                            <h2 class="text-base font-medium text-white">
                                Status
                            </h2>
                        </div>

                        <div class="rounded-xl bg-[#393836] p-3">
                            <select
                                v-model="form.status"
                                class="w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                            >
                                <option class="bg-[#2b2c29]" value="draft">
                                    Draft
                                </option>
                                <option class="bg-[#2b2c29]" value="published">
                                    Active
                                </option>
                                <option class="bg-[#2b2c29]" value="hidden">
                                    Hidden
                                </option>
                                <option class="bg-[#2b2c29]" value="ended">
                                    Ended
                                </option>
                            </select>

                            <p class="mt-1 text-xs text-white/35">
                                Set the product status
                            </p>
                        </div>
                    </article>

                    <article class="overflow-hidden rounded-xl bg-[#1d1e1c]">
                        <div class="bg-[#1d1e1c] px-4 py-3">
                            <h2 class="text-base font-medium text-white">
                                Product Details
                            </h2>
                        </div>

                        <div
                            class="space-y-3 sm:space-y-4 rounded-xl bg-[#393836] p-3"
                        >
                            <div>
                                <label class="text-sm text-white/80">
                                    Categories
                                </label>

                                <select
                                    v-model="form.category_id"
                                    class="mt-2 w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                >
                                    <option class="bg-[#2b2c29]" value="">
                                        Select an option
                                    </option>

                                    <option
                                        v-for="category in categories"
                                        :key="category.id"
                                        class="bg-[#2b2c29]"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>

                                <p class="mt-3 text-sm text-white/35">
                                    Add product to a category
                                </p>
                            </div>

                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-lg bg-white/5 px-4 py-3 text-sm font-medium text-[#ff675c] hover:bg-white/10"
                                @click="showNewCategory = !showNewCategory"
                            >
                                Create New Category
                                <span>+</span>
                            </button>

                            <div
                                v-if="showNewCategory"
                                class="space-y-3 rounded-lg border border-white/10 bg-black/10 p-3"
                            >
                                <input
                                    v-model="categoryForm.name"
                                    type="text"
                                    class="w-full rounded-lg bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                    placeholder="Nama kategori baru"
                                />

                                <textarea
                                    v-model="categoryForm.description"
                                    rows="3"
                                    class="w-full rounded-lg bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                    placeholder="Deskripsi kategori"
                                ></textarea>

                                <div>
                                    <label
                                        class="mb-2 block text-sm text-white/70"
                                    >
                                        Tampilan Card Homepage
                                    </label>

                                    <select
                                        v-model="categoryForm.card_style"
                                        class="w-full rounded-lg bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                    >
                                        <option
                                            class="bg-[#2b2c29]"
                                            value="standard"
                                        >
                                            Standard Event Card
                                        </option>
                                        <option
                                            class="bg-[#2b2c29]"
                                            value="promo"
                                        >
                                            Promo Event Card
                                        </option>
                                    </select>
                                </div>

                                <div
                                    v-if="categoryForm.card_style === 'promo'"
                                    class="rounded-lg border border-white/10 bg-white/[0.04] p-3"
                                >
                                    <div
                                        v-if="
                                            categoryForm.card_style === 'promo'
                                        "
                                        class="grid gap-4 sm:grid-cols-2"
                                    >
                                        <div>
                                            <label
                                                class="mb-2 block text-sm text-white/70"
                                            >
                                                Promo Start Date
                                            </label>

                                            <input
                                                v-model="
                                                    categoryForm.promo_starts_at
                                                "
                                                type="datetime-local"
                                                class="w-full rounded-lg bg-white/5 px-4 py-3 text-xs text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                            />

                                            <p
                                                v-if="
                                                    categoryForm.errors
                                                        .promo_starts_at
                                                "
                                                class="mt-2 text-sm text-red-400"
                                            >
                                                {{
                                                    categoryForm.errors
                                                        .promo_starts_at
                                                }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                class="mb-2 block text-sm text-white/70"
                                            >
                                                Promo End Date
                                            </label>

                                            <input
                                                v-model="
                                                    categoryForm.promo_ends_at
                                                "
                                                type="datetime-local"
                                                class="w-full rounded-lg bg-white/5 px-4 py-3 text-xs text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                            />

                                            <p
                                                v-if="
                                                    categoryForm.errors
                                                        .promo_ends_at
                                                "
                                                class="mt-2 text-sm text-red-400"
                                            >
                                                {{
                                                    categoryForm.errors
                                                        .promo_ends_at
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <label
                                        class="my-2 block text-sm text-white/70"
                                    >
                                        Promo Left Background
                                    </label>

                                    <div
                                        class="grid h-[120px] place-items-center overflow-hidden rounded-lg bg-black/20"
                                    >
                                        <img
                                            v-if="promoLeftBgPreview"
                                            :src="promoLeftBgPreview"
                                            alt="Promo Left Background Preview"
                                            class="h-full w-full object-contain"
                                        />

                                        <div v-else class="px-4 text-center">
                                            <i
                                                class="bi bi-image text-3xl text-white/40"
                                            ></i>
                                            <p
                                                class="mt-2 text-xs text-white/50"
                                            >
                                                Upload gambar dekorasi kiri
                                                promo
                                            </p>
                                            <p
                                                class="mt-1 text-[11px] text-white/30"
                                            >
                                                PNG, JPG, WEBP maks. 5MB
                                            </p>
                                        </div>
                                    </div>

                                    <label
                                        class="mt-3 flex cursor-pointer items-center justify-center rounded-lg bg-white/5 px-4 py-3 text-sm font-medium text-white hover:bg-white/10"
                                    >
                                        Pilih Gambar Promo
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="hidden"
                                            @change="setCategoryPromoLeftBg"
                                        />
                                    </label>

                                    <p
                                        v-if="categoryForm.errors.promo_left_bg"
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{ categoryForm.errors.promo_left_bg }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-lg border border-white/10 bg-white/[0.04] p-3"
                                >
                                    <label
                                        class="mb-2 block text-sm text-white/70"
                                    >
                                        Warna Glow Category
                                    </label>

                                    <div class="flex items-center gap-3">
                                        <input
                                            v-model="
                                                categoryForm.category_glow_color
                                            "
                                            type="color"
                                            class="h-12 w-14 cursor-pointer rounded-lg border border-white/10 bg-transparent p-1"
                                        />

                                        <input
                                            v-model="
                                                categoryForm.category_glow_color
                                            "
                                            type="text"
                                            maxlength="7"
                                            placeholder="#F66455"
                                            class="h-12 flex-1 rounded-lg border border-white/10 bg-white/5 px-4 text-sm uppercase text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                        />
                                    </div>

                                    <div
                                        class="mt-3 h-3 rounded-lg border border-white/10"
                                        :style="{
                                            background: `linear-gradient(90deg, ${categoryForm.category_glow_color}, transparent)`,
                                        }"
                                    ></div>

                                    <p
                                        v-if="
                                            categoryForm.errors
                                                .category_glow_color
                                        "
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{
                                            categoryForm.errors
                                                .category_glow_color
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-lg border border-white/10 bg-white/[0.04] p-3"
                                >
                                    <label
                                        class="mb-2 block text-sm text-white/70"
                                    >
                                        Posisi Glow Bawah Section
                                    </label>

                                    <div class="grid grid-cols-2 gap-2">
                                        <label
                                            class="flex cursor-pointer items-center justify-center rounded-lg border px-4 py-3 text-sm font-medium transition"
                                            :class="
                                                categoryForm.category_glow_position ===
                                                'left'
                                                    ? 'border-[#ff675c] bg-[#ff675c]/15 text-white'
                                                    : 'border-white/10 bg-white/5 text-white/60 hover:bg-white/10'
                                            "
                                        >
                                            <input
                                                v-model="
                                                    categoryForm.category_glow_position
                                                "
                                                type="radio"
                                                value="left"
                                                class="hidden"
                                            />
                                            Kiri
                                        </label>

                                        <label
                                            class="flex cursor-pointer items-center justify-center rounded-lg border px-4 py-3 text-sm font-medium transition"
                                            :class="
                                                categoryForm.category_glow_position ===
                                                'right'
                                                    ? 'border-[#ff675c] bg-[#ff675c]/15 text-white'
                                                    : 'border-white/10 bg-white/5 text-white/60 hover:bg-white/10'
                                            "
                                        >
                                            <input
                                                v-model="
                                                    categoryForm.category_glow_position
                                                "
                                                type="radio"
                                                value="right"
                                                class="hidden"
                                            />
                                            Kanan
                                        </label>
                                    </div>

                                    <p
                                        v-if="
                                            categoryForm.errors
                                                .category_glow_position
                                        "
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{
                                            categoryForm.errors
                                                .category_glow_position
                                        }}
                                    </p>
                                </div>

                                <p
                                    v-if="categoryForm.errors.name"
                                    class="text-sm text-red-400"
                                >
                                    {{ categoryForm.errors.name }}
                                </p>

                                <p
                                    v-if="categoryForm.errors.card_style"
                                    class="text-sm text-red-400"
                                >
                                    {{ categoryForm.errors.card_style }}
                                </p>

                                <button
                                    type="button"
                                    class="w-full rounded-lg bg-[#ff675c] px-4 py-3 text-sm font-semibold text-white hover:bg-[#ff5145]"
                                    @click="createCategory"
                                >
                                    Simpan Kategori
                                </button>
                            </div>

                            <div>
                                <label class="text-sm text-white/80">
                                    Draw Mode
                                </label>

                                <select
                                    v-model="form.draw_mode"
                                    class="mt-2 w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white focus:border-[#ff675c] focus:ring-[#ff675c]"
                                >
                                    <option class="bg-[#2b2c29]" value="single">
                                        Single
                                    </option>
                                    <option class="bg-[#2b2c29]" value="multi">
                                        Multi
                                    </option>
                                    <option class="bg-[#2b2c29]" value="both">
                                        Both
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="text-sm text-white/80">
                                    Event Type Filter
                                </label>

                                <input
                                    v-model="form.event_type"
                                    type="text"
                                    class="mt-2 w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder:text-white/35 focus:border-[#ff675c] focus:ring-[#ff675c]"
                                    placeholder="Contoh: standard, smartphone, console, laptop-pc"
                                />

                                <p class="mt-2 text-xs text-white/35">
                                    Kosongkan jika product tidak perlu masuk
                                    filter khusus di halaman Selengkapnya.
                                </p>

                                <p
                                    v-if="form.errors.event_type"
                                    class="mt-2 text-sm text-red-400"
                                >
                                    {{ form.errors.event_type }}
                                </p>
                            </div>

                            <label
                                class="flex items-center justify-between rounded-lg bg-white/5 px-4 py-3 text-sm"
                            >
                                Sellback Enabled
                                <input
                                    v-model="form.sellback_enabled"
                                    type="checkbox"
                                    class="rounded-lg border-white/20 bg-transparent text-[#ff675c] focus:ring-[#ff675c]"
                                />
                            </label>

                            <label
                                class="flex items-center justify-between rounded-lg bg-white/5 px-4 py-3 text-sm"
                            >
                                Shipping Enabled
                                <input
                                    v-model="form.shipping_enabled"
                                    type="checkbox"
                                    class="rounded-lg border-white/20 bg-transparent text-[#ff675c] focus:ring-[#ff675c]"
                                />
                            </label>
                        </div>
                    </article>
                </aside>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="showPreviewModal"
                class="fixed inset-0 z-[9999] bg-black/75 px-4 py-6 backdrop-blur-sm"
            >
                <button
                    type="button"
                    class="fixed right-4 top-4 z-[10001] grid h-12 w-12 place-items-center rounded-full bg-[#FB6758] text-white shadow-lg shadow-black/40 transition hover:scale-[1.03] hover:brightness-105"
                    @click="closePreview"
                >
                    <i class="bi bi-x-lg text-lg"></i>
                </button>

                <div
                    class="preview-scroll flex h-full items-start justify-center overflow-y-auto"
                >
                    <div
                        class="relative w-full max-w-[1280px] overflow-hidden rounded-xl bg-[#060B16] shadow-2xl shadow-black/70"
                    >
                        <div class="min-h-screen bg-[#060B16] text-white">
                            <div
                                class="mx-auto max-w-[1280px] px-4 pb-10 pt-6 sm:px-6 lg:px-8"
                            >
                                <AppNavbar />

                                <main class="mt-8">
                                    <section
                                        class="grid gap-5 lg:grid-cols-[1fr_1fr]"
                                    >
                                        <div
                                            class="relative min-h-[420px] overflow-hidden rounded-xl bg-[#0D1322] lg:min-h-[640px]"
                                        >
                                            <template
                                                v-if="previewSelectedImage"
                                            >
                                                <img
                                                    :src="previewSelectedImage"
                                                    :alt="
                                                        form.name ||
                                                        'Preview image'
                                                    "
                                                    class="pointer-events-none absolute inset-0 z-0 h-full w-full scale-125 object-cover opacity-60 blur-sm"
                                                />
                                            </template>

                                            <div
                                                class="absolute inset-0 z-0 bg-[#0D1322]/20"
                                            />

                                            <button
                                                type="button"
                                                class="absolute right-4 top-4 z-20 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white/90 backdrop-blur transition hover:bg-white/15"
                                            >
                                                <i
                                                    class="bi bi-heart text-lg"
                                                />
                                            </button>

                                            <div
                                                class="relative z-10 flex h-full items-center justify-center p-5 sm:p-6"
                                            >
                                                <div
                                                    class="flex w-full items-center justify-center"
                                                >
                                                    <template
                                                        v-if="
                                                            previewSelectedImage
                                                        "
                                                    >
                                                        <img
                                                            :src="
                                                                previewSelectedImage
                                                            "
                                                            :alt="
                                                                form.name ||
                                                                'Preview image'
                                                            "
                                                            class="max-h-[420px] max-w-[300px] rounded-xl object-contain sm:min-w-[450px]"
                                                        />
                                                    </template>

                                                    <template v-else>
                                                        <div
                                                            class="flex h-[360px] w-full items-center justify-center rounded-xl bg-[linear-gradient(135deg,#1c2438,#0d1322)] text-center text-base text-white/40 sm:h-[500px] lg:h-[620px]"
                                                        >
                                                            Gambar belum
                                                            tersedia
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            class="rounded-xl bg-[#101521] p-5 sm:p-6 lg:p-7"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <div class="min-w-0">
                                                    <div
                                                        class="flex items-center gap-2 text-sm text-[#FF5A4D]"
                                                    >
                                                        <i
                                                            class="bi bi-unlock"
                                                        />
                                                        <span>
                                                            {{
                                                                form.opened_text ||
                                                                "Dibuka 21 Hari Ini"
                                                            }}
                                                        </span>
                                                    </div>

                                                    <h1
                                                        class="mt-1 max-w-[560px] text-[30px] font-medium leading-[1.12] text-white sm:mt-3 sm:text-[40px] lg:text-3xl"
                                                    >
                                                        {{
                                                            form.name ||
                                                            "Product Name"
                                                        }}
                                                    </h1>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white/80 transition hover:bg-white/5 hover:text-white"
                                                >
                                                    <i
                                                        class="bi bi-share text-lg"
                                                    />
                                                </button>
                                            </div>

                                            <div>
                                                <p
                                                    class="mt-3 max-w-[620px] whitespace-pre-line break-words text-sm leading-6 text-white/75 transition-all sm:text-base"
                                                >
                                                    {{
                                                        form.description ||
                                                        "Deskripsi product akan tampil di sini seperti halaman event user setelah product dipublish."
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="mt-4 flex items-end justify-between text-white sm:mt-8"
                                            >
                                                <div
                                                    class="text-base font-medium leading-none sm:text-xl"
                                                >
                                                    Sisa : {{ totalPrizeStock }}
                                                </div>
                                                <div
                                                    class="text-base font-medium leading-none text-white/95 sm:text-xl"
                                                >
                                                    {{ totalPrizeStock }}
                                                </div>
                                            </div>

                                            <div
                                                class="mt-3 h-3 w-full overflow-hidden rounded-full bg-white/10"
                                            >
                                                <div
                                                    class="h-full rounded-full bg-[repeating-linear-gradient(135deg,#42E8E0_0px,#42E8E0_10px,#58EFE6_10px,#58EFE6_20px)] transition-all duration-300"
                                                    :style="{
                                                        width: `${previewStockPercentage}%`,
                                                    }"
                                                />
                                            </div>

                                            <div class="mt-6 sm:mt-8">
                                                <div
                                                    class="text-base font-medium text-white sm:text-xl"
                                                >
                                                    Kuantitas
                                                </div>

                                                <div class="mt-3">
                                                    <div
                                                        class="inline-flex h-10 w-full max-w-[120px] items-center justify-between rounded-xl bg-white/5 px-4"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="inline-flex h-5 w-5 items-center justify-center rounded-xl text-white/80 transition hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                                                            :disabled="
                                                                !previewCanDecrease
                                                            "
                                                            @click="
                                                                decreasePreviewQty
                                                            "
                                                        >
                                                            <i
                                                                class="bi bi-dash-lg text-lg font-semibold"
                                                            />
                                                        </button>

                                                        <div
                                                            class="text-xl font-medium leading-none text-white"
                                                        >
                                                            {{
                                                                previewQuantity
                                                            }}
                                                        </div>

                                                        <button
                                                            type="button"
                                                            class="inline-flex h-5 w-5 items-center justify-center rounded-xl text-white/80 transition hover:bg-white/5 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                                                            :disabled="
                                                                !previewCanIncrease
                                                            "
                                                            @click="
                                                                increasePreviewQty
                                                            "
                                                        >
                                                            <i
                                                                class="bi bi-plus-lg text-lg font-semibold"
                                                            />
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                class="mt-6 border-t border-white/12 pt-8"
                                            >
                                                <button
                                                    type="button"
                                                    class="flex h-[58px] w-full items-center justify-center gap-2 rounded-xl bg-[#F36A5B] px-6 text-lg font-semibold text-white transition hover:brightness-105"
                                                >
                                                    <span>Beli</span>
                                                    <img
                                                        :src="catKoinImage"
                                                        alt="Cat Koin"
                                                        class="h-5 w-5 object-contain"
                                                    />
                                                    <span>
                                                        {{
                                                            formattedPreviewTotalCoinPrice
                                                        }}
                                                    </span>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="mt-4 flex h-[56px] w-full items-center justify-center gap-3 rounded-xl bg-[#0E1830] px-6 text-base font-semibold text-white transition hover:bg-[#13203C]"
                                                >
                                                    <span
                                                        >Cara Main Catkuji</span
                                                    >
                                                    <i
                                                        class="bi bi-arrow-right text-base"
                                                    />
                                                </button>
                                            </div>
                                        </div>
                                    </section>

                                    <section class="mt-10 space-y-10">
                                        <template
                                            v-for="(
                                                items, label
                                            ) in previewGroupedPrizes"
                                            :key="label"
                                        >
                                            <div class="space-y-4">
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <div
                                                        class="h-px flex-1 opacity-70"
                                                        :class="
                                                            getSectionTheme(
                                                                label,
                                                            ).line
                                                        "
                                                    />
                                                </div>

                                                <div
                                                    :class="
                                                        label === 'Last One'
                                                            ? 'grid grid-cols-1'
                                                            : 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4'
                                                    "
                                                >
                                                    <article
                                                        v-for="item in items"
                                                        :key="item.id"
                                                        :class="[
                                                            'cursor-pointer overflow-hidden rounded-xl bg-[#101521] transition hover:bg-[#131a29] border-b-4',
                                                            getSectionTheme(
                                                                label,
                                                            ).border,
                                                        ]"
                                                    >
                                                        <template
                                                            v-if="
                                                                label ===
                                                                'Last One'
                                                            "
                                                        >
                                                            <div
                                                                class="grid items-start gap-5 p-4 sm:grid-cols-[260px_1fr] sm:p-5"
                                                            >
                                                                <div
                                                                    class="overflow-hidden rounded-xl bg-[#0B1324]"
                                                                >
                                                                    <template
                                                                        v-if="
                                                                            resolvePreviewPrizeImage(
                                                                                item.image,
                                                                            )
                                                                        "
                                                                    >
                                                                        <img
                                                                            :src="
                                                                                resolvePreviewPrizeImage(
                                                                                    item.image,
                                                                                )
                                                                            "
                                                                            :alt="
                                                                                item.name
                                                                            "
                                                                            class="h-[210px] w-full object-cover sm:h-[230px]"
                                                                        />
                                                                    </template>

                                                                    <template
                                                                        v-else
                                                                    >
                                                                        <div
                                                                            class="flex h-[260px] w-full items-center justify-center bg-[linear-gradient(135deg,#1b2438,#10182b)] px-5 text-center text-sm text-white/35 sm:h-[230px]"
                                                                        >
                                                                            Gambar
                                                                            hadiah
                                                                            belum
                                                                            tersedia
                                                                        </div>
                                                                    </template>
                                                                </div>

                                                                <div
                                                                    class="flex flex-col justify-between"
                                                                >
                                                                    <div>
                                                                        <div
                                                                            class="text-base font-medium"
                                                                            :class="
                                                                                getSectionTheme(
                                                                                    label,
                                                                                )
                                                                                    .title
                                                                            "
                                                                        >
                                                                            {{
                                                                                label
                                                                            }}
                                                                        </div>

                                                                        <h3
                                                                            class="mt-3 text-lg font-medium text-white sm:text-xl"
                                                                        >
                                                                            {{
                                                                                item.name
                                                                            }}
                                                                        </h3>

                                                                        <p
                                                                            class="mt-2 line-clamp-3 text-sm leading-6 text-white/65 lg:text-base"
                                                                        >
                                                                            {{
                                                                                item.description ||
                                                                                "Hadiah spesial kategori Last One."
                                                                            }}
                                                                        </p>
                                                                    </div>

                                                                    <div
                                                                        class="mt-5 flex items-center justify-between pt-4 sm:max-w-[80%]"
                                                                    >
                                                                        <div
                                                                            class="flex flex-col"
                                                                        >
                                                                            <div
                                                                                class="text-sm text-white/50"
                                                                            >
                                                                                Harga
                                                                                Jual
                                                                            </div>
                                                                            <div
                                                                                class="mt-1 inline-flex items-center gap-2 text-base font-medium text-white"
                                                                            >
                                                                                <img
                                                                                    :src="
                                                                                        catKoinImage
                                                                                    "
                                                                                    alt="Cat Koin"
                                                                                    class="h-5 w-5 object-contain"
                                                                                />
                                                                                <span>
                                                                                    {{
                                                                                        Number(
                                                                                            item.coin_price ??
                                                                                                0,
                                                                                        ).toLocaleString(
                                                                                            "id-ID",
                                                                                        )
                                                                                    }}
                                                                                </span>
                                                                            </div>
                                                                        </div>

                                                                        <div
                                                                            class="h-6 w-px bg-white/20"
                                                                        ></div>

                                                                        <div
                                                                            class="flex flex-col items-center"
                                                                        >
                                                                            <div
                                                                                class="text-sm text-white/50"
                                                                            >
                                                                                Stok
                                                                            </div>
                                                                            <div
                                                                                class="mt-1 text-base font-medium text-white"
                                                                            >
                                                                                {{
                                                                                    previewStockText(
                                                                                        item,
                                                                                    )
                                                                                }}
                                                                            </div>
                                                                        </div>

                                                                        <div
                                                                            class="h-6 w-px bg-white/20"
                                                                        ></div>

                                                                        <div
                                                                            class="flex flex-col items-start"
                                                                        >
                                                                            <div
                                                                                class="text-sm text-white/50"
                                                                            >
                                                                                Hadiah
                                                                                tiket
                                                                            </div>
                                                                            <div
                                                                                class="mt-1 inline-flex items-center gap-2 text-base font-medium text-white"
                                                                            >
                                                                                <img
                                                                                    :src="
                                                                                        tokenImage
                                                                                    "
                                                                                    alt="Token"
                                                                                    class="h-5 w-5 object-contain"
                                                                                />
                                                                                <span>
                                                                                    {{
                                                                                        Number(
                                                                                            item.token_reward ??
                                                                                                0,
                                                                                        ).toLocaleString(
                                                                                            "id-ID",
                                                                                        )
                                                                                    }}
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>

                                                        <template v-else>
                                                            <div class="p-4">
                                                                <div
                                                                    class="flex items-center justify-between"
                                                                >
                                                                    <div
                                                                        class="text-base font-medium"
                                                                        :class="
                                                                            getSectionTheme(
                                                                                label,
                                                                            )
                                                                                .title
                                                                        "
                                                                    >
                                                                        {{
                                                                            label
                                                                        }}
                                                                    </div>

                                                                    <div
                                                                        class="text-sm text-white/60"
                                                                    >
                                                                        Stok :
                                                                        {{
                                                                            previewStockText(
                                                                                item,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </div>

                                                                <div
                                                                    class="mt-4 overflow-hidden rounded-[14px] bg-[#101521]"
                                                                >
                                                                    <template
                                                                        v-if="
                                                                            resolvePreviewPrizeImage(
                                                                                item.image,
                                                                            )
                                                                        "
                                                                    >
                                                                        <img
                                                                            :src="
                                                                                resolvePreviewPrizeImage(
                                                                                    item.image,
                                                                                )
                                                                            "
                                                                            :alt="
                                                                                item.name
                                                                            "
                                                                            class="h-[210px] w-full object-cover sm:h-[230px]"
                                                                        />
                                                                    </template>

                                                                    <template
                                                                        v-else
                                                                    >
                                                                        <div
                                                                            class="flex h-[260px] w-full items-center justify-center bg-[linear-gradient(135deg,#1b2438,#10182b)] px-5 text-center text-sm text-white/35 sm:h-[230px]"
                                                                        >
                                                                            Gambar
                                                                            hadiah
                                                                            belum
                                                                            tersedia
                                                                        </div>
                                                                    </template>
                                                                </div>

                                                                <div
                                                                    class="mt-4"
                                                                >
                                                                    <h3
                                                                        class="line-clamp-2 text-lg font-medium leading-8 text-white"
                                                                    >
                                                                        {{
                                                                            item.name
                                                                        }}
                                                                    </h3>
                                                                </div>

                                                                <div
                                                                    class="mt-4 grid grid-cols-2 gap-4"
                                                                >
                                                                    <div>
                                                                        <div
                                                                            class="text-sm text-white/55"
                                                                        >
                                                                            Harga
                                                                            Jual
                                                                        </div>
                                                                        <div
                                                                            class="mt-2 inline-flex items-center gap-2 text-base font-medium text-white"
                                                                        >
                                                                            <img
                                                                                :src="
                                                                                    catKoinImage
                                                                                "
                                                                                alt="Cat Koin"
                                                                                class="h-5 w-5 object-contain"
                                                                            />
                                                                            <span>
                                                                                {{
                                                                                    Number(
                                                                                        item.coin_price ??
                                                                                            0,
                                                                                    ).toLocaleString(
                                                                                        "id-ID",
                                                                                    )
                                                                                }}
                                                                            </span>
                                                                        </div>
                                                                    </div>

                                                                    <div
                                                                        class="text-right"
                                                                    >
                                                                        <div
                                                                            class="text-sm text-white/55"
                                                                        >
                                                                            Hadiah
                                                                            tiket
                                                                        </div>
                                                                        <div
                                                                            class="mt-2 inline-flex items-center gap-2 text-base font-medium text-white"
                                                                        >
                                                                            <img
                                                                                :src="
                                                                                    tokenImage
                                                                                "
                                                                                alt="Token"
                                                                                class="h-5 w-5 object-contain"
                                                                            />
                                                                            <span>
                                                                                {{
                                                                                    Number(
                                                                                        item.token_reward ??
                                                                                            0,
                                                                                    ).toLocaleString(
                                                                                        "id-ID",
                                                                                    )
                                                                                }}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </article>
                                                </div>
                                            </div>
                                        </template>
                                    </section>

                                    <div
                                        class="mt-10 flex flex-col-reverse gap-3 border-t border-white/10 pt-6 sm:flex-row sm:justify-end"
                                    >
                                        <button
                                            type="button"
                                            class="h-11 rounded-lg bg-white/5 px-6 text-sm font-semibold text-white/70 hover:bg-white/10"
                                            @click="closePreview"
                                        >
                                            Tutup Preview
                                        </button>

                                        <button
                                            type="button"
                                            class="h-11 rounded-lg bg-[#ff675c] px-6 text-sm font-semibold text-white hover:bg-[#ff5145] disabled:opacity-60"
                                            :disabled="form.processing"
                                            @click="submit('published')"
                                        >
                                            {{
                                                form.processing
                                                    ? "Saving..."
                                                    : "Publish Product"
                                            }}
                                        </button>
                                    </div>
                                </main>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
<style scoped>
.preview-scroll {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.preview-scroll::-webkit-scrollbar {
    display: none;
}
</style>
