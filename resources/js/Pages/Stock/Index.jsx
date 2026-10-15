import Field from '@/Components/Field';
import FormModal from '@/Components/FormModal';
import PageShell, { Badge, Card, Stat, Table, Td, Th } from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { formatDateTime, formatGhs, todayInGhana } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function StockIndex({ products, members, recentMovements, adjustmentReasons, can }) {
    const [action, setAction] = useState(null);
    const close = () => setAction(null);
    const active = products.filter((p) => p.status === 'active');

    const totals = products.reduce(
        (sum, p) => ({
            produced: sum.produced + p.produced,
            unassigned: sum.unassigned + p.unassigned,
            withTeam: sum.withTeam + p.with_team,
            online: sum.online + p.online,
            remaining: sum.remaining + p.remaining,
            sold: sum.sold + p.sold,
            value: sum.value + p.value_remaining_pesewas,
            low: sum.low + (p.low_stock && p.status === 'active' ? 1 : 0),
        }),
        { produced: 0, unassigned: 0, withTeam: 0, online: 0, remaining: 0, sold: 0, value: 0, low: 0 },
    );
    const share = (part, whole) => (whole > 0 ? `${Math.round((part / whole) * 1000) / 10}%` : '—');

    return (
        <PageShell
            title="Stock"
            actions={
                <>
                    <PrimaryButton onClick={() => setAction('produce')}>Record production</PrimaryButton>
                    <SecondaryButton onClick={() => setAction('adjust')}>Adjust</SecondaryButton>
                    {can.assign && <SecondaryButton onClick={() => setAction('assign')}>Assign to member</SecondaryButton>}
                    {can.allocateOnline && (
                        <SecondaryButton onClick={() => setAction('allocate')}>Allocate online</SecondaryButton>
                    )}
                </>
            }
        >
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat label="Stock remaining" value={totals.remaining.toLocaleString()} icon="cube" accent="violet" hint="Warehouse + team + online shop" />
                <Stat label="Not yet assigned" value={totals.unassigned.toLocaleString()} icon="archive" accent="amber" hint={`In the warehouse · ${share(totals.unassigned, totals.remaining)}`} />
                <Stat label="With marketing team" value={totals.withTeam.toLocaleString()} icon="team" accent="indigo" hint={`${share(totals.withTeam, totals.remaining)} of stock remaining`} />
                <Stat label="In online shop" value={totals.online.toLocaleString()} icon="shop" accent="teal" hint={`${share(totals.online, totals.remaining)} of stock remaining`} />
                <Stat label="Units produced" value={totals.produced.toLocaleString()} icon="plus" accent="sky" hint="All time" />
                <Stat label="Units sold" value={totals.sold.toLocaleString()} icon="bag" accent="emerald" hint="Team and online, all time" />
                <Stat label="Value remaining" value={formatGhs(totals.value)} icon="banknotes" accent="emerald" hint="At current prices" />
                <Stat label="Below reorder level" value={totals.low} tone={totals.low > 0 ? 'warn' : 'default'} icon="warning" accent={totals.low > 0 ? 'amber' : 'gray'}
                    hint={totals.low > 0 ? 'Warehouse stock to top up' : 'Warehouse stock is enough'} />
            </div>

            <Card title="Stock by product">
                <Table>
                    <thead className="bg-gray-50">
                        <tr>
                            <Th>Product</Th>
                            <Th className="text-right">Not yet assigned</Th>
                            <Th className="text-right">With team</Th>
                            <Th className="text-right">Online shop</Th>
                            <Th className="text-right">Sold</Th>
                            <Th className="text-right">Remaining</Th>
                            <Th className="text-right">Value</Th>
                            <Th>Status</Th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {products.length === 0 && (
                            <tr>
                                <Td className="text-gray-500">No products yet.</Td>
                            </tr>
                        )}
                        {products.map((p) => (
                            <tr key={p.id} className={p.status === 'retired' ? 'text-gray-400' : ''}>
                                <Td>
                                    <div className="font-medium">{p.name}</div>
                                    <div className="text-xs text-gray-500">
                                        {p.sku} · {formatGhs(p.price_pesewas)} / {p.unit}
                                    </div>
                                </Td>
                                <Td className="text-right">{p.unassigned}</Td>
                                <Td className="text-right">{p.with_team}</Td>
                                <Td className="text-right">{p.online}</Td>
                                <Td className="text-right">{p.sold}</Td>
                                <Td className="text-right font-medium">{p.remaining}</Td>
                                <Td className="text-right">{formatGhs(p.value_remaining_pesewas)}</Td>
                                <Td>
                                    <div className="flex gap-1">
                                        {p.status === 'retired' && <Badge>Retired</Badge>}
                                        {p.status === 'active' && p.low_stock && (
                                            <Badge tone="amber">Low (reorder at {p.reorder_level})</Badge>
                                        )}
                                        {!p.reconciles && <Badge tone="red">Does not reconcile</Badge>}
                                    </div>
                                </Td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            </Card>

            <Card title="Recent movements">
                <MovementsTable movements={recentMovements} />
                <div className="border-t border-gray-100 px-4 py-3 text-sm">
                    <Link href={route('stock.movements')} className="text-sky-700 hover:underline">
                        View all movements
                    </Link>
                </div>
            </Card>

            <ProduceModal show={action === 'produce'} onClose={close} products={active} />
            <AdjustModal show={action === 'adjust'} onClose={close} products={products} reasons={adjustmentReasons} />
            {can.assign && <AssignModal show={action === 'assign'} onClose={close} products={active} members={members} />}
            {can.allocateOnline && <AllocateModal show={action === 'allocate'} onClose={close} products={active} />}
        </PageShell>
    );
}

export function MovementsTable({ movements }) {
    return (
        <Table>
            <thead className="bg-gray-50">
                <tr>
                    <Th>When</Th>
                    <Th>Type</Th>
                    <Th>Product</Th>
                    <Th className="text-right">Qty</Th>
                    <Th>From</Th>
                    <Th>To</Th>
                    <Th>By</Th>
                    <Th>Reason</Th>
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
                {movements.length === 0 && (
                    <tr>
                        <Td className="text-gray-500">No movements yet.</Td>
                    </tr>
                )}
                {movements.map((m) => (
                    <tr key={m.id}>
                        <Td>{formatDateTime(m.occurred_at)}</Td>
                        <Td>
                            <Badge tone={m.type === 'SALE_REVERSED' || m.type === 'ADJUSTED_OUT' ? 'amber' : 'blue'}>
                                {m.type_label}
                            </Badge>
                        </Td>
                        <Td>{m.product}</Td>
                        <Td className="text-right">{m.quantity}</Td>
                        <Td>{m.from ?? '—'}</Td>
                        <Td>{m.to ?? '—'}</Td>
                        <Td>{m.user}</Td>
                        <Td className="max-w-xs truncate whitespace-normal text-gray-600">
                            {[m.reason, m.note].filter(Boolean).join(' — ') || '—'}
                        </Td>
                    </tr>
                ))}
            </tbody>
        </Table>
    );
}

function ProductSelect({ products, value, onChange, id = 'product_id' }) {
    return (
        <SelectInput id={id} className="block w-full" value={value} onChange={(e) => onChange(e.target.value)}>
            <option value="">Choose a product…</option>
            {products.map((p) => (
                <option key={p.id} value={p.id}>
                    {p.name} ({p.sku})
                </option>
            ))}
        </SelectInput>
    );
}

function useActionForm(initial, onClose) {
    const form = useForm(initial);
    const submit = (routeName) => (e) => {
        e.preventDefault();
        form.post(route(routeName), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return { ...form, submit };
}

function ProduceModal({ show, onClose, products }) {
    const form = useActionForm({ product_id: '', quantity: '', produced_on: todayInGhana() }, onClose);

    return (
        <FormModal
            show={show}
            onClose={onClose}
            title="Record stock produced"
            onSubmit={form.submit('stock.produce')}
            processing={form.processing}
            submitLabel="Record"
        >
            <Field label="Product" htmlFor="product_id" error={form.errors.product_id}>
                <ProductSelect products={products} value={form.data.product_id} onChange={(v) => form.setData('product_id', v)} />
            </Field>
            <div className="grid grid-cols-2 gap-4">
                <Field label="Quantity" htmlFor="quantity" error={form.errors.quantity}>
                    <TextInput id="quantity" type="number" min="1" className="block w-full" value={form.data.quantity}
                        onChange={(e) => form.setData('quantity', e.target.value)} />
                </Field>
                <Field label="Date produced" htmlFor="produced_on" error={form.errors.produced_on}>
                    <TextInput id="produced_on" type="date" max={todayInGhana()} className="block w-full" value={form.data.produced_on}
                        onChange={(e) => form.setData('produced_on', e.target.value)} />
                </Field>
            </div>
        </FormModal>
    );
}

function AdjustModal({ show, onClose, products, reasons }) {
    const form = useActionForm({ product_id: '', direction: 'out', quantity: '', reason: '', note: '' }, onClose);
    const product = products.find((p) => String(p.id) === String(form.data.product_id));

    return (
        <FormModal
            show={show}
            onClose={onClose}
            title="Adjust warehouse stock"
            onSubmit={form.submit('stock.adjust')}
            processing={form.processing}
            submitLabel="Save adjustment"
        >
            <Field label="Product" htmlFor="adj_product" error={form.errors.product_id}
                hint={product ? `${product.unassigned} ${product.unit} in the warehouse now` : null}>
                <ProductSelect id="adj_product" products={products} value={form.data.product_id} onChange={(v) => form.setData('product_id', v)} />
            </Field>
            <div className="grid grid-cols-2 gap-4">
                <Field label="Direction" htmlFor="direction" error={form.errors.direction}>
                    <SelectInput id="direction" className="block w-full" value={form.data.direction}
                        onChange={(e) => form.setData('direction', e.target.value)}>
                        <option value="out">Down (remove stock)</option>
                        <option value="in">Up (add stock)</option>
                    </SelectInput>
                </Field>
                <Field label="Quantity" htmlFor="adj_quantity" error={form.errors.quantity}>
                    <TextInput id="adj_quantity" type="number" min="1" className="block w-full" value={form.data.quantity}
                        onChange={(e) => form.setData('quantity', e.target.value)} />
                </Field>
            </div>
            <Field label="Reason" htmlFor="reason" error={form.errors.reason}>
                <SelectInput id="reason" className="block w-full capitalize" value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}>
                    <option value="">Choose a reason…</option>
                    {reasons.map((r) => (
                        <option key={r} value={r}>{r}</option>
                    ))}
                </SelectInput>
            </Field>
            <Field label={form.data.reason === 'other' ? 'Explanation (required)' : 'Note (optional)'} htmlFor="note" error={form.errors.note}>
                <TextInput id="note" className="block w-full" value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)} />
            </Field>
        </FormModal>
    );
}

function AssignModal({ show, onClose, products, members }) {
    const form = useActionForm({ product_id: '', member_id: '', quantity: '' }, onClose);
    const product = products.find((p) => String(p.id) === String(form.data.product_id));
    const qty = parseInt(form.data.quantity, 10) || 0;

    return (
        <FormModal
            show={show}
            onClose={onClose}
            title="Assign stock to a team member"
            onSubmit={form.submit('stock.assign')}
            processing={form.processing}
            submitLabel="Assign"
        >
            <Field label="Team member" htmlFor="member_id" error={form.errors.member_id}>
                <SelectInput id="member_id" className="block w-full" value={form.data.member_id}
                    onChange={(e) => form.setData('member_id', e.target.value)}>
                    <option value="">Choose a member…</option>
                    {members.map((m) => (
                        <option key={m.id} value={m.id}>{m.name}</option>
                    ))}
                </SelectInput>
            </Field>
            <Field label="Product" htmlFor="assign_product" error={form.errors.product_id}>
                <ProductSelect id="assign_product" products={products} value={form.data.product_id} onChange={(v) => form.setData('product_id', v)} />
            </Field>
            <Field label="Quantity" htmlFor="assign_quantity" error={form.errors.quantity}>
                <TextInput id="assign_quantity" type="number" min="1" max={product?.unassigned} className="block w-full"
                    value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} />
            </Field>
            {product && (
                <dl className="grid grid-cols-3 gap-2 rounded-md bg-gray-50 p-3 text-sm">
                    <div>
                        <dt className="text-gray-500">Available now</dt>
                        <dd className="font-semibold">{product.unassigned}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-500">After this</dt>
                        <dd className={'font-semibold ' + (product.unassigned - qty < 0 ? 'text-red-600' : '')}>
                            {product.unassigned - qty}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-gray-500">Value at {formatGhs(product.price_pesewas)}</dt>
                        <dd className="font-semibold">{formatGhs(qty * (product.price_pesewas ?? 0))}</dd>
                    </div>
                </dl>
            )}
        </FormModal>
    );
}

function AllocateModal({ show, onClose, products }) {
    const form = useActionForm({ product_id: '', quantity: '' }, onClose);
    const product = products.find((p) => String(p.id) === String(form.data.product_id));

    return (
        <FormModal
            show={show}
            onClose={onClose}
            title="Allocate stock to the online shop"
            onSubmit={form.submit('stock.allocate-online')}
            processing={form.processing}
            submitLabel="Allocate"
        >
            <Field label="Product" htmlFor="alloc_product" error={form.errors.product_id}
                hint={product ? `${product.unassigned} not yet assigned · ${product.online} already online` : null}>
                <ProductSelect id="alloc_product" products={products} value={form.data.product_id} onChange={(v) => form.setData('product_id', v)} />
            </Field>
            <Field label="Quantity" htmlFor="alloc_quantity" error={form.errors.quantity}>
                <TextInput id="alloc_quantity" type="number" min="1" max={product?.unassigned} className="block w-full"
                    value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} />
            </Field>
        </FormModal>
    );
}
