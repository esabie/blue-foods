import PageShell, { StatusDot } from '@/Components/PageShell';
import { formatDateTime, formatGhs, todayInGhana } from '@/lib/format';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export const TABS = [
    ['Overview', 'dashboards.overview', ['management']],
    ['Stock', 'dashboards.stock', ['management', 'warehouse']],
    ['Marketing team', 'dashboards.team', ['management']],
    ['Sales and revenue', 'dashboards.sales', ['management']],
];

export const COLORS = {
    team: '#0369a1',
    online: '#f59e0b',
    unassigned: '#94a3b8',
    withTeam: '#0369a1',
    shop: '#f59e0b',
    units: '#10b981',
    profit: '#10b981',
};

/** Shared recharts styling: light grid, no axis lines, muted labels. */
export const GRID = { stroke: '#eef0f3' };
export const AXIS = { tick: { fontSize: 12, fill: '#6b7280' }, axisLine: false, tickLine: false };
export const CURSOR = { fill: '#f3f4f6' };
export const LEGEND = { wrapperStyle: { fontSize: 12, color: '#4b5563' }, iconType: 'circle', iconSize: 8 };

/** Compact cedi amounts for chart axes, e.g. GHS 12.5k. */
export function shortGhs(pesewas) {
    const cedis = pesewas / 100;
    if (Math.abs(cedis) >= 1_000_000) return `${(cedis / 1_000_000).toFixed(1)}m`;
    if (Math.abs(cedis) >= 1_000) return `${(cedis / 1_000).toFixed(cedis >= 10_000 ? 0 : 1)}k`;
    return String(Math.round(cedis));
}

export function percent(value) {
    return value === null || value === undefined ? '—' : `${value}%`;
}

export function number(value) {
    return (value ?? 0).toLocaleString('en-GH');
}

/** "25.8%" of a total, or an em dash when the total is zero. */
export function share(part, whole) {
    return whole > 0 ? `${Math.round((part / whole) * 1000) / 10}%` : '—';
}

export function ReorderStatus({ count }) {
    return count > 0 ? (
        <StatusDot tone="amber">
            {count} {count === 1 ? 'product' : 'products'} below reorder level
        </StatusDot>
    ) : (
        <StatusDot tone="green">All above reorder level</StatusDot>
    );
}

/** Gross profit in cedis, or an em dash when none of the sales has a cost. */
export function profitGhs(pesewas) {
    return pesewas === null || pesewas === undefined ? '—' : formatGhs(pesewas);
}

/** Margin under a Gross profit figure, flagging sales left out because their product has no cost. */
export function ProfitHint({ profit, margin, uncosted }) {
    if (profit === null) {
        return <StatusDot tone="amber">Set product costs to see profit</StatusDot>;
    }
    if (uncosted > 0) {
        return (
            <StatusDot tone="amber">
                {percent(margin)} margin · {number(uncosted)} {uncosted === 1 ? 'unit' : 'units'} without a cost left out
            </StatusDot>
        );
    }

    return margin === null ? 'No sales in this period' : `${percent(margin)} margin`;
}

export function OnlineShare({ online, total }) {
    return online > 0 ? `${number(online)} in online shop · ${share(online, total)}` : 'None in online shop';
}

