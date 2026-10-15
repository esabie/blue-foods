import Field from '@/Components/Field';
import FormModal from '@/Components/FormModal';
import PageShell, { Card, Stat, StatusDot } from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { formatDate, formatDateTime, formatGhs } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function MemberHome({ stock, today, month_revenue_pesewas, cash, pendingReturns, recentSales, recentHandIns }) {
    const [recording, setRecording] = useState(false);
    const [returning, setReturning] = useState(null);
    const sellable = stock.filter((s) => s.available > 0);

    if (recording) {
        return <RecordSale stock={sellable} onDone={() => setRecording(false)} />;
    }

    return (
        <PageShell title="My stock">
            <PrimaryButton
                className="w-full justify-center py-4 text-sm"
                disabled={sellable.length === 0}
                onClick={() => setRecording(true)}
            >
                Record a sale
            </PrimaryButton>

            <div className="grid grid-cols-2 gap-2">
                <div className="col-span-2">
                    <Stat
                        label="To hand in"
                        value={formatGhs(Math.max(0, cash.outstanding_pesewas))}
                        tone={cash.overdue ? 'warn' : 'default'}
                        icon="banknotes"
                        accent={cash.outstanding_pesewas > 0 ? 'amber' : 'gray'}
                        hint={
                            cash.owing_since ? (
                                <StatusDot tone={cash.overdue ? 'amber' : 'gray'}>
                                    From sales since {formatDate(cash.owing_since)}
                                    {cash.overdue && ' · please hand it in'}
                                </StatusDot>
                            ) : (
                                <StatusDot tone="green">All handed in</StatusDot>
                            )
                        }
                    />
                </div>
                <Stat label="Today" value={formatGhs(today.revenue_pesewas)} icon="clock" accent="teal" hint={`${today.sales} ${today.sales === 1 ? 'sale' : 'sales'}`} />
                <Stat label="This month" value={formatGhs(month_revenue_pesewas)} icon="calendar" accent="sky" />
            </div>

            <Card title="Stock I hold">
                {stock.length === 0 && <p className="p-4 text-sm text-gray-500">You are not holding any stock.</p>}
                <ul className="divide-y divide-gray-100">
                    {stock.map((s) => (
                        <li key={s.product_id} className="flex items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <div className="font-medium text-gray-900">{s.name}</div>
                                <div className="text-xs text-gray-500">
                                    {formatGhs(s.price_pesewas)} / {s.unit}
                                    {s.pending_return > 0 && ` · ${s.pending_return} awaiting return`}
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="text-right">
                                    <div className="text-xl font-semibold">{s.available}</div>
                                    <div className="text-xs text-gray-500">{s.unit}</div>
                                </div>
                                {s.available > 0 && (
                                    <button className="text-sm text-sky-700 hover:underline" onClick={() => setReturning(s)}>
                                        Return
                                    </button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            </Card>

            {pendingReturns.length > 0 && (
                <Card title="Returns waiting for the warehouse">
                    <ul className="divide-y divide-gray-100 text-sm">
                        {pendingReturns.map((r) => (
                            <li key={r.id} className="flex justify-between px-4 py-3">
                                <span>{r.quantity} {r.unit} of {r.product}</span>
                                <span className="text-gray-500">{formatDateTime(r.requested_at)}</span>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            <Card title="Recent sales">
                {recentSales.length === 0 && <p className="p-4 text-sm text-gray-500">No sales yet.</p>}
                <ul className="divide-y divide-gray-100 text-sm">
                    {recentSales.map((s) => (
                        <li key={s.id} className={'flex justify-between px-4 py-3 ' + (s.reversed ? 'text-gray-400' : '')}>
                            <div>
                                <div>{s.quantity} × {s.product}</div>
                                <div className="text-xs text-gray-500">
                                    {formatDateTime(s.created_at)}
                                    {s.reversed && ' · reversed'}
                                </div>
                            </div>
                            <div className={'font-medium ' + (s.reversed ? 'line-through' : '')}>{formatGhs(s.total_pesewas)}</div>
                        </li>
                    ))}
                </ul>
                <div className="border-t border-gray-100 px-4 py-3 text-sm">
                    <Link href={route('member.sales')} className="text-sky-700 hover:underline">All my sales</Link>
                </div>
            </Card>

            <Card title="Cash I handed in">
                {recentHandIns.length === 0 && <p className="p-4 text-sm text-gray-500">Nothing handed in yet.</p>}
                <ul className="divide-y divide-gray-100 text-sm">
                    {recentHandIns.map((h) => (
                        <li key={h.id} className={'flex justify-between gap-3 px-4 py-3 ' + (h.cancelled ? 'text-gray-400' : '')}>
                            <div>
                                <div>{h.method_label} to {h.received_by}</div>
                                <div className="text-xs text-gray-500">
                                    {formatDateTime(h.created_at)}
                                    {h.cancelled && ` · cancelled: ${h.cancel_reason}`}
                                </div>
                            </div>
                            <div className={'font-medium ' + (h.cancelled ? 'line-through' : '')}>{formatGhs(h.amount_pesewas)}</div>
                        </li>
                    ))}
                </ul>
            </Card>

            {returning && <ReturnModal item={returning} onClose={() => setReturning(null)} />}
        </PageShell>
    );
}

function RecordSale({ stock, onDone }) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({ product_id: stock[0]?.product_id ?? '', quantity: '1', customer_note: '' });
    const item = stock.find((s) => String(s.product_id) === String(form.data.product_id));
    const qty = parseInt(form.data.quantity, 10) || 0;
    const valid = item && qty >= 1 && qty <= item.available;

    const save = () => {
        form.post(route('member.sales.store'), {
            onSuccess: onDone,
            onError: () => setConfirming(false),
        });
    };

    if (confirming && item) {
        return (
            <PageShell title="Confirm sale">
                <Card>
                    <dl className="divide-y divide-gray-100 text-base">
                        <Row label="Product" value={item.name} />
                        <Row label="Quantity" value={`${qty} ${item.unit}`} />
                        <Row label="Unit price" value={formatGhs(item.price_pesewas)} />
                        <Row label="Total" value={<span className="text-2xl font-bold">{formatGhs(qty * item.price_pesewas)}</span>} />
                        {form.data.customer_note && <Row label="Customer" value={form.data.customer_note} />}
                    </dl>
                </Card>
                <PrimaryButton className="w-full justify-center py-4 text-sm" disabled={form.processing} onClick={save}>
                    Confirm sale
                </PrimaryButton>
                <SecondaryButton className="w-full justify-center py-3" onClick={() => setConfirming(false)}>
                    Back
                </SecondaryButton>
            </PageShell>
        );
    }

    return (
        <PageShell title="Record a sale">
            <Card>
                <form
                    className="space-y-4 p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (valid) setConfirming(true);
                    }}
                >
                    <Field label="Product" htmlFor="sale_product" error={form.errors.product_id}>
                        <SelectInput id="sale_product" className="block w-full text-base" value={form.data.product_id}
                            onChange={(e) => form.setData('product_id', e.target.value)}>
                            {stock.map((s) => (
                                <option key={s.product_id} value={s.product_id}>
                                    {s.name} ({s.available} available)
                                </option>
                            ))}
                        </SelectInput>
                    </Field>

                    <Field label="Quantity" htmlFor="sale_qty" error={form.errors.quantity}
                        hint={item ? `Up to ${item.available} ${item.unit} · ${formatGhs(item.price_pesewas)} each` : null}>
                        <div className="flex items-center gap-2">
                            <SecondaryButton className="h-12 w-12 justify-center text-lg" onClick={() => form.setData('quantity', String(Math.max(1, qty - 1)))}>
                                −
                            </SecondaryButton>
                            <TextInput id="sale_qty" type="number" inputMode="numeric" min="1" max={item?.available}
                                className="block h-12 w-full text-center text-lg" value={form.data.quantity}
                                onChange={(e) => form.setData('quantity', e.target.value)} />
                            <SecondaryButton className="h-12 w-12 justify-center text-lg"
                                onClick={() => form.setData('quantity', String(Math.min(item?.available ?? 1, qty + 1)))}>
                                +
                            </SecondaryButton>
                        </div>
                    </Field>

                    <Field label="Customer name or phone (optional)" htmlFor="customer_note" error={form.errors.customer_note}>
                        <TextInput id="customer_note" className="block w-full" value={form.data.customer_note}
                            onChange={(e) => form.setData('customer_note', e.target.value)} />
                    </Field>

                    {item && (
                        <div className="rounded-md bg-gray-50 p-3 text-center">
                            <div className="text-xs uppercase text-gray-500">Total</div>
                            <div className="text-2xl font-bold">{formatGhs(qty * item.price_pesewas)}</div>
                        </div>
                    )}

                    <PrimaryButton className="w-full justify-center py-4 text-sm" disabled={!valid}>
                        Continue
                    </PrimaryButton>
                    <SecondaryButton className="w-full justify-center py-3" onClick={onDone}>
                        Cancel
                    </SecondaryButton>
                </form>
            </Card>
        </PageShell>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex items-center justify-between px-4 py-3">
            <dt className="text-gray-500">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
    );
}

function ReturnModal({ item, onClose }) {
    const form = useForm({ product_id: item.product_id, quantity: String(item.available) });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('member.returns.store'), { onSuccess: onClose });
    };

    return (
        <FormModal show onClose={onClose} title={`Return ${item.name}`} onSubmit={submit} processing={form.processing} submitLabel="Request return">
            <p className="text-sm text-gray-600">
                The stock stays with you until the warehouse confirms they have received it.
            </p>
            <Field label="Quantity to return" htmlFor="return_qty" error={form.errors.quantity} hint={`You can return up to ${item.available}`}>
                <TextInput id="return_qty" type="number" inputMode="numeric" min="1" max={item.available} className="block w-full"
                    value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} />
            </Field>
        </FormModal>
    );
}
