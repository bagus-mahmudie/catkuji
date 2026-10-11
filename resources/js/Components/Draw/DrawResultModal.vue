<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import axios from "axios";
import giftBoxImage from "@/assets/gift/gift.png";
import { resolvePrizeImagePath } from "@/Utils/resolvePrizeImage";
import tokenImage from "@/assets/token-silver.png";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    result: {
        type: Object,
        default: () => null,
    },
    initialStage: {
        type: String,
        default: "machine", // machine | gift
    },
});

const emit = defineEmits([
    "close",
    "go-inventory",
    "draw-again",
    "confirm-draw",
]);

const getInitialStage = () => {
    if (props.initialStage === "gift") {
        return "gift";
    }

    if (props.result?.pack?.id && !props.result?.transaction) {
        return "gift";
    }

    return "machine";
};

const stage = ref(getInitialStage()); // machine | gift | reveal

const isOpeningGift = ref(false);
const isConfirmingDraw = ref(false);
const isShakingGift = ref(false);
const revealedItems = ref([]);
const showItems = ref(false);

const isRotatingHandle = ref(false);
const isMachineShaking = ref(false);
const isPrizeBouncing = ref(false);
const prizeBounceKey = ref(0);
const isBoxRolling = ref(false);
const hasBoxInPrizeHole = ref(false);
const showClaimHint = ref(false);
const isClaimingBox = ref(false);
const claimedBoxes = ref([]);
const claimedRolls = ref(0);
const activeRollingBox = ref(0);

const prizeHoleBoxRef = ref(null);
const claimedSlotRefs = ref([]);
const flyingClaimBox = ref(null);

const giftStageBoxRef = ref(null);
const hideMachineScene = ref(false);
const isMergingClaimedBoxes = ref(false);
const mergingClaimBoxes = ref([]);
const mergedCenterBox = ref(null);
const isGiftCarryOver = ref(false);

const handleDialRef = ref(null);
const isDraggingHandle = ref(false);
const handleRotation = ref(0);
const handleProgress = ref(0);
const lastDragAngle = ref(0);

const transaction = computed(() => props.result?.transaction ?? null);
const pack = computed(() => props.result?.pack ?? null);
const items = computed(() => revealedItems.value ?? []);

const rollQuota = computed(() => {
    return Math.max(
        1,
        Number(
            props.result?.transaction?.draw_count ??
                props.result?.pack?.pack_count ??
                1,
        ),
    );
});

const machineProgressLabel = computed(() => {
    return `${claimedRolls.value} / ${rollQuota.value} gift diambil`;
});

const machineInstruction = computed(() => {
    if (hasBoxInPrizeHole.value) {
        return "Klik mystery box di lubang ambil hadiah";
    }

    if (isBoxRolling.value) {
        return "Mystery box sedang bergulir ke lubang hadiah...";
    }

    if (isDraggingHandle.value) {
        return `Lanjutkan putar searah panah: ${Math.floor(handleProgress.value)}° / 360°`;
    }

    if (isRotatingHandle.value) {
        return "Tuas berhasil diputar 360°. Box sedang keluar...";
    }

    if (claimedRolls.value >= rollQuota.value) {
        return "Semua mystery box sudah berhasil diambil";
    }

    return "Drag tuas mengikuti arah panah sampai 360°";
});

const machineBoxRows = computed(() => [
    // BARIS PALING ATAS -> 3 BOX
    [
        {
            id: "r1-1",
            style: {
                marginLeft: "0px",
                zIndex: 4,
                transform: "translate(5px, 0px) rotate(-9deg) scale(0.66)",
            },
        },
        {
            id: "r1-2",
            style: {
                marginLeft: "-12px",
                zIndex: 4,
                transform: "translate(0px, -2px) rotate(6deg) scale(0.98)",
            },
        },
        {
            id: "r1-3",
            style: {
                marginLeft: "-12px",
                zIndex: 2,
                transform: "translate(-3px, 10px) rotate(-17deg) scale(0.76)",
            },
        },
    ],

    // BARIS KE-3 -> JADI 5 BOX
    [
        {
            id: "r3-1",
            style: {
                marginLeft: "0px",
                zIndex: 2,
                transform: "translate(-11px, 2px) rotate(20deg) scale(0.83)",
            },
        },
        {
            id: "r3-2",
            style: {
                marginLeft: "-12px",
                zIndex: 2,
                transform: "translate(-6px, 0px) rotate(7deg) scale(1.40)",
            },
        },
        {
            id: "r3-3",
            style: {
                marginLeft: "-12px",
                zIndex: 2,
                transform: "translate(0px, -5px) rotate(-36deg) scale(1.24)",
            },
        },
        {
            id: "r3-4",
            style: {
                marginLeft: "-12px",
                zIndex: 2,
                transform: "translate(6px, 1px) rotate(3deg) scale(1.33)",
            },
        },
        {
            id: "r3-5",
            style: {
                marginLeft: "-12px",
                zIndex: 2,
                transform: "translate(11px, 7px) rotate(-8deg) scale(0.92)",
            },
        },
    ],

    // BARIS KEDUA
    [
        {
            id: "r3-1",
            style: {
                marginLeft: "0px",
                zIndex: 2,
                transform: "translate(-10px, -15px) rotate(-10deg) scale(1.14)",
            },
        },
        {
            id: "r3-2",
            style: {
                marginLeft: "-12px",
                zIndex: 2,
                transform: "translate(-14px, 0px) rotate(7deg) scale(0.85)",
            },
        },
        {
            id: "r3-3",
            style: {
                marginLeft: "-12px",
                zIndex: 2,
                transform: "translate(-12px, 1px) rotate(-6deg) scale(1.04)",
            },
        },
        {
            id: "r3-4",
            style: {
                marginLeft: "-12px",
                zIndex: 2,
                transform: "translate(0px, 2px) rotate(39deg) scale(1.33)",
            },
        },
    ],

    // BARIS PALING BAWAH — dibuat penuh supaya tidak ada lubang tengah
    [
        {
            id: "r4-1",
            style: {
                marginLeft: "0px",
                zIndex: 1,
                transform: "translate(-10px, 2px) rotate(-11deg) scale(1.05)",
            },
        },
        {
            id: "r4-2",
            style: {
                marginLeft: "-12px",
                zIndex: 1,
                transform: "translate(-9px, -10px) rotate(8deg) scale(1.35)",
            },
        },
        {
            id: "r4-3",
            style: {
                marginLeft: "-12px",
                zIndex: 2,
                transform: "translate(-5px, -5px) rotate(-26deg) scale(1.05)",
            },
        },
        {
            id: "r4-4",
            style: {
                marginLeft: "-12px",
                zIndex: 2,
                transform: "translate(4px, -10px) rotate(7deg) scale(1.05)",
            },
        },
        {
            id: "r4-5",
            style: {
                marginLeft: "-12px",
                zIndex: 1,
                transform: "translate(10px, 2px) rotate(-9deg) scale(1.05)",
            },
        },
    ],
]);

const resetState = () => {
    stage.value = getInitialStage();
    isOpeningGift.value = false;
    isConfirmingDraw.value = false;
    isShakingGift.value = false;
    revealedItems.value = [];
    showItems.value = false;

    isRotatingHandle.value = false;
    isMachineShaking.value = false;
    isPrizeBouncing.value = false;
    prizeBounceKey.value = 0;
    isBoxRolling.value = false;
    hasBoxInPrizeHole.value = false;
    showClaimHint.value = false;
    isClaimingBox.value = false;
    claimedBoxes.value = [];
    claimedSlotRefs.value = [];
    flyingClaimBox.value = null;
    hideMachineScene.value = false;
    isMergingClaimedBoxes.value = false;
    mergingClaimBoxes.value = [];
    mergedCenterBox.value = null;
    isGiftCarryOver.value = false;
    claimedRolls.value = 0;
    activeRollingBox.value = 0;

    isDraggingHandle.value = false;
    handleRotation.value = 0;
    handleProgress.value = 0;
    lastDragAngle.value = 0;
};

