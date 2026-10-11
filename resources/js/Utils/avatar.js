export const resolveAvatarUrl = (user) => {
    const avatar = user?.avatar || user?.avatar_url;

    if (!avatar) {
        return `https://ui-avatars.com/api/?name=${encodeURIComponent(
            user?.name ?? "User",
        )}&background=101521&color=ffffff`;
    }

    if (avatar.startsWith("http://") || avatar.startsWith("https://")) {
        return avatar;
    }

    if (avatar.startsWith("/storage/http")) {
        return avatar.replace("/storage/", "");
    }

    if (avatar.startsWith("/storage/")) {
        return avatar;
    }

    return `/storage/${avatar}`;
};
