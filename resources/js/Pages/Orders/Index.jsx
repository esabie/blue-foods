import PageShell, { Badge, Card } from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import { formatDateTime, formatGhs } from '@/lib/format';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_TONE = {
    pending_payment: 'amber',
    paid: 'blue',
    packed: 'blue',
    delivered: 'green',
    collected: 'green',
    cancelled: 'gray',
};

function OrderCard({ order, canCancel }) {
    const [busy, setBusy] = useState(false);
    const [cashConfirmed, setCashConfirmed] = useState(false);
    const [showHistory, setShowHistory] = useState(false);
    const post = (name, data = {}) => router.post(route(name, order.id), data, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    const canPack = order.status === 'paid' || (order.status === 'pending_payment' && order.pay_on_delivery);
    const finalLabel = order.delivery_method === 'delivery' ? 'Mark delivered' : 'Mark collected';
    const cancellable = canCancel && !order.paid && ['pending_payment', 'packed'].includes(order.status);

    return (
        <li className="p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono font-semibold">{order.reference}</span>
                        <Badge tone={STATUS_TONE[order.status]}>{order.status_label}</Badge>
                        {!(order.paid && order.status_label === 'Paid') && (
                            <Badge tone={order.paid ? 'green' : 'amber'}>{order.paid ? 'Paid' : order.pay_on_delivery ? 'Pay on delivery' : 'Not paid'}</Badge>
                        )}
                        <Badge>{order.delivery_label}</Badge>
                        {order.needs_refund && <Badge tone="red">Refund needed</Badge>}
                    </div>
                    <p className="mt-1 text-sm text-gray-600">
                        {order.customer_name} · <a href={`tel:${order.phone}`} className="text-sky-700 hover:underline">{order.phone}</a> · placed {formatDateTime(order.created_at)}
                    </p>
                    {order.address && <p className="mt-1 whitespace-pre-line text-sm text-gray-600">{order.address}</p>}
                </div>
                <div className="text-right">
                    <p className="text-lg font-semibold">{formatGhs(order.total_pesewas)}</p>
                    <p className="text-xs text-gray-500">{order.payment_method}{order.delivery_fee_pesewas > 0 && ` · incl. ${formatGhs(order.delivery_fee_pesewas)} delivery`}</p>
                </div>
            </div>

            <ul className="mt-3 rounded-md bg-gray-50 px-3 py-2 text-sm">
                {order.items.map((item, i) => (
                    <li key={i} className="flex justify-between">
                        <span>
                            <span className="font-semibold">{item.quantity}</span> {item.unit} × {item.product}
                        </span>
                        <span className="text-gray-500">{formatGhs(item.total_pesewas)}</span>
                    </li>
                ))}
            </ul>

            <div className="mt-3 flex flex-wrap items-center gap-2">
                {canPack && (
                    <button type="button" disabled={busy} onClick={() => post('orders.pack')} className="rounded-md bg-sky-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-800 disabled:opacity-50">
                        Mark packed
                    </button>
                )}
                {order.status === 'packed' &&
                    (order.paid ? (
                        <button type="button" disabled={busy} onClick={() => post('orders.complete')} className="rounded-md bg-emerald-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50">
                            {finalLabel}
                        </button>
                    ) : (
                        <>
                            <label className="flex items-center gap-2 rounded-md bg-amber-50 px-3 py-1.5 text-sm ring-1 ring-amber-200">
                                <input type="checkbox" className="rounded border-gray-300 text-emerald-700" checked={cashConfirmed} onChange={(e) => setCashConfirmed(e.target.checked)} />
                                Cash of {formatGhs(order.total_pesewas)} received
                            </label>
                            <button
                                type="button"
                                disabled={busy || !cashConfirmed}
                                onClick={() => post('orders.complete', { cash_received: true })}
                                className="rounded-md bg-emerald-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                            >
                                {finalLabel}
                            </button>
                        </>
                    ))}
                {cancellable && (
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => confirm(`Cancel order ${order.reference}? Its stock becomes available again.`) && post('orders.cancel')}
                        className="rounded-md px-3 py-1.5 text-sm font-medium text-red-700 ring-1 ring-red-200 hover:bg-red-50 disabled:opacity-50"
                    >
                        Cancel order
                    </button>
                )}
                {order.status === 'pending_payment' && !order.pay_on_delivery && order.reserved_until && (
                    <span className="text-xs text-gray-500">Stock held until {formatDateTime(order.reserved_until)}</span>
                )}
                <button type="button" onClick={() => setShowHistory(!showHistory)} className="ml-auto text-xs font-medium text-sky-700 hover:underline">
                    {showHistory ? 'Hide history' : `History (${order.history.length})`}
                </button>
            </div>

            {showHistory && (
                <ol className="mt-3 space-y-1 border-l-2 border-sky-100 pl-3 text-xs text-gray-600">
                    {order.history.map((h, i) => (
                        <li key={i}>
                            <span className="font-medium text-gray-800">{h.to}</span> · {h.by} · {formatDateTime(h.at)}
                            {h.note && <span className="text-gray-500"> — {h.note}</span>}
                        </li>
                    ))}
                </ol>
            )}
        </li>
    );
}

export default function Index({ orders, tab, tabs, counts, canCancel }) {
    return (
        <PageShell title="Online orders">
            <nav className="-mb-2 flex gap-1 overflow-x-auto border-b border-gray-200">
                {Object.entries(tabs).map(([key, label]) => (
                    <Link
                        key={key}
                        href={route('orders.index', { tab: key })}
                        className={
                            'flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium ' +
                            (tab === key ? 'border-sky-700 text-sky-800' : 'border-transparent text-gray-500 hover:text-gray-700')
                        }
                    >
                        {label}
                        <span className={'rounded-full px-2 text-xs ' + (tab === key ? 'bg-sky-100' : 'bg-gray-100')}>{counts[key]}</span>
                    </Link>
                ))}
            </nav>

            <Card>
                {orders.data.length === 0 ? (
                    <p className="px-4 py-10 text-center text-sm text-gray-500">No orders here.</p>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {orders.data.map((order) => (
                            <OrderCard key={order.id} order={order} canCancel={canCancel} />
                        ))}
                    </ul>
                )}
            </Card>
            <Pagination paginator={orders} />
        </PageShell>
    );
}
