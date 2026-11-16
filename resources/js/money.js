/**
 * Prices arrive already in the client's currency (the server converts and
 * rounds them), so this only formats: Rp 1.000.000 / ¥1,000,000 / $1,000.
 */
const SYMBOL = { IDR: 'Rp', JPY: '¥', USD: '$', THB: '฿', TWD: 'NT$' };
const NUMBER_LOCALE = { IDR: 'id-ID', JPY: 'ja-JP', USD: 'en-US', THB: 'en-US', TWD: 'en-US' };

export function formatMoney(value, currency) {
    const number = new Intl.NumberFormat(NUMBER_LOCALE[currency] ?? 'en-US', { maximumFractionDigits: 0 }).format(Number(value) || 0);

    if (currency === 'IDR') return `Rp ${number}`;

    return SYMBOL[currency] ? `${SYMBOL[currency]}${number}` : `${currency} ${number}`;
}
