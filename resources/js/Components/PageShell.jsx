import Icon from '@/Components/Icon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function PageShell({ title, actions, children }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold tracking-tight text-gray-900 sm:text-2xl">{title}</h1>
                    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                </div>
            }
        >
            <Head title={title} />
            <div className="pb-8 pt-3">
                <div className="mx-auto max-w-7xl space-y-4 px-2 sm:px-6 lg:px-8">{children}</div>
            </div>
        </AuthenticatedLayout>
    );
}

export const SURFACE = 'bg-white ring-1 ring-gray-200 sm:rounded-xl';

export function Card({ title, children, className = '' }) {
    return (
        <section className={'overflow-hidden ' + SURFACE + ' ' + className}>
            {title && (
                <h3 className="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-900">
                    {title}
                </h3>
            )}
            {children}
        </section>
    );
}

const ACCENTS = {
    sky: 'bg-sky-50 text-sky-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    violet: 'bg-violet-50 text-violet-600',
    indigo: 'bg-indigo-50 text-indigo-600',
    amber: 'bg-amber-50 text-amber-600',
    teal: 'bg-teal-50 text-teal-600',
    rose: 'bg-rose-50 text-rose-600',
    gray: 'bg-gray-100 text-gray-500',
};

/**
 * A headline figure: label and icon on top, the value, then an optional hint line or progress bar.
 */
export function Stat({ label, value, tone = 'default', icon, accent = 'sky', hint, progress, children }) {
    const tones = {
        default: 'text-gray-900',
        warn: 'text-amber-600',
        good: 'text-emerald-700',
    };

    return (
        <div className="flex flex-col rounded-xl bg-white px-3.5 py-3 ring-1 ring-gray-200">
            <div className="flex items-start justify-between gap-2">
                <span className="pt-0.5 text-[11px] font-semibold uppercase tracking-wider text-gray-500">{label}</span>
                {icon && (
                    <span className={'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg ' + ACCENTS[accent]}>
                        <Icon name={icon} />
                    </span>
                )}
            </div>
            <div className="mt-auto pt-2">
                <div className={'text-lg font-semibold tabular-nums tracking-tight sm:text-xl ' + tones[tone]}>{value}</div>
                {hint && <div className="mt-0.5 text-xs text-gray-500">{hint}</div>}
                {progress !== undefined && (
                    <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <div className="h-full rounded-full bg-teal-500" style={{ width: `${Math.min(100, Math.max(0, progress ?? 0))}%` }} />
                    </div>
                )}
                {children}
            </div>
        </div>
    );
}

/** A coloured dot before a short status, e.g. "● All above reorder level". */
export function StatusDot({ tone = 'green', children }) {
    const dots = { green: 'bg-emerald-500', amber: 'bg-amber-500', red: 'bg-red-500', gray: 'bg-gray-400' };

    return (
        <span className="inline-flex items-center gap-1.5">
            <span className={'h-1.5 w-1.5 rounded-full ' + dots[tone]} />
            {children}
        </span>
    );
}

/** Change against the previous period: "↑ 12.5% vs previous period" in green, or red when down. */
export function Change({ value }) {
    if (value === null || value === undefined) {
        return <span className="text-gray-400">No previous period to compare</span>;
    }

    return (
        <span>
            <span className={'font-medium ' + (value >= 0 ? 'text-emerald-700' : 'text-red-700')}>
                {value >= 0 ? '↑' : '↓'} {Math.abs(value)}%
            </span>{' '}
            vs previous period
        </span>
    );
}

export function Table({ children }) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 text-sm">{children}</table>
        </div>
    );
}

export function Th({ children, className = '' }) {
    return (
        <th className={'whitespace-nowrap px-4 py-2.5 text-left text-xs font-medium text-gray-500 ' + className}>
            {children}
        </th>
    );
}

export function Td({ children, className = '' }) {
    return <td className={'whitespace-nowrap px-4 py-2.5 tabular-nums text-gray-800 ' + className}>{children}</td>;
}

export function Badge({ children, tone = 'gray' }) {
    const tones = {
        gray: 'bg-gray-50 text-gray-700 ring-gray-500/15',
        green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        amber: 'bg-amber-50 text-amber-800 ring-amber-600/20',
        red: 'bg-red-50 text-red-700 ring-red-600/20',
        blue: 'bg-sky-50 text-sky-800 ring-sky-600/20',
    };

    return (
        <span className={'inline-flex rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset ' + tones[tone]}>
            {children}
        </span>
    );
}
