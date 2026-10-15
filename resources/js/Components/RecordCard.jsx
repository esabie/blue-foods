import Icon from '@/Components/Icon';

const PILLS = {
    green: 'bg-emerald-100 text-emerald-800',
    amber: 'bg-amber-300 text-amber-950',
    soft: 'bg-amber-100 text-amber-800',
    red: 'bg-red-100 text-red-700',
    gray: 'bg-gray-100 text-gray-600',
    blue: 'bg-sky-100 text-sky-800',
};

export function Pill({ tone = 'gray', children }) {
    return <span className={'inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ' + PILLS[tone]}>{children}</span>;
}

export function CardGrid({ children }) {
    return <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{children}</div>;
}

/**
 * A record shown as a card: picture top left, status pills and a date top right, the name and
 * a small ID line, a few detail lines, then actions along the bottom.
 */
export default function RecordCard({ media, pills, date, title, subtitle, children, actions, footer, muted = false }) {
    return (
        <article className="flex flex-col rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
            <div className={'flex flex-1 flex-col p-5 ' + (muted ? 'opacity-60' : '')}>
                <div className="flex items-start justify-between gap-3">
                    <div className="shrink-0">{media}</div>
                    <div className="flex flex-col items-end gap-1.5 text-right">
                        {pills}
                        {date && <span className="text-[11px] font-medium text-sky-800">{date}</span>}
                    </div>
                </div>
                <h3 className="mt-5 text-lg font-semibold leading-snug text-gray-900">{title}</h3>
                {subtitle && <p className="mt-1 text-xs text-gray-400">{subtitle}</p>}
                <div className="mt-4 space-y-3 text-sm text-gray-600">{children}</div>
            </div>
            {actions && <div className="flex flex-wrap gap-x-4 gap-y-1 px-5 pb-5 text-sm font-medium">{actions}</div>}
            {footer}
        </article>
    );
}

export function CardAction({ onClick, tone = 'primary', children }) {
    const tones = { primary: 'text-sky-800 hover:text-sky-950', muted: 'text-gray-500 hover:text-gray-700' };

    return (
        <button type="button" onClick={onClick} className={'hover:underline ' + tones[tone]}>
            {children}
        </button>
    );
}

const INITIALS = {
    slate: 'bg-slate-200 text-slate-600',
    sky: 'bg-sky-100 text-sky-800',
    amber: 'bg-amber-100 text-amber-800',
    emerald: 'bg-emerald-100 text-emerald-800',
};

export function Initials({ name, tone = 'slate' }) {
    const letters = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('');

    return <span className={'flex h-12 w-12 items-center justify-center rounded-xl text-sm font-semibold ' + INITIALS[tone]}>{letters}</span>;
}

export function NotProvided() {
    return <span className="text-gray-400">Not provided</span>;
}

/** A detail line led by a small icon; a link when href is given. */
export function DetailLine({ icon, href, children }) {
    const content = (
        <>
            <Icon name={icon} className="h-4 w-4 shrink-0 text-gray-400" />
            <span className="min-w-0 break-all">{children}</span>
        </>
    );

    return href ? (
        <a href={href} className="flex items-center gap-2 hover:text-sky-800 hover:underline">{content}</a>
    ) : (
        <p className="flex items-center gap-2">{content}</p>
    );
}

/** Two or three small labelled figures side by side inside a card. */
export function MiniStats({ items }) {
    return (
        <dl className={'grid divide-x divide-gray-100 rounded-xl bg-gray-50 py-2.5 ' + (items.length === 3 ? 'grid-cols-3' : 'grid-cols-2')}>
            {items.map((item) => (
                <div key={item.label} className="px-3">
                    <dt className="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{item.label}</dt>
                    <dd className={'mt-0.5 truncate font-semibold tabular-nums ' + (item.tone === 'red' ? 'text-red-700' : item.tone === 'amber' ? 'text-amber-700' : item.tone === 'muted' ? 'text-gray-400' : 'text-gray-900')}>
                        {item.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

/** Search box and filter tabs with counts above a card grid. */
export function ListToolbar({ search, onSearch, placeholder, tabs, tab, onTab }) {
    return (
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div className="flex gap-1 overflow-x-auto rounded-xl bg-gray-100 p-1" role="tablist">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        role="tab"
                        aria-selected={tab === t.key}
                        onClick={() => onTab(t.key)}
                        className={
                            'whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium ' +
                            (tab === t.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800')
                        }
                    >
                        {t.label} <span className={tab === t.key ? 'text-sky-700' : 'text-gray-400'}>{t.count}</span>
                    </button>
                ))}
            </div>
            <label className="relative block md:w-72">
                <span className="sr-only">{placeholder}</span>
                <Icon name="search" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    type="search"
                    value={search}
                    onChange={(e) => onSearch(e.target.value)}
                    placeholder={placeholder}
                    className="block w-full rounded-xl border-gray-200 py-2 pl-9 text-sm shadow-sm focus:border-sky-600 focus:ring-sky-600"
                />
            </label>
        </div>
    );
}

export function NoMatches({ children, onClear }) {
    return (
        <div className="rounded-2xl border border-dashed border-gray-200 px-6 py-12 text-center text-sm text-gray-500">
            {children}{' '}
            {onClear && (
                <button type="button" onClick={onClear} className="font-medium text-sky-800 hover:underline">
                    Clear search
                </button>
            )}
        </div>
    );
}

/** Lower-cased text to match a search against. */
export function matches(query, ...fields) {
    const q = query.trim().toLowerCase();

    return q === '' || fields.some((f) => (f ?? '').toString().toLowerCase().includes(q));
}
