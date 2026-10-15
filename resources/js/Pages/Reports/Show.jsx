import { FilterBar, number, percent } from '@/Components/Dashboard';
import PageShell, { Card, Table, Td, Th } from '@/Components/PageShell';
import { formatDateTime, formatGhs } from '@/lib/format';
import { Link } from '@inertiajs/react';

function cell(value, format) {
    if (value === null || value === undefined) return '—';
    switch (format) {
        case 'money':
            return formatGhs(value);
        case 'int':
            return number(value);
        case 'percent':
            return percent(value);
        case 'datetime':
            return formatDateTime(value);
        default:
            return value;
    }
}

const alignRight = (format) => ['money', 'int', 'percent'].includes(format);

export default function Show({ report, types, grouping, filters, products }) {
    const isCash = report.type === 'cash-hand-ins';
    const hasProfit = report.columns.some((c) => c.key === 'gross_profit');
    const query = { from: filters.from, to: filters.to, products: isCash ? undefined : filters.products };
    const groupingParam = report.type === 'sales-summary' ? { grouping } : {};

    return (
        <PageShell
            title="Reports"
            actions={
                <a href={route('reports.export', { type: report.type, ...query, ...groupingParam })} className="inline-flex items-center gap-2 rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-800">
                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" /><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" /></svg>
                    Export to spreadsheet (CSV)
                </a>
            }
        >
            <nav className="-mb-2 flex gap-1 overflow-x-auto border-b border-gray-200">
                {Object.entries(types).map(([type, label]) => (
                    <Link
                        key={type}
                        href={route('reports.show', { type, ...query })}
                        preserveScroll
                        className={
                            'whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium ' +
                            (report.type === type ? 'border-sky-700 text-sky-800' : 'border-transparent text-gray-500 hover:text-gray-700')
                        }
                    >
                        {label}
                    </Link>
                ))}
            </nav>

            <FilterBar filters={filters} products={products} extra={groupingParam} showProducts={!isCash}>
                {report.type === 'sales-summary' && (
                    <div className="flex rounded-md shadow-sm" role="group" aria-label="Group by">
                        {['day', 'week', 'month'].map((g, i) => (
                            <Link
                                key={g}
                                href={route('reports.show', { type: report.type, ...query, grouping: g })}
                                preserveScroll
                                preserveState
                                className={
                                    'border border-gray-300 px-3 py-2 text-sm capitalize ' +
                                    (i === 0 ? 'rounded-l-md ' : '-ml-px ') +
                                    (i === 2 ? 'rounded-r-md ' : '') +
                                    (grouping === g ? 'z-10 border-sky-700 bg-sky-700 text-white' : 'bg-white text-gray-700 hover:bg-gray-50')
                                }
                            >
                                By {g}
                            </Link>
                        ))}
                    </div>
                )}
            </FilterBar>

            <Card title={`${report.title} · ${filters.from} to ${filters.to}`}>
                {report.rows.length === 0 ? (
                    <p className="px-4 py-10 text-center text-sm text-gray-500">No rows for this period.</p>
                ) : (
                    <Table>
                        <thead className="bg-gray-50">
                            <tr>
                                {report.columns.map((c) => (
                                    <Th key={c.key} className={alignRight(c.format) ? 'text-right' : ''}>
                                        {c.label}
                                    </Th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {report.rows.map((row, i) => (
                                <tr key={row.id ?? i}>
                                    {report.columns.map((c) => (
                                        <Td key={c.key} className={alignRight(c.format) ? 'text-right tabular-nums' : 'whitespace-normal'}>
                                            {cell(row[c.key], c.format)}
                                        </Td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                        {report.totals && (
                            <tfoot className="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                                <tr>
                                    {report.columns.map((c) => (
                                        <Td key={c.key} className={alignRight(c.format) ? 'text-right tabular-nums' : ''}>
                                            {cell(report.totals[c.key], c.format)}
                                        </Td>
                                    ))}
                                </tr>
                            </tfoot>
                        )}
                    </Table>
                )}
            </Card>
            <p className="text-xs text-gray-500">
                {isCash
                    ? 'Every hand-in received in the period. A cancelled hand-in stays listed, with its amount under Cancelled instead of Amount, so the Amount total is the money actually received.'
                    : 'Totals use the same definitions as the dashboards, so they match for the same period and products.'}{' '}
                The export contains exactly these rows and totals.
            </p>
            {hasProfit && (
                <p className="text-xs text-gray-500">
                    Gross profit is revenue minus cost of goods. Each sale is costed at the product's cost on the day it was sold;
                    delivery fees and payment charges are not included. Units of a product with no cost yet are left out of cost
                    and profit and counted under Units without cost. Set costs on the Products page.
                </p>
            )}
        </PageShell>
    );
}
