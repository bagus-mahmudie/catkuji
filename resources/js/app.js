import "../css/app.css";
import "./bootstrap";
import "bootstrap-icons/font/bootstrap-icons.css";
import "flowbite";

import { createInertiaApp, router } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, Fragment, h, ref } from "vue";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";
import AuthModalRoot from "@/Components/Auth/AuthModalRoot.vue";
import GlobalPageLoader from "@/Components/GlobalPageLoader.vue";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

const isGlobalPageLoading = ref(false);
let loadingTimeout = null;

router.on("start", (event) => {
    const visit = event.detail.visit;

    const headers = visit.headers ?? {};

    const shouldSkipGlobalLoader =
        headers["X-Skip-Global-Loader"] === "true" ||
        headers["x-skip-global-loader"] === "true";

    if (shouldSkipGlobalLoader) {
        return;
    }

    if (visit.only?.length) {
        return;
    }

    loadingTimeout = window.setTimeout(() => {
        isGlobalPageLoading.value = true;
    }, 150);
});

router.on("finish", () => {
    if (loadingTimeout) {
        window.clearTimeout(loadingTimeout);
        loadingTimeout = null;
    }

    isGlobalPageLoading.value = false;
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({
            render: () =>
                h(Fragment, [
                    h(App, props),
                    h(AuthModalRoot),
                    h(GlobalPageLoader, {
                        show: isGlobalPageLoading.value,
                    }),
                ]),
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