const getPointerAngle = (event) => {
    const dial = handleDialRef.value;
    if (!dial) return 0;

    const rect = dial.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    const x = event.clientX - centerX;
    const y = event.clientY - centerY;

    return Math.atan2(y, x) * (180 / Math.PI);
};

const normalizeAngleDelta = (delta) => {
    if (delta > 180) return delta - 360;
    if (delta < -180) return delta + 360;

    return delta;
};

const resetHandleDrag = () => {
    isDraggingHandle.value = false;

    window.removeEventListener("pointermove", onHandlePointerMove);
    window.removeEventListener("pointerup", onHandlePointerUp);
    window.removeEventListener("pointercancel", onHandlePointerUp);
};

const triggerPrizeBounce = async () => {
    isPrizeBouncing.value = false;

    await nextTick();

    prizeBounceKey.value += 1;
    isPrizeBouncing.value = true;
};

const releaseBoxToPrizeHole = async () => {
    activeRollingBox.value = claimedRolls.value + 1;

    isRotatingHandle.value = true;
    isMachineShaking.value = true;

    await triggerPrizeBounce();

    await new Promise((resolve) => setTimeout(resolve, 720));

    isMachineShaking.value = false;
    isPrizeBouncing.value = false;
    isBoxRolling.value = true;

    await new Promise((resolve) => setTimeout(resolve, 1500));

    isBoxRolling.value = false;
    isRotatingHandle.value = false;
    hasBoxInPrizeHole.value = true;
    showClaimHint.value = false;

    handleRotation.value = 0;
    handleProgress.value = 0;

    setTimeout(() => {
        if (hasBoxInPrizeHole.value) {
            showClaimHint.value = true;
        }
    }, 1000);
};

const completeHandleTurn = async () => {
    resetHandleDrag();

    handleProgress.value = 360;
    handleRotation.value = 360;

    await releaseBoxToPrizeHole();
};

const onHandlePointerMove = async (event) => {
    if (!isDraggingHandle.value) return;

    const currentAngle = getPointerAngle(event);
    let delta = normalizeAngleDelta(currentAngle - lastDragAngle.value);

    lastDragAngle.value = currentAngle;

    /**
     * Arah putar yang dipakai: clockwise / searah jarum jam.
     * Pada koordinat layar, putaran clockwise menghasilkan delta positif.
     */
    if (delta < 0) {
        delta = 0;
    }

    if (delta > 80) {
        delta = 0;
    }

    const nextProgress = Math.min(360, handleProgress.value + delta);

    handleProgress.value = nextProgress;
    handleRotation.value = nextProgress;

    if (nextProgress >= 360) {
        await completeHandleTurn();
    }
};

const onHandlePointerUp = () => {
    if (!isDraggingHandle.value) return;

    resetHandleDrag();

    if (handleProgress.value < 360) {
        handleRotation.value = 0;
        handleProgress.value = 0;
    }
};

const startHandleDrag = (event) => {
    if (
        isRotatingHandle.value ||
        isBoxRolling.value ||
        isClaimingBox.value ||
        hasBoxInPrizeHole.value ||
        claimedRolls.value >= rollQuota.value
    ) {
        return;
    }

    event.preventDefault();

    isDraggingHandle.value = true;

    const angle = getPointerAngle(event);
    lastDragAngle.value = angle;
    handleProgress.value = 0;
    handleRotation.value = 0;

    window.addEventListener("pointermove", onHandlePointerMove);
    window.addEventListener("pointerup", onHandlePointerUp);
    window.addEventListener("pointercancel", onHandlePointerUp);
};

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const setClaimedSlotRef = (el, index) => {
    if (el) {
        claimedSlotRefs.value[index] = el;
    }
};

const startClaimBoxFlight = async (targetIndex) => {
    await nextTick();

    const fromEl = prizeHoleBoxRef.value;
    const toEl = claimedSlotRefs.value[targetIndex];

    if (!fromEl || !toEl) {
        return false;
    }

    const fromRect = fromEl.getBoundingClientRect();
    const toRect = toEl.getBoundingClientRect();

    const fromX = fromRect.left + fromRect.width / 2;
    const fromY = fromRect.top + fromRect.height / 2;
    const toX = toRect.left + toRect.width / 2;
    const toY = toRect.top + toRect.height / 2;

    const moveX = toX - fromX;
    const moveY = toY - fromY;

    flyingClaimBox.value = {
        id: `flying-claim-${Date.now()}`,
        fromX,
        fromY,
        moveX,
        moveY,
        midX: moveX * 0.48,
        midY: moveY * 0.55 - 34,
        scaleEnd: Math.max(0.42, toRect.width / fromRect.width),
    };

    await nextTick();

    return true;
};

const startClaimedBoxesMerge = async () => {
    await nextTick();

    if (!claimedBoxes.value.length) {
        return;
    }

    // Ambil posisi slot terlebih dahulu sebelum stage machine diganti.
    const slotRects = claimedBoxes.value
        .map((box, index) => {
            const slotEl = claimedSlotRefs.value[index];

            if (!slotEl) return null;

            const rect = slotEl.getBoundingClientRect();

            return {
                id: `merge-${box.id}-${Date.now()}`,
                fromX: rect.left + rect.width / 2,
                fromY: rect.top + rect.height / 2,
                delay: index * 35,
            };
        })
        .filter(Boolean);

    if (!slotRects.length) {
        return;
    }

    // 1. Hilangkan machine dulu.
    hideMachineScene.value = true;

    await wait(280);

    // 2. Pindah ke stage gift saat masih hidden.
    // Jadi user belum melihat modal gift, tapi DOM gift sudah bisa diukur.
    stage.value = "gift";
    isGiftCarryOver.value = true;

    await nextTick();

    const giftEl = giftStageBoxRef.value;

    if (!giftEl) {
        return;
    }

    const giftRect = giftEl.getBoundingClientRect();

    const targetX = giftRect.left + giftRect.width / 2;
    const targetY = giftRect.top + giftRect.height / 2;

    // 3. Semua box kecil merge langsung ke titik gift asli.
    mergingClaimBoxes.value = slotRects.map((box) => ({
        ...box,
        moveX: targetX - box.fromX,
        moveY: targetY - box.fromY,
    }));

    isMergingClaimedBoxes.value = true;
    mergedCenterBox.value = null;

    await nextTick();

    await wait(560);

    mergingClaimBoxes.value = [];

    // 4. Box besar langsung muncul di titik gift asli, bukan di center machine.
    mergedCenterBox.value = {
        id: `merged-center-box-${Date.now()}`,
        x: targetX,
        y: targetY,
        width: giftRect.width,
        height: giftRect.height,
    };

    await wait(640);

    isMergingClaimedBoxes.value = false;
};

const confirmDrawBeforeGiftStage = async () => {
    if (pack.value?.id) {
        return true;
    }

    if (isConfirmingDraw.value) {
        return false;
    }

    isConfirmingDraw.value = true;

    return await new Promise((resolve) => {
        emit("confirm-draw", {
            onSuccess: async () => {
                await nextTick();

                isConfirmingDraw.value = false;
                resolve(true);
            },
            onError: (message) => {
                isConfirmingDraw.value = false;

                if (message) {
                    alert(message);
                }

                resolve(false);
            },
        });
    });
};

