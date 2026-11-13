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
