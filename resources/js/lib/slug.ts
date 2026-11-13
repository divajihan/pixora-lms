/**
 * "Belajar Desain Dasar!" → "belajar-desain-dasar" (mirrors Laravel Str::slug for ASCII text).
 */
export function slugify(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/&/g, ' dan ')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 200);
}