const claimFallenBox = async () => {
    if (
        !hasBoxInPrizeHole.value ||
        isRotatingHandle.value ||
        isBoxRolling.value ||
        isClaimingBox.value
    ) {
        return;
    }

    showClaimHint.value = false;
    isClaimingBox.value = true;

    const targetIndex = claimedRolls.value;
    const canFly = await startClaimBoxFlight(targetIndex);

    hasBoxInPrizeHole.value = false;

    await wait(canFly ? 760 : 260);

    const nextClaimed = claimedRolls.value + 1;

    claimedBoxes.value.push({
        id: `claimed-box-${nextClaimed}`,
    });

    flyingClaimBox.value = null;
    claimedRolls.value = nextClaimed;
    isClaimingBox.value = false;

    await wait(260);

    if (claimedRolls.value >= rollQuota.value) {
        await wait(420);

        /**
         * Capsule machine sudah selesai.
         * Baru di sini pembelian dilakukan:
         * - coin berkurang
         * - stock event berkurang
         * - stock prize berkurang
         * - pack dibuat
         */
        const confirmed = await confirmDrawBeforeGiftStage();

        if (!confirmed) {
            emit("close");
            return;
        }

        await startClaimedBoxesMerge();

        claimedBoxes.value = [];

        hideMachineScene.value = false;

        await wait(220);

        mergedCenterBox.value = null;
        isGiftCarryOver.value = false;
    }
};

onBeforeUnmount(() => {
    resetHandleDrag();
});

const openGiftAndReveal = async () => {
    if (isOpeningGift.value) return;

    isOpeningGift.value = true;
    isShakingGift.value = true;

    try {
        await new Promise((resolve) => setTimeout(resolve, 700));

        const packId = pack.value?.id;
        if (!packId) {
            alert("Pack belum siap. Silakan coba lagi.");
            return;
        }
        const res = await axios.post(route("inventory.pack.open", packId));

        revealedItems.value = res.data?.data?.results ?? [];
        stage.value = "reveal";

        await new Promise((resolve) => setTimeout(resolve, 80));
        showItems.value = true;

        window.dispatchEvent(
            new CustomEvent("pack:opened", {
                detail: { count: 1 },
            }),
        );
    } catch (err) {
        alert(err.response?.data?.message || "Gagal membuka pack");
    } finally {
        isOpeningGift.value = false;
        isShakingGift.value = false;
    }
};

watch(
    () => props.show,
    () => {
        resetState();
    },
);

const count = computed(() => items.value.length);

const revealModalClass = computed(() => {
    if (count.value <= 1) return "max-w-[520px]";
    if (count.value === 2) return "max-w-[620px]";
    if (count.value === 3 || count.value === 4) return "max-w-[760px]";
    if (count.value >= 5 && count.value <= 8) return "max-w-[880px]";
    return "max-w-[980px]";
});

const layoutClass = computed(() => {
    const c = count.value;

    if (c === 1) return "grid-cols-1 max-w-[260px]";
    if (c === 2) return "grid-cols-2 max-w-[420px]";
    if (c === 3) return "grid-cols-2 sm:grid-cols-3 max-w-[540px]";
    if (c === 4) return "grid-cols-2 max-w-[520px]";
    if (c === 5) return "grid-cols-2 sm:grid-cols-3 max-w-[560px]";
    if (c === 6) return "grid-cols-2 sm:grid-cols-3 max-w-[720px]";
    if (c === 7) return "grid-cols-2 sm:grid-cols-4 max-w-[760px]";
    if (c === 8) return "grid-cols-2 sm:grid-cols-4 max-w-[760px]";
    if (c === 9) return "grid-cols-2 sm:grid-cols-3 max-w-[780px]";
    return "grid-cols-2 sm:grid-cols-5 max-w-[820px]";
});

const cardWrapWidthClass = computed(() => {
    if (count.value <= 1) return "max-w-[210px]";
    if (count.value === 2) return "max-w-[150px] sm:max-w-[170px]";
    if (count.value === 3 || count.value === 4) {
        return "max-w-[120px] sm:max-w-[150px]";
    }
    if (count.value >= 5 && count.value <= 8) {
        return "max-w-[104px] sm:max-w-[132px]";
    }
    return "max-w-[96px] sm:max-w-[118px]";
});

const imageSizeClass = computed(() => {
    if (count.value <= 1) return "w-[210px] h-[210px]";
    if (count.value === 2)
        return "w-[150px] h-[150px] sm:w-[165px] sm:h-[165px]";
    if (count.value === 3 || count.value === 4) {
        return "w-[120px] h-[120px] sm:w-[145px] sm:h-[145px]";
    }
    if (count.value >= 5 && count.value <= 8) {
        return "w-[92px] h-[92px] sm:w-[120px] sm:h-[120px]";
    }
    return "w-[84px] h-[84px] sm:w-[102px] sm:h-[102px]";
});

const titleClass = computed(() => {
    const c = count.value;

    if (c <= 1) return "max-w-[230px] text-[18px]";
    if (c === 2 || c === 3 || c === 4) {
        return "max-w-[150px] sm:max-w-[175px] text-[13px] sm:text-[16px]";
    }
    if (c >= 5 && c <= 8) {
        return "max-w-[120px] sm:max-w-[145px] text-[11px] sm:text-[14px]";
    }

    return "max-w-[112px] sm:max-w-[132px] text-[11px] sm:text-[13px]";
});

const titleHeightClass = computed(() => {
    const c = count.value;

    if (c <= 1) return "min-h-[42px]";
    if (c === 2 || c === 3 || c === 4) {
        return "min-h-[36px] sm:min-h-[38px]";
    }
    if (c >= 5 && c <= 8) {
        return "min-h-[20px] sm:min-h-[34px]";
    }

    return "min-h-[30px] sm:min-h-[32px]";
});

const sunburstStyle = {
    backgroundImage: `
        repeating-conic-gradient(
            from 0deg,
            rgba(255,255,255,0.14) 0deg,
            rgba(255,255,255,0.14) 2deg,
            transparent 2deg,
            transparent 18deg
        )
    `,
};

const repeatDrawCount = computed(() => {
    return Number(transaction.value?.draw_count ?? pack.value?.pack_count ?? 1);
});

const canRepeatDraw = computed(() => {
    return !!transaction.value && !!props.result?.event_slug;
});

const primaryActionLabel = computed(() => {
    if (canRepeatDraw.value) {
        return `Beli x${repeatDrawCount.value} lagi`;
    }

    return "Lihat hadiah di Inventory";
});

const handlePrimaryAction = () => {
    if (canRepeatDraw.value) {
        emit("draw-again", {
            draw_count: repeatDrawCount.value,
            event_slug: props.result?.event_slug,
        });
        return;
    }

    emit("go-inventory");
};
const requestClose = () => {
    if (isConfirmingDraw.value) return;

    emit("close");
};
</script>

