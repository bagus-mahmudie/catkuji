import { getDemoCatalogImage } from "./demoCatalogImages.js";

export function getPrizeImage(path) {
    if (!path) {
        return null;
    }

    const imagePath = String(path).trim();

    if (!imagePath) {
        return null;
    }

    if (
        imagePath.startsWith("http://") ||
        imagePath.startsWith("https://") ||
        imagePath.startsWith("data:")
    ) {
        return imagePath;
    }

    const demoImage = getDemoCatalogImage(imagePath);
    if (demoImage) {
        return demoImage;
    }

    if (imagePath.startsWith("/storage/")) {
        return imagePath;
    }

    if (imagePath.startsWith("storage/")) {
        return `/${imagePath}`;
    }

    if (imagePath.startsWith("/images/")) {
        return imagePath;
    }

    if (imagePath.startsWith("images/")) {
        return `/${imagePath}`;
    }

    if (imagePath.startsWith("/")) {
        return imagePath;
    }

    return `/storage/${imagePath}`;
}
