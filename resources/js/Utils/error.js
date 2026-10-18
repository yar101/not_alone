/**
 * Extract human-readable error message from an API error response or Error object.
 * Handles Laravel validation errors (errors object), domain errors (error string),
 * message strings, and fallback messages.
 *
 * @param {any} e
 * @param {string} fallback
 * @returns {string}
 */
export function extractErrorMessage(e, fallback = 'Произошла ошибка') {
    if (!e) return fallback;
    if (typeof e === 'string') return e;

    const data = e.response?.data;
    if (data) {
        if (data.error && typeof data.error === 'string') {
            return data.error;
        }
        if (data.errors && typeof data.errors === 'object') {
            const firstKey = Object.keys(data.errors)[0];
            if (firstKey) {
                const val = data.errors[firstKey];
                if (Array.isArray(val) && val[0]) return String(val[0]);
                if (typeof val === 'string') return val;
            }
        }
        if (data.message && typeof data.message === 'string') {
            return data.message;
        }
    }

    if (e.message && typeof e.message === 'string') {
        return e.message;
    }

    return fallback;
}
