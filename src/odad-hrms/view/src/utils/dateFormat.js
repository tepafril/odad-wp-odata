/**
 * Date/time formatting utilities that respect WordPress settings.
 *
 * WordPress date_format, time_format, and timezone are injected via
 * window.wphrApi by wp_localize_script in wp-hr-core.php.
 */

/**
 * Get the PrimeVue-compatible date format derived from WP's date_format.
 * Used as the default dateFormat prop for DatePicker / HrDatePicker.
 */
export function getPrimeDateFormat() {
    return window.wphrApi?.primeDateFormat || 'yy-mm-dd';
}

/**
 * Get the WordPress timezone string (e.g. 'Asia/Bangkok', 'UTC').
 */
export function getTimezone() {
    return window.wphrApi?.timezone || undefined;
}

/**
 * Format a raw date string (YYYY-MM-DD) for display using WP timezone.
 * Prefer using the *_formatted field from API responses when available.
 */
export function formatDate(dateStr) {
    if (!dateStr) return '';
    // Append T00:00:00 to avoid UTC-shift issues with date-only strings
    const d = new Date(dateStr + (dateStr.length === 10 ? 'T00:00:00' : ''));
    if (isNaN(d)) return dateStr;
    const tz = getTimezone();
    const opts = { year: 'numeric', month: '2-digit', day: '2-digit' };
    if (tz) opts.timeZone = tz;
    return d.toLocaleDateString(undefined, opts);
}

/**
 * Format a raw datetime string (YYYY-MM-DD HH:mm:ss) for display.
 */
export function formatDateTime(datetimeStr) {
    if (!datetimeStr) return '';
    const d = new Date(datetimeStr);
    if (isNaN(d)) return datetimeStr;
    const tz = getTimezone();
    const opts = {};
    if (tz) opts.timeZone = tz;
    return d.toLocaleString(undefined, opts);
}

/**
 * Format a time portion from a datetime string using WP timezone.
 */
export function formatTime(datetimeStr) {
    if (!datetimeStr) return '';
    const d = new Date(datetimeStr);
    if (isNaN(d)) return datetimeStr;
    const tz = getTimezone();
    const opts = { hour: '2-digit', minute: '2-digit' };
    if (tz) opts.timeZone = tz;
    return d.toLocaleTimeString(undefined, opts);
}

/**
 * Convert a JS Date object to YYYY-MM-DD string for API submission.
 * Always sends ISO format regardless of display format.
 */
export function toApiDate(dateObj) {
    if (!dateObj) return null;
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}
