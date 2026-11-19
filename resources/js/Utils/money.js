/**
 * Format a numeric amount into Russian currency format with exactly 2 decimals.
 *
 * @param {number|string} val
 * @returns {string} e.g. "1 500,00"
 */
export function formatMoney(val) {
    const num = Number(val) || 0;
    return num.toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
