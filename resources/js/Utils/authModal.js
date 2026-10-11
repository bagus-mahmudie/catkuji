export const AUTH_MODAL_OPEN_EVENT = "auth:open";
export const AUTH_MODAL_CLOSE_EVENT = "auth:close";

export const openAuthModal = (mode = "login", options = {}) => {
    if (typeof window === "undefined") return;

    window.dispatchEvent(
        new CustomEvent(AUTH_MODAL_OPEN_EVENT, {
            detail: {
                mode,
                ...options,
            },
        }),
    );
};

export const closeAuthModal = () => {
    if (typeof window === "undefined") return;

    window.dispatchEvent(new Event(AUTH_MODAL_CLOSE_EVENT));
};
