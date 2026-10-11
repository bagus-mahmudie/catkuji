import { computed, ref } from "vue";
import { router, usePage } from "@inertiajs/vue3";

export function useWishlist(eventIdSource) {
    const page = usePage();
    const processing = ref(false);

    const currentEventId = computed(() => {
        if (typeof eventIdSource === "function") {
            return Number(eventIdSource() ?? 0);
        }

        return Number(eventIdSource?.value ?? eventIdSource ?? 0);
    });

    const wishlistEventIds = computed(() => {
        return (page.props.wishlistEventIds ?? []).map((id) => Number(id));
    });

    const isWishlisted = computed(() => {
        return wishlistEventIds.value.includes(currentEventId.value);
    });

    const toggleWishlist = (event) => {
        event?.preventDefault?.();
        event?.stopPropagation?.();

        if (!page.props.auth?.user) {
            window.location.href = route("login");
            return;
        }

        if (!currentEventId.value || processing.value) {
            return;
        }

        processing.value = true;

        const options = {
            preserveScroll: true,
            preserveState: true,
            only: ["wishlistEventIds"],
            onFinish: () => {
                processing.value = false;
            },
        };

        if (isWishlisted.value) {
            router.delete(
                route("wishlist.destroy", currentEventId.value),
                options,
            );
            return;
        }

        router.post(route("wishlist.store", currentEventId.value), {}, options);
    };

    return {
        isWishlisted,
        wishlistProcessing: processing,
        toggleWishlist,
    };
}
