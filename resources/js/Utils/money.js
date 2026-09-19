/**
 * Format a numeric amount into Russian currency format.
 *
 * @param {number|string} val
 * @param {number} [decimals] Optional exact decimal places (e.g. 2 for ledger)
 * @returns {string} e.g. "1 500" or "1 500,50"
 */
export function formatMoney(val, decimals) {
    const num = Number(val) || 0;
    const minDigits = decimals !== undefined ? decimals : (num % 1 !== 0 ? 2 : 0);
    const maxDigits = decimals !== undefined ? decimals : 2;
    return num.toLocaleString('ru-RU', {
        minimumFractionDigits: minDigits,
        maximumFractionDigits: maxDigits,
    });
}
