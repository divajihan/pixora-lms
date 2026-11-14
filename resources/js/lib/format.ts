const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

/**
 * Formats an integer rupiah amount, e.g. 199000 → "Rp 199.000".
 */
export function formatRupiah(amount: number): string {
    return rupiah.format(amount).replace(/ /g, ' ');
}

/**
 * Human duration in Indonesian, e.g. 3725 → "1 j 2 mnt", 95 → "1 mnt 35 dtk".
 */
export function formatDuration(seconds: number | null | undefined): string {
    if (!seconds || seconds <= 0) {
        return '–';
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = seconds % 60;

    if (hours > 0) {
        return minutes > 0 ? `${hours} j ${minutes} mnt` : `${hours} j`;
    }

    if (minutes > 0) {
        return rest > 0 && minutes < 10
            ? `${minutes} mnt ${rest} dtk`
            : `${minutes} mnt`;
    }

    return `${rest} dtk`;
}

const dateTime = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Asia/Jakarta',
});

/**
 * "20 Nov 2026 19.00" (Asia/Jakarta). Accepts ISO or "YYYY-MM-DDTHH:mm" (local).
 */
export function formatDateTime(value: string): string {
    return dateTime.format(new Date(value));
}

/**
 * "15–27 November 2026" / "28 Nov – 3 Des 2026" for Y-m-d dates.
 */
export function formatDateRange(start: string, end: string): string {
    const a = new Date(`${start}T00:00:00`);
    const b = new Date(`${end}T00:00:00`);
    const sameMonth =
        a.getMonth() === b.getMonth() && a.getFullYear() === b.getFullYear();

    if (sameMonth) {
        return `${a.getDate()}–${new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(b)}`;
    }

    const short = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
    });

    return `${short.format(a)} – ${new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(b)}`;
}

/**
 * "Senin, 16 November 2026 00.00" (Asia/Jakarta) — used for unlock dates.
 */
export function formatUnlock(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(iso));
}

const longDate = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
});

/**
 * Formats an ISO date as an Indonesian long date, e.g. "13 November 2026".
 */
export function formatDate(iso: string): string {
    return longDate.format(new Date(iso));
}