function shiftDays(iso, days) {
    const d = new Date(`${iso}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
}

function presets() {
    const today = todayInGhana();
    const [y, m] = today.split('-').map(Number);
    const monthStart = (year, month) => new Date(Date.UTC(year, month - 1, 1)).toISOString().slice(0, 10);
    const lastMonthEnd = shiftDays(monthStart(y, m), -1);

    return [
        ['Today', today, today],
        ['Last 7 days', shiftDays(today, -6), today],
        ['This month', monthStart(y, m), today],
        ['Last month', monthStart(...lastMonthEnd.split('-').map(Number).slice(0, 2)), lastMonthEnd],
        ['Last 3 months', monthStart(y, m - 2), today],
        ['This year', `${y}-01-01`, today],
        ['Last 12 months', monthStart(y, m - 11), today],
    ];
}

function ProductPicker({ products, selected, onChange }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const close = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const toggle = (id) => onChange(selected.includes(id) ? selected.filter((s) => s !== id) : [...selected, id]);
    const label =
        selected.length === 0
            ? 'All products'
            : selected.length === 1
              ? products.find((p) => p.id === selected[0])?.name
              : `${selected.length} products`;

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="flex w-full min-w-44 items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm hover:bg-gray-50"
            >
                <span className="truncate">{label}</span>
                <svg className="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M5.3 7.3a1 1 0 011.4 0L10 10.6l3.3-3.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 010-1.4z" clipRule="evenodd" /></svg>
            </button>
            {open && (
                <div className="absolute right-0 z-20 mt-1 max-h-72 w-64 overflow-y-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5">
                    <button type="button" onClick={() => onChange([])} className="block w-full px-3 py-2 text-left text-sm text-sky-700 hover:bg-gray-50">
                        All products
                    </button>
                    {products.map((p) => (
                        <label key={p.id} className="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-gray-50">
                            <input type="checkbox" className="rounded border-gray-300 text-sky-700" checked={selected.includes(p.id)} onChange={() => toggle(p.id)} />
                            {p.name}
                        </label>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * D5: the period and product filter. Every figure on the page reloads from the server with it.
 */
export function FilterBar({ filters, products, extra = {}, showProducts = true, children }) {
    const current = route().current();
    const params = route().params;
    const apply = (next) => {
        const merged = { ...filters, ...extra, ...next };
        router.get(route(current, params), { from: merged.from, to: merged.to, products: merged.products, ...extra, ...next }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };
    const active = presets().find(([, from, to]) => from === filters.from && to === filters.to)?.[0];

    return (
        <div className="flex flex-wrap items-end justify-between gap-3">
            <div className="inline-flex flex-wrap gap-0.5 self-start rounded-lg bg-gray-200/60 p-0.5">
                {presets().map(([label, from, to]) => (
                    <button
                        key={label}
                        type="button"
                        onClick={() => apply({ from, to })}
                        className={
                            'rounded-md px-2.5 py-1 text-xs font-medium transition ' +
                            (active === label ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900')
                        }
                    >
                        {label}
                    </button>
                ))}
            </div>
            <div className="flex flex-wrap items-end gap-2">
                <label className="text-xs text-gray-500">
                    From
                    <input type="date" value={filters.from} max={filters.to} onChange={(e) => e.target.value && apply({ from: e.target.value })} className="mt-0.5 block rounded-md border-gray-300 py-1.5 text-sm focus:border-sky-600 focus:ring-sky-600" />
                </label>
                <label className="text-xs text-gray-500">
                    To
                    <input type="date" value={filters.to} min={filters.from} onChange={(e) => e.target.value && apply({ to: e.target.value })} className="mt-0.5 block rounded-md border-gray-300 py-1.5 text-sm focus:border-sky-600 focus:ring-sky-600" />
                </label>
                {showProducts && <ProductPicker products={products} selected={filters.products} onChange={(ids) => apply({ products: ids })} />}
                {children}
            </div>
        </div>
    );
}

export default function DashboardShell({ title, filters, products, generatedAt, extra, children }) {
    const { auth } = usePage().props;
    const tabs = TABS.filter(([, , roles]) => roles.includes(auth.user.role));
    const query = { from: filters.from, to: filters.to, products: filters.products };

    return (
        <PageShell title={title}>
            {tabs.length > 1 && (
                <nav className="-mt-1 flex gap-1 overflow-x-auto border-b border-gray-200">
                    {tabs.map(([label, name]) => (
                        <Link
                            key={name}
                            href={route(name, query)}
                            preserveScroll
                            className={
                                '-mb-px whitespace-nowrap border-b-2 px-3 py-1.5 text-sm font-medium ' +
                                (route().current(name) ? 'border-sky-700 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700')
                            }
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
            )}
            <FilterBar filters={filters} products={products} extra={extra} />
            {children}
            <p className="text-right text-xs text-gray-400">Figures as of {formatDateTime(generatedAt)}, Ghana time. Refresh the page for the latest.</p>
        </PageShell>
    );
}

export function ChartCard({ title, children, action, className = '' }) {
    return (
        <section className={'min-w-0 rounded-xl bg-white ring-1 ring-gray-200 ' + className}>
            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
                <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
                {action}
            </div>
            <div className="p-3">{children}</div>
        </section>
    );
}

export function MoneyTooltip({ active, payload, label, money = true }) {
    if (!active || !payload?.length) return null;

    return (
        <div className="rounded-lg bg-white px-3 py-2 text-xs shadow-md ring-1 ring-gray-200">
            <p className="mb-1 font-semibold text-gray-800">{payload[0]?.payload?.label ?? label}</p>
            {payload.filter((p) => p.value !== null && p.value !== undefined).map((p) => (
                <p key={p.dataKey} className="flex items-center gap-2">
                    <span className="inline-block h-2 w-2 rounded-full" style={{ background: p.color }} />
                    <span className="text-gray-600">{p.name}:</span>
                    <span className="font-medium">{money ? formatGhs(p.value) : number(p.value)}</span>
                </p>
            ))}
        </div>
    );
}

export function Empty({ children = 'No data for this period.' }) {
    return <p className="py-10 text-center text-sm text-gray-500">{children}</p>;
}
