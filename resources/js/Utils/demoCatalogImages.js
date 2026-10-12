// Only bundled seed artwork is mapped here; uploaded images keep their storage URLs.
const demoImages = new Set([
    "events/promo-kuuga.png",
    "events/pika-parade.png",
    "events/honor-of-kings.png",
    "events/voucher-pack.png",
    "events/xiaomi-15t.png",
    "events/galaxy-s25.png",
    "events/iphone-17-pro.png",
    "events/redmi-note.png",
    "events/tcg-pika.png",
    "events/tcg-charizard.png",
    "events/tcg-brothers.png",
    "events/fate-grand-order.png",
    "events/demon-thunder.png",
    "events/mlbb-diamonds.png",
    "events/yakuza-steam.png",
    "events/hok-pack.png",
    "prizes/thumbnail-prize.png"
]);

export function getDemoCatalogImage(path) {
    const relative = path.replace(/^\/?storage\//, "");
    return demoImages.has(relative) ? `/images/demo/${relative}` : null;
}
