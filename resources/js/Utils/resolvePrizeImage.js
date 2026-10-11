import { getPrizeImage } from "@/Utils/prizeImages";

export function resolvePrizeImagePath(path) {
    return getPrizeImage(path) || "/images/placeholder-prize.png";
}
