const TIMEZONE = 'Africa/Accra';

export function formatGhs(pesewas) {
    if (pesewas === null || pesewas === undefined) {
        return '—';
    }

    const sign = pesewas < 0 ? '-' : '';
    const abs = Math.abs(pesewas);
    const cedis = Math.floor(abs / 100).toLocaleString('en-GH');
    const fraction = String(abs % 100).padStart(2, '0');

    return `${sign}GHS ${cedis}.${fraction}`;
}

/**
 * Parse a cedi string like "12.5" into integer pesewas without floating point.
 * Returns null when the input is not a valid amount.
 */
export function toPesewas(cedis) {
    const match = String(cedis ?? '').trim().match(/^(\d{1,9})(?:\.(\d{1,2}))?$/);

    if (!match) {
        return null;
    }

    return Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'));
}

export function toCedisInput(pesewas) {
    if (pesewas === null || pesewas === undefined) {
        return '';
    }

    return `${Math.floor(pesewas / 100)}.${String(pesewas % 100).padStart(2, '0')}`;
}

export function formatDateTime(iso) {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        timeZone: TIMEZONE,
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

export function formatDate(iso) {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        timeZone: TIMEZONE,
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(iso));
}

/** "just now", "5 min ago", "3 h ago", "yesterday", then the date. */
export function timeAgo(iso, now = Date.now()) {
    const minutes = Math.floor((now - new Date(iso).getTime()) / 60000);

    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes} min ago`;
    if (minutes < 24 * 60) return `${Math.floor(minutes / 60)} h ago`;
    if (minutes < 48 * 60) return 'yesterday';

    return formatDate(iso);
}

export function todayInGhana() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: TIMEZONE }).format(new Date());
}
