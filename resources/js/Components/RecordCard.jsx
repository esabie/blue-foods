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

export function Initials({ name }) {
    const letters = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('');

    return <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-200 text-sm font-semibold text-slate-600">{letters}</span>;
}

export function NotProvided() {
    return <span className="text-gray-400">Not provided</span>;
}