<template>
    <Teleport to="body">
        <img
            v-for="box in mergingClaimBoxes"
            :key="box.id"
            :src="giftBoxImage"
            alt="Merging claimed gift box"
            class="claimed-merge-flying-box-custom fixed pointer-events-none z-[9999] h-[30px] w-[30px] object-contain drop-shadow-[0_16px_30px_rgba(251,103,88,0.35)]"
            :style="{
                left: `${box.fromX}px`,
                top: `${box.fromY}px`,
                '--merge-x': `${box.moveX}px`,
                '--merge-y': `${box.moveY}px`,
                animationDelay: `${box.delay}ms`,
            }"
        />

        <img
            v-if="mergedCenterBox"
            :key="mergedCenterBox.id"
            :src="giftBoxImage"
            alt="Merged gift box"
            class="claimed-merge-center-box-custom fixed pointer-events-none z-[10000] object-contain drop-shadow-[0_24px_55px_rgba(251,103,88,0.42)]"
            :style="{
                left: `${mergedCenterBox.x}px`,
                top: `${mergedCenterBox.y}px`,
                width: `${mergedCenterBox.width}px`,
                height: `${mergedCenterBox.height}px`,
            }"
        />

        <img
            v-if="flyingClaimBox"
            :key="flyingClaimBox.id"
            :src="giftBoxImage"
            alt="Flying claimed gift box"
            class="claimed-flying-box-custom fixed z-[9999] pointer-events-none h-[54px] w-[54px] object-contain drop-shadow-[0_18px_34px_rgba(251,103,88,0.35)]"
            :style="{
                left: `${flyingClaimBox.fromX}px`,
                top: `${flyingClaimBox.fromY}px`,
                '--move-x': `${flyingClaimBox.moveX}px`,
                '--move-y': `${flyingClaimBox.moveY}px`,
                '--mid-x': `${flyingClaimBox.midX}px`,
                '--mid-y': `${flyingClaimBox.midY}px`,
                '--scale-end': flyingClaimBox.scaleEnd,
            }"
        />

        <div
            v-if="show"
            class="fixed inset-0 z-[100] overflow-y-auto bg-black/75 px-4 py-8 backdrop-blur-[3px]"
            @click="requestClose"
        >
            <div
                class="machine-scene-layer-custom flex min-h-full items-center justify-center"
                :class="{ 'machine-scene-hidden-custom': hideMachineScene }"
            >
                <!-- STAGE MACHINE -->
                <div
                    v-if="stage === 'machine'"
                    class="relative z-10 flex w-full max-w-[560px] flex-col items-center"
                    @click.stop
                >
                    <div
                        class="relative w-full overflow-hidden rounded-2xl bg-[#101521] p-4 sm:p-5"
                    >
                        <div class="pointer-events-none absolute inset-0" />

                        <div class="relative z-10">
                            <div
                                class="mb-4 flex items-center justify-between gap-3"
                            >
                                <!-- <div>
                                    <p
                                        class="text-xs font-semibold uppercase tracking-[0.24em] text-[#42E8E0]"
                                    >
                                        Capsule Machine
                                    </p>

                                    <h2
                                        class="mt-1 text-xl font-semibold text-white sm:text-2xl"
                                    >
                                        Putar tuas untuk roll hadiah
                                    </h2>
                                </div> -->

                                <div
                                    class="flex min-h-[42px] flex-wrap items-center gap-2"
                                >
                                    <div
                                        class="shrink-0 rounded-full border border-white/10 bg-white/[0.07] px-3 py-1.5 text-sm font-medium text-white"
                                    >
                                        {{ machineProgressLabel }}
                                    </div>

                                    <div class="flex items-center gap-1.5">
                                        <div
                                            v-for="n in rollQuota"
                                            :key="`claimed-slot-${n}`"
                                            :ref="
                                                (el) =>
                                                    setClaimedSlotRef(el, n - 1)
                                            "
                                            class="claimed-box-slot-custom"
                                            :class="{
                                                'claimed-box-slot-empty-custom':
                                                    n > claimedBoxes.length,
                                                'claimed-box-slot-merging-custom':
                                                    isMergingClaimedBoxes,
                                            }"
                                        >
                                            <img
                                                v-if="n <= claimedBoxes.length"
                                                :key="claimedBoxes[n - 1]?.id"
                                                :src="giftBoxImage"
                                                alt="Claimed mystery gift box"
                                                class="h-full w-full object-contain drop-shadow-[0_8px_16px_rgba(251,103,88,0.28)]"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="relative mx-auto max-w-[400px]">
                                <div
                                    class="capsule-cartoon-machine relative mx-auto h-[530px]"
                                    :class="{
                                        'machine-shake-custom':
                                            isMachineShaking,
                                    }"
                                >
                                    <!-- GLASS BALL -->
                                    <div
                                        class="absolute left-1/2 top-8 z-10 h-[260px] w-[260px] -translate-x-1/2 overflow-hidden rounded-full border-4 border-[#7e7e7e] bg-[radial-gradient(circle_at_35%_25%,rgba(255,255,255,0.92),rgba(255,255,255,0.28)_28%,rgba(155,230,255,0.26)_62%,rgba(255,255,255,0.14)_100%)] shadow-[inset_-18px_-22px_35px_rgba(66,232,224,0.10),0_18px_40px_rgba(0,0,0,0.30)]"
                                    >
                                        <!-- DASAR KACA / AREA TUMPUKAN -->
                                        <div
                                            class="pointer-events-none absolute bottom-[-2px] left-1/2 z-0 h-[150px] w-[268px] -translate-x-1/2 rounded-b-full bg-[radial-gradient(ellipse_at_center,rgba(0,0,0,0.42),rgba(0,0,0,0.18)_48%,transparent_72%)]"
                                        ></div>

                                        <!-- TUMPUKAN GIFT DI DALAM KACA -->
                                        <div
                                            class="absolute inset-x-[-4px] bottom-[-10px] z-10 flex h-full items-end justify-center overflow-hidden px-0 pb-0"
                                        >
                                            <div
                                                class="machine-pile-shell flex h-full w-[268px] items-end justify-center overflow-hidden rounded-b-full px-0 pb-0"
                                            >
                                                <div
                                                    :key="`prize-pile-${prizeBounceKey}`"
                                                    class="machine-pile-inner flex w-full flex-col justify-end"
                                                    :class="{
                                                        'machine-prize-bounce-custom':
                                                            isPrizeBouncing,
                                                    }"
                                                >
                                                    <div
                                                        v-for="(
                                                            row, rowIndex
                                                        ) in machineBoxRows"
                                                        :key="`row-${rowIndex}`"
                                                        class="machine-pile-row flex w-full items-end justify-center"
                                                    >
                                                        <img
                                                            v-for="box in row"
                                                            :key="`${box.id}-${prizeBounceKey}`"
                                                            :src="giftBoxImage"
                                                            alt="Mystery gift box"
                                                            class="machine-mini-box h-[60px] w-[60px] shrink-0 object-contain"
                                                            :class="{
                                                                'machine-mini-box-bounce-custom':
                                                                    isPrizeBouncing,
                                                            }"
                                                            :style="box.style"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- BOX YANG BERGULIR KELUAR -->
                                        <img
                                            v-if="isBoxRolling"
                                            :key="activeRollingBox"
                                            :src="giftBoxImage"
                                            alt="Rolling mystery gift box"
                                            class="rolling-prize-box-custom absolute z-30 h-[54px] w-[54px] object-contain drop-shadow-[0_20px_38px_rgba(251,103,88,0.35)]"
                                        />

                                        <!-- KACA DEPAN / LENS TINT -->
                                        <div
                                            class="pointer-events-none absolute inset-0 z-40 rounded-full bg-[radial-gradient(circle_at_50%_10%,rgba(255,255,255,0.22),rgba(255,255,255,0.10)_22%,rgba(255,255,255,0.04)_38%,rgba(255,255,255,0)_60%)]"
                                        ></div>

                                        <!-- KACA DEPAN / INNER GLASS SHADOW -->
                                        <div
                                            class="pointer-events-none absolute inset-0 z-40 rounded-full shadow-[inset_0_18px_26px_rgba(255,255,255,0.16),inset_0_-22px_30px_rgba(0,0,0,0.18),inset_14px_0_22px_rgba(255,255,255,0.05),inset_-14px_0_22px_rgba(0,0,0,0.08)]"
                                        ></div>

                                        <!-- HIGHLIGHT BESAR KACA -->
                                        <div
                                            class="pointer-events-none absolute left-[34px] top-[24px] z-50 h-[92px] w-[74px] rotate-[-12deg] rounded-full bg-white/26 blur-[10px]"
                                        ></div>

                                        <!-- HIGHLIGHT KECIL KACA -->
                                        <div
                                            class="pointer-events-none absolute left-[70px] top-[48px] z-50 h-[20px] w-[18px] rounded-full bg-white/42 blur-[2px]"
                                        ></div>
                                    </div>

                                    <!-- NECK -->
                                    <div
                                        class="absolute left-1/2 top-[260px] z-20 h-12 w-[190px] -translate-x-1/2 rounded-xl border-4 border-[#5B3818] bg-[#FB2546] shadow-[inset_0_5px_0_rgba(255,255,255,0.24)]"
                                    />

                                    <!-- BODY RED -->
                                    <div
                                        class="absolute left-1/2 top-[302px] z-10 h-[160px] w-[260px] -translate-x-1/2 rounded-t-[42px] border-4 border-[#5B3818] bg-[linear-gradient(135deg,#FF1744,#C9002B)] shadow-[inset_5px_5px_0_rgba(255,255,255,0.18),0_20px_40px_rgba(0,0,0,0.32)]"
                                    >
                                        <!-- HANDLE + PRIZE ROW -->
                                        <div
                                            class="absolute left-1/2 top-[40px] flex w-[200px] -translate-x-1/2 items-center justify-between gap-5"
                                        >
                                            <!-- COIN / HANDLE AREA -->
                                            <div
                                                ref="handleDialRef"
                                                class="relative h-[82px] w-[82px] shrink-0 touch-none select-none"
                                            >
                                                <!-- PROGRESS RING -->
                                                <div
                                                    class="pointer-events-none absolute -inset-1.5 z-10 rounded-full"
                                                    :style="{
                                                        background: `conic-gradient(#42E8E0 ${handleProgress}deg, rgba(255,255,255,0.16) 0deg)`,
                                                    }"
                                                />

                                                <!-- HANDLE BASE -->
                                                <div
                                                    class="absolute inset-0 z-20 rounded-full border-[6px] border-[#5B3818] bg-[#FFF5D6] shadow-[inset_0_3px_0_rgba(255,255,255,0.8)]"
                                                />

                                                <!-- TUAS YANG DI-DRAG -->
                                                <button
                                                    type="button"
                                                    class="absolute inset-2 z-30 cursor-grab touch-none select-none rounded-full border-[4px] border-[#5B3818] bg-[#F8E8B5] active:cursor-grabbing disabled:cursor-not-allowed disabled:opacity-70"
                                                    :disabled="
                                                        isRotatingHandle ||
                                                        isBoxRolling ||
                                                        hasBoxInPrizeHole ||
                                                        claimedRolls >=
                                                            rollQuota
                                                    "
                                                    :style="{
                                                        transform: `rotate(${handleRotation}deg)`,
                                                    }"
                                                    @pointerdown="
                                                        startHandleDrag
                                                    "
                                                >
                                                    <span
                                                        class="absolute left-1/2 top-1/2 h-[8px] w-[50px] -translate-x-1/2 -translate-y-1/2 -rotate-45 rounded-full bg-[#5B3818]"
                                                    />
                                                    <span
                                                        class="absolute left-1/2 top-1/2 h-[8px] w-[50px] -translate-x-1/2 -translate-y-1/2 rotate-45 rounded-full bg-[#5B3818]"
                                                    />
                                                </button>
                                            </div>

                                            <!-- PRIZE HOLE -->
                                            <div
                                                class="relative h-[90px] w-[80px] shrink-0 rounded-b-xl rounded-t-full border-4 border-[#5B3818] bg-black/25 shadow-[inset_0_12px_18px_rgba(0,0,0,0.28)]"
                                            >
                                                <!-- HINT TAP TO CLAIM -->
                                                <Transition
                                                    name="machine-hint-fade"
                                                >
                                                    <div
                                                        v-if="showClaimHint"
                                                        class="pointer-events-none absolute left-2/3 top-[60px] gap-5 z-50 flex -translate-x-1/2 flex-col items-center"
                                                    >
                                                        <!-- ICON HAND -->
                                                        <div
                                                            class="animate-hint-claim-hand-custom"
                                                        >
                                                            <i
                                                                class="bi bi-hand-index-thumb-fill text-[42px] leading-none text-white drop-shadow-[0_2px_0_#FF6A4A]"
                                                                style="
                                                                    -webkit-text-stroke: 1.5px
                                                                        #ff6a4a;
                                                                "
                                                            ></i>
                                                        </div>

                                                        <!-- TEXT HINT -->
                                                        <div
                                                            class="animate-hint-text-custom w-[180px] text-center text-2xl font-black leading-tight text-white drop-shadow-[0_2px_0_#FF6A4A]"
                                                            style="
                                                                -webkit-text-stroke: 1px
                                                                    #ff6a4a;
                                                            "
                                                        >
                                                            Klik untuk<br />
                                                            ambil hadiah!
                                                        </div>
                                                    </div>
                                                </Transition>
                                                <button
                                                    v-if="hasBoxInPrizeHole"
                                                    ref="prizeHoleBoxRef"
                                                    type="button"
                                                    class="prize-hole-box-custom absolute left-1/2 top-5 z-30 h-[54px] w-[54px] -translate-x-1/2 transition hover:scale-105 disabled:pointer-events-none"
                                                    :disabled="isClaimingBox"
                                                    @click="claimFallenBox"
                                                >
                                                    <img
                                                        :src="giftBoxImage"
                                                        alt="Mystery gift box"
                                                        class="h-full w-full object-contain drop-shadow-[0_16px_30px_rgba(251,103,88,0.35)]"
                                                    />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- HINT PUTAR TUAS -->
                                        <Transition name="machine-hint-fade">
                                            <div
                                                v-if="
                                                    !isRotatingHandle &&
                                                    !isBoxRolling &&
                                                    !hasBoxInPrizeHole &&
                                                    claimedRolls < rollQuota
                                                "
                                                class="pointer-events-none absolute left-1/3 bottom-[-50px] gap-5 z-50 flex -translate-x-1/2 flex-col items-center"
                                            >
                                                <!-- ICON HAND -->
                                                <div
                                                    class="animate-hint-hand-custom mb-[2px]"
                                                >
                                                    <i
                                                        class="bi bi-hand-index-thumb-fill text-[42px] leading-none text-white drop-shadow-[0_2px_0_#FF6A4A]"
                                                        style="
                                                            -webkit-text-stroke: 1.5px
                                                                #ff6a4a;
                                                        "
                                                    ></i>
                                                </div>

                                                <!-- TEXT HINT -->
                                                <div
                                                    class="animate-hint-text-custom whitespace-nowrap text-2xl font-black leading-none text-white drop-shadow-[0_2px_0_#FF6A4A]"
                                                    style="
                                                        -webkit-text-stroke: 1px
                                                            #ff6a4a;
                                                    "
                                                >
                                                    Putar Tuas !
                                                </div>
                                            </div>
                                        </Transition>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-4 rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-3 text-center text-xs lg:text-sm font-medium text-white/75"
                            >
                                {{ machineInstruction }}
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-6 inline-flex h-[58px] w-[58px] items-center justify-center rounded-full bg-[#FB6758] text-white transition hover:scale-[1.03] hover:brightness-105"
                        @click="requestClose"
                    >
                        <i class="bi bi-x text-3xl leading-none" />
                    </button>
                </div>

                <!-- STAGE GIFT -->
                <div
                    v-else-if="stage === 'gift'"
                    class="relative z-10 flex w-full max-w-[420px] flex-col items-center"
                    @click.stop
                >
                    <div
                        class="relative w-full overflow-hidden rounded-xl bg-[#101521] p-5"
                    >
                        <div class="relative z-10 flex flex-col items-center">
                            <button
                                type="button"
                                class="flex w-full flex-col items-center justify-center"
                                :disabled="isOpeningGift"
                                @click="openGiftAndReveal"
                            >
                                <div
                                    class="relative flex aspect-square w-full max-w-full items-center justify-center overflow-hidden rounded-xl"
                                >
                                    <div
                                        class="pointer-events-none absolute left-1/2 top-1/2 z-0 h-[150%] w-[150%] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-[0.18] animate-gift-sunburst-spin-custom"
                                        :style="sunburstStyle"
                                    />

                                    <img
                                        ref="giftStageBoxRef"
                                        :src="giftBoxImage"
                                        alt="Gift Box"
                                        class="relative z-10 w-[240px] max-w-full object-contain drop-shadow-[0_18px_40px_rgba(255,120,90,0.18)]"
                                        :class="[
                                            isGiftCarryOver
                                                ? 'opacity-0'
                                                : isShakingGift
                                                  ? 'animate-gift-shake-custom'
                                                  : 'animate-gift-float-custom',
                                        ]"
                                    />
                                </div>
                            </button>

                            <p
                                class="relative z-20 -mt-6 text-center text-base text-white/60"
                            >
                                Klik kado untuk membuka pack
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-6 inline-flex h-[58px] w-[58px] items-center justify-center rounded-full bg-[#FB6758] text-white transition hover:scale-[1.03] hover:brightness-105"
                        @click="requestClose"
                    >
                        <i class="bi bi-x text-3xl leading-none" />
                    </button>
                </div>

                <!-- STAGE REVEAL -->
                <div
                    v-else
                    class="relative z-10 flex w-full flex-col items-center"
                >
                    <div
                        class="relative w-full overflow-hidden rounded-xl bg-[#101521] px-3 pb-5 pt-3 sm:px-6 sm:pb-8 sm:pt-6"
                        :class="revealModalClass"
                        @click.stop
                    >
                        <button
                            type="button"
                            class="absolute right-6 top-6 z-20 inline-flex h-[42px] w-[42px] items-center justify-center rounded-full bg-white/10 text-white transition hover:scale-[1.03] hover:bg-white/15"
                            @click="requestClose"
                        >
                            <i class="bi bi-x-lg" />
                        </button>

                        <div
                            class="pointer-events-none absolute inset-0 overflow-hidden rounded-xl"
                        >
                            <div
                                class="absolute inset-0 bg-[linear-gradient(180deg,rgba(7,19,39,0.02),rgba(7,19,39,0.24))]"
                            />
                        </div>

                        <div class="relative z-10">
                            <div
                                class="relative flex w-full items-center justify-center overflow-hidden px-3 py-3 rounded-xl min-h-[360px] sm:px-3 sm:pb-5 sm:pt-8 sm:min-h-[460px]"
                            >
                                <div
                                    class="pointer-events-none absolute left-1/2 top-1/2 z-0 h-[150%] w-[150%] -translate-x-1/2 -translate-y-1/2 opacity-0"
                                    :class="{
                                        'animate-sunburst-japan-in-custom':
                                            showItems,
                                    }"
                                    :style="sunburstStyle"
                                />

                                <div
                                    class="relative z-[2] grid w-full justify-center justify-items-center items-start"
                                    :class="[
                                        layoutClass,
                                        count >= 8
                                            ? 'gap-y-4 gap-x-4 sm:gap-y-[18px] sm:gap-x-[14px]'
                                            : 'gap-y-5 gap-x-5 sm:gap-y-5 sm:gap-x-[18px]',
                                    ]"
                                >
                                    <div
                                        v-for="(item, index) in items"
                                        :key="item.draw_result_id ?? index"
                                        class="relative flex w-full items-start justify-center"
                                    >
                                        <div
                                            class="relative flex w-full flex-col items-center"
                                            :class="cardWrapWidthClass"
                                        >
                                            <div
                                                class="pointer-events-none absolute left-1/2 top-[42%] z-0 rounded-full opacity-0 blur-[10px]"
                                                :style="{
                                                    animationDelay: `${index * 90}ms`,
                                                    width:
                                                        count <= 1
                                                            ? '165px'
                                                            : count <= 4
                                                              ? '140px'
                                                              : count <= 8
                                                                ? '118px'
                                                                : '102px',
                                                    height:
                                                        count <= 1
                                                            ? '165px'
                                                            : count <= 4
                                                              ? '140px'
                                                              : count <= 8
                                                                ? '118px'
                                                                : '102px',
                                                    transform:
                                                        'translate(-50%, -50%) scale(0.2)',
                                                    background:
                                                        'radial-gradient(circle, rgba(255,255,255,0.24) 0%, rgba(255,214,107,0.14) 20%, rgba(255,162,65,0.08) 38%, rgba(255,255,255,0) 72%)',
                                                }"
                                                :class="{
                                                    'animate-pack-glow-in-custom':
                                                        showItems,
                                                }"
                                            />

                                            <div
                                                class="relative z-[2] opacity-0 [transform-style:preserve-3d] [backface-visibility:hidden] [filter:blur(10px)]"
                                                :style="{
                                                    animationDelay: `${index * 90}ms`,
                                                }"
                                                :class="{
                                                    'animate-pack-item-in-custom':
                                                        showItems,
                                                }"
                                            >
                                                <div
                                                    class="w-full overflow-hidden rounded-[14px]"
                                                >
                                                    <div
                                                        class="flex items-center justify-center overflow-hidden rounded-[10px]"
                                                        :class="imageSizeClass"
                                                    >
                                                        <img
                                                            v-if="
                                                                resolvePrizeImagePath(
                                                                    item.image,
                                                                )
                                                            "
                                                            :src="
                                                                resolvePrizeImagePath(
                                                                    item.image,
                                                                )
                                                            "
                                                            :alt="item.name"
                                                            class="h-full w-full rounded-[14px] object-cover"
                                                        />
                                                        <div
                                                            v-else
                                                            class="flex h-full w-full items-center justify-center px-3 text-center text-[11px] text-slate-500"
                                                        >
                                                            Gambar belum
                                                            tersedia
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                class="mt-1 w-full translate-y-[10px] text-center opacity-0"
                                                :style="{
                                                    animationDelay: `${index * 90 + 120}ms`,
                                                }"
                                                :class="{
                                                    'animate-result-caption-in-custom':
                                                        showItems,
                                                }"
                                            >
                                                <div
                                                    class="flex w-full items-center justify-center"
                                                    :class="titleHeightClass"
                                                >
                                                    <h3
                                                        class="mx-auto overflow-hidden text-center font-semibold leading-[1.15] text-white [overflow-wrap:anywhere]"
                                                        :class="titleClass"
                                                        :style="{
                                                            display:
                                                                '-webkit-box',
                                                            lineClamp: '2',
                                                            WebkitLineClamp:
                                                                '2',
                                                            WebkitBoxOrient:
                                                                'vertical',
                                                        }"
                                                    >
                                                        {{ item.name }}
                                                    </h3>
                                                </div>

                                                <div
                                                    v-if="
                                                        Number(
                                                            item.token_reward ??
                                                                0,
                                                        ) > 0
                                                    "
                                                    class="mx-auto mt-2 inline-flex items-center justify-center gap-1.5 rounded-full bg-white/[0.06] px-2.5 py-1 text-xs font-semibold text-white sm:text-sm"
                                                >
                                                    <img
                                                        :src="tokenImage"
                                                        alt="Token"
                                                        class="h-3 w-3 object-contain sm:h-4 sm:w-4"
                                                    />
                                                    <span
                                                        >+{{
                                                            Number(
                                                                item.token_reward,
                                                            ).toLocaleString(
                                                                "id-ID",
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-6 inline-flex h-[58px] w-full max-w-[520px] items-center justify-center rounded-xl bg-[#FB6758] px-7 text-base font-medium text-white transition hover:scale-[1.03] hover:brightness-105"
                        :class="revealModalClass"
                        @click.stop="handlePrimaryAction"
                    >
                        {{ primaryActionLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.machine-pile-shell {
    overflow: hidden;
}

.machine-pile-inner {
    width: 100%;
}

.machine-pile-row {
    margin-top: -18px;
}

.machine-pile-row:first-child {
    margin-top: 0;
}

.machine-mini-box {
    animation: none;
    filter: drop-shadow(0 7px 10px rgba(0, 0, 0, 0.3));
    transform-origin: center;
}

.machine-prize-bounce-custom {
    animation: machine-prize-bounce-custom 720ms
        cubic-bezier(0.18, 0.84, 0.22, 1) both;
    transform-origin: center bottom;
}

.machine-mini-box-bounce-custom {
    animation: machine-mini-box-bounce-custom 720ms ease-out both;
}

@keyframes machine-prize-bounce-custom {
    0% {
        transform: translateY(0) scaleY(1);
    }

    18% {
        transform: translateY(-6px) scaleY(1.04);
    }

    34% {
        transform: translateY(3px) scaleY(0.96);
    }

    50% {
        transform: translateY(-6px) scaleY(1.03);
    }

    68% {
        transform: translateY(1px) scaleY(0.98);
    }

    100% {
        transform: translateY(0) scaleY(1);
    }
}

@keyframes machine-mini-box-bounce-custom {
    0% {
        translate: 0 0;
    }

    20% {
        translate: 0 -9px;
    }

    38% {
        translate: 0 4px;
    }

    55% {
        translate: 0 -6px;
    }

    75% {
        translate: 0 2px;
    }

    100% {
        translate: 0 0;
    }
}
.capsule-cartoon-machine {
    filter: drop-shadow(0 28px 45px rgba(0, 0, 0, 0.38));
}

.animate-clockwise-icon-custom {
    animation: clockwise-icon-rotate-custom 1.25s linear infinite;
    transform-origin: center;
}

.machine-shake-custom {
    animation: machine-shake-custom 520ms ease-in-out both;
}

.rolling-prize-box-custom {
    left: 50%;
    top: 155px;
    animation: rolling-prize-box-custom 1500ms cubic-bezier(0.2, 0.78, 0.18, 1)
        both;
}

.prize-hole-box-custom {
    animation: prize-hole-pop-custom 360ms cubic-bezier(0.2, 0.9, 0.2, 1) both;
}

@keyframes clockwise-icon-rotate-custom {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

@keyframes machine-shake-custom {
    0%,
    100% {
        transform: translateX(0);
    }

    18% {
        transform: translateX(-5px);
    }

    36% {
        transform: translateX(5px);
    }

    54% {
        transform: translateX(-4px);
    }

    72% {
        transform: translateX(4px);
    }
}

@keyframes rolling-prize-box-custom {
    0% {
        opacity: 0;
        transform: translate(-50%, -16px) rotate(0deg) scale(0.82);
    }

    12% {
        opacity: 1;
        transform: translate(-70%, 35px) rotate(85deg) scale(1);
    }

    28% {
        transform: translate(-20%, 95px) rotate(205deg) scale(1);
    }

    44% {
        transform: translate(-65%, 150px) rotate(335deg) scale(1);
    }

    62% {
        transform: translate(-15%, 210px) rotate(485deg) scale(0.98);
    }

    82% {
        opacity: 1;
        transform: translate(30%, 270px) rotate(650deg) scale(0.92);
    }

    100% {
        opacity: 0;
        transform: translate(34%, 315px) rotate(760deg) scale(0.72);
    }
}

@keyframes prize-hole-pop-custom {
    0% {
        opacity: 0;
        transform: translate(-50%, -22px) rotate(-18deg) scale(0.72);
    }

    70% {
        opacity: 1;
        transform: translate(-50%, 3px) rotate(8deg) scale(1.05);
    }

    100% {
        opacity: 1;
        transform: translate(-50%, 0) rotate(0deg) scale(1);
    }
}

.animate-gift-float-custom {
    animation: gift-float 1.15s ease-in-out infinite;
}

.animate-gift-shake-custom {
    animation: gift-shake 700ms ease-in-out both;
}

.animate-pack-item-in-custom {
    animation: pack-item-in 760ms cubic-bezier(0.2, 0.88, 0.22, 1) forwards;
}

.animate-pack-glow-in-custom {
    animation: pack-glow-in 760ms cubic-bezier(0.2, 0.88, 0.22, 1) forwards;
}

.animate-result-caption-in-custom {
    animation: result-caption-in 560ms ease-out forwards;
}

.animate-sunburst-japan-in-custom {
    animation:
        sunburst-japan-in 900ms ease-out forwards,
        sunburst-japan-spin-pulse 10s linear infinite;
}

.animate-gift-sunburst-spin-custom {
    animation: gift-sunburst-spin 14s linear infinite;
}

@keyframes gift-float {
    0%,
    100% {
        transform: translateY(0) scale(1);
    }
    50% {
        transform: translateY(-12px) scale(1.03);
    }
}

@keyframes gift-shake {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    12% {
        transform: translate3d(-10px, 0, 0) scale(1.04);
    }
    24% {
        transform: translate3d(10px, 0, 0) scale(1.06);
    }
    36% {
        transform: translate3d(-12px, 0, 0) scale(1.08);
    }
    48% {
        transform: translate3d(12px, 0, 0) scale(1.1);
    }
    60% {
        transform: translate3d(-10px, 0, 0) scale(1.07);
    }
    72% {
        transform: translate3d(8px, 0, 0) scale(1.05);
    }
    84% {
        transform: translate3d(-4px, 0, 0) scale(1.02);
    }
    100% {
        transform: translate3d(0, 0, 0) scale(1);
    }
}

@keyframes gift-sunburst-spin {
    0% {
        transform: translate(-50%, -50%) rotate(0deg) scale(1);
    }
    25% {
        transform: translate(-50%, -50%) rotate(90deg) scale(1.03);
    }
    50% {
        transform: translate(-50%, -50%) rotate(180deg) scale(0.98);
    }
    75% {
        transform: translate(-50%, -50%) rotate(270deg) scale(1.03);
    }
    100% {
        transform: translate(-50%, -50%) rotate(360deg) scale(1);
    }
}

@keyframes pack-item-in {
    0% {
        opacity: 0;
        transform: perspective(1000px) rotateY(-180deg) scale(0.18);
        filter: blur(12px) brightness(1.3);
    }
    35% {
        opacity: 1;
        transform: perspective(1000px) rotateY(-110deg) scale(0.45);
        filter: blur(6px) brightness(1.18);
    }
    60% {
        opacity: 1;
        transform: perspective(1000px) rotateY(-35deg) scale(1.08);
        filter: blur(0) brightness(1.08);
    }
    78% {
        opacity: 1;
        transform: perspective(1000px) rotateY(8deg) scale(0.96);
        filter: blur(0) brightness(1.02);
    }
    100% {
        opacity: 1;
        transform: perspective(1000px) rotateY(0deg) scale(1);
        filter: blur(0) brightness(1);
    }
}

@keyframes pack-glow-in {
    0% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.2);
    }
    55% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1.18);
    }
    100% {
        opacity: 0.95;
        transform: translate(-50%, -50%) scale(1);
    }
}

@keyframes result-caption-in {
    0% {
        opacity: 0;
        transform: translateY(10px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes sunburst-japan-in {
    0% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.18);
    }
    60% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1.06);
    }
    100% {
        opacity: 0.95;
        transform: translate(-50%, -50%) scale(1);
    }
}

@keyframes sunburst-japan-spin-pulse {
    0% {
        transform: translate(-50%, -50%) scale(1) rotate(0deg);
    }
    25% {
        transform: translate(-50%, -50%) scale(2) rotate(90deg);
    }
    50% {
        transform: translate(-50%, -50%) scale(2) rotate(180deg);
    }
    75% {
        transform: translate(-50%, -50%) scale(2) rotate(270deg);
    }
    100% {
        transform: translate(-50%, -50%) scale(2) rotate(360deg);
    }
}
@keyframes machine-box-float {
    0%,
    100% {
        translate: 0 0;
    }

    50% {
        translate: 0 -8px;
    }
}

@keyframes handle-rotate {
    0% {
        transform: rotate(0deg) scale(1);
    }

    72% {
        transform: rotate(360deg) scale(1.08);
    }

    100% {
        transform: rotate(360deg) scale(1);
    }
}

@keyframes fallen-box {
    0% {
        opacity: 0;
        transform: translate(-50%, -120px) rotate(-18deg) scale(0.72);
    }

    70% {
        opacity: 1;
        transform: translate(-50%, 4px) rotate(8deg) scale(1.05);
    }

    100% {
        opacity: 1;
        transform: translate(-50%, 0) rotate(0deg) scale(1);
    }
}

.animate-hint-hand-custom {
    animation: hint-hand-click-custom 1s ease-in-out infinite both;
    transform-origin: center;
}

@keyframes hint-hand-click-custom {
    0% {
        transform: rotate(-30deg) translateY(0);
    }

    80% {
        transform: rotate(-30deg) translateY(18px);
    }

    100% {
        transform: rotate(-30deg) translateY(0);
    }
}

.animate-hint-text-custom {
    animation: hint-text-wiggle-custom 900ms ease-in-out infinite;
    transform-origin: center;
}

@keyframes hint-text-wiggle-custom {
    0%,
    100% {
        transform: rotate(0deg) scale(1);
    }

    15% {
        transform: rotate(-3deg) scale(1.03);
    }

    30% {
        transform: rotate(3deg) scale(1.04);
    }

    45% {
        transform: rotate(-2deg) scale(1.03);
    }

    60% {
        transform: rotate(2deg) scale(1.02);
    }

    75% {
        transform: rotate(-1deg) scale(1.01);
    }
}

.animate-hint-claim-hand-custom {
    animation: hint-claim-hand-click-custom 1s ease-in-out infinite both;
    transform-origin: center;
}

@keyframes hint-claim-hand-click-custom {
    0% {
        transform: rotate(-20deg) translateY(0);
    }

    80% {
        transform: rotate(-20deg) translateY(14px);
    }

    100% {
        transform: rotate(-20deg) translateY(0);
    }
}

.machine-hint-fade-enter-active {
    transition:
        opacity 360ms ease-out,
        transform 360ms ease-out;
}

.machine-hint-fade-leave-active {
    transition:
        opacity 260ms ease-in,
        transform 260ms ease-in;
}

.machine-hint-fade-enter-from {
    opacity: 0;
}

.machine-hint-fade-enter-to {
    opacity: 1;
}

.machine-hint-fade-leave-from {
    opacity: 1;
}

.machine-hint-fade-leave-to {
    opacity: 0;
}

.prize-hole-box-claim-up-custom {
    animation: prize-hole-box-claim-up-custom 720ms
        cubic-bezier(0.2, 0.9, 0.2, 1) both;
    transform-origin: center;
}

.claimed-box-slot-custom {
    width: 30px;
    height: 30px;
    flex: 0 0 auto;
    animation: claimed-box-slot-pop-custom 420ms cubic-bezier(0.2, 0.9, 0.2, 1)
        both;
}

.claimed-box-row-enter-active,
.claimed-box-row-leave-active {
    transition:
        opacity 260ms ease,
        scale 260ms ease;
}

.claimed-box-row-enter-from,
.claimed-box-row-leave-to {
    opacity: 0;
    scale: 0.4;
}

.claimed-box-row-enter-to,
.claimed-box-row-leave-from {
    opacity: 1;
    scale: 1;
}
.claimed-flying-box-custom {
    animation: claimed-flying-box-custom 560ms ease-out both;
    transform-origin: center;
    will-change: transform, opacity;
}

.claimed-box-slot-empty-custom {
    opacity: 0;
}

.claimed-box-slot-custom img {
    animation: claimed-box-slot-pop-custom 420ms cubic-bezier(0.2, 0.9, 0.2, 1)
        both;
}

@keyframes claimed-flying-box-custom {
    0% {
        opacity: 1;
        transform: translate(-50%, -50%) rotate(0deg) scale(1);
    }

    100% {
        opacity: 0;
        transform: translate(
                calc(-50% + var(--move-x)),
                calc(-50% + var(--move-y))
            )
            rotate(0deg) scale(var(--scale-end));
    }
}

@keyframes claimed-box-slot-pop-custom {
    0% {
        opacity: 0;
        transform: translateY(8px) scale(0.45) rotate(-12deg);
    }

    70% {
        opacity: 1;
        transform: translateY(-3px) scale(1.12) rotate(6deg);
    }

    100% {
        opacity: 1;
        transform: translateY(0) scale(1) rotate(0deg);
    }
}

.claimed-box-slot-merging-custom {
    animation: claimed-box-slot-hide-while-merge-custom 220ms ease-out forwards;
}

.claimed-merge-flying-box-custom {
    animation: claimed-merge-flying-box-custom 520ms ease-in both;
    transform-origin: center;
    will-change: transform, opacity;
}

.claimed-merge-center-box-custom {
    animation: claimed-merge-center-box-custom 640ms
        cubic-bezier(0.2, 0.9, 0.2, 1) both;
    transform-origin: center;
    will-change: transform, opacity;
}

@keyframes claimed-merge-flying-box-custom {
    0% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }

    100% {
        opacity: 0;
        transform: translate(
                calc(-50% + var(--merge-x)),
                calc(-50% + var(--merge-y))
            )
            scale(0.25);
    }
}

@keyframes claimed-merge-center-box-custom {
    0% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.18);
        filter: blur(8px) brightness(1.35);
    }

    45% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1.12);
        filter: blur(0) brightness(1.12);
    }

    70% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(0.96);
        filter: blur(0) brightness(1.04);
    }

    100% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
        filter: blur(0) brightness(1);
    }
}

@keyframes claimed-box-slot-hide-while-merge-custom {
    0% {
        opacity: 1;
        scale: 1;
    }

    100% {
        opacity: 0;
        scale: 0.55;
    }
}

.machine-scene-layer-custom {
    transition:
        opacity 260ms ease,
        scale 260ms ease,
        filter 260ms ease;
}

.machine-scene-hidden-custom {
    opacity: 0;
    scale: 0.96;
    filter: blur(6px);
    pointer-events: none;
}
</style>
