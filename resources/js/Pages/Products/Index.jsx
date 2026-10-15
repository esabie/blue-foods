import ConfirmModal from '@/Components/ConfirmModal';
import Field from '@/Components/Field';
import FormModal from '@/Components/FormModal';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import RecordCard, { CardAction, CardGrid, DetailLine, ListToolbar, matches, MiniStats, NoMatches, Pill } from '@/Components/RecordCard';
import TextInput from '@/Components/TextInput';
import { formatDate, formatDateTime, formatGhs, todayInGhana, toCedisInput, toPesewas } from '@/lib/format';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const blank = { sku: '', name: '', category: '', unit: '', description: '', reorder_level: '0', price: '', cost: '', photo: null };

function margin(price, cost) {
    if (!price || cost === null || cost === undefined) return null;
    return Math.round(((price - cost) / price) * 1000) / 10;
}

function PhotoButton({ product }) {
    const upload = (e) => {
        const file = e.target.files[0];
        if (file) {
            router.post(route('products.photo', product.id), { photo: file }, { forceFormData: true, preserveScroll: true });
        }
        e.target.value = '';
    };

    return (
        <label className="group relative block h-12 w-12 cursor-pointer overflow-hidden rounded-xl bg-slate-200" title="Upload website photo">
            {product.photo_url ? (
                <img src={product.photo_url} alt="" className="h-full w-full object-cover" />
            ) : (
                <span className="flex h-full w-full items-center justify-center text-lg text-slate-500">+</span>
            )}
            <span className="absolute inset-0 hidden items-center justify-center bg-black/40 text-[10px] font-medium text-white group-hover:flex">Photo</span>
            <input type="file" accept="image/*" className="sr-only" onChange={upload} />
        </label>
    );
}

function History({ product }) {
    return (
        <div className="space-y-4 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-5 py-4 text-sm">
            <div>
                <div className="text-xs font-semibold uppercase text-gray-500">Price history</div>
                <ul className="mt-2 space-y-1">
                    {product.price_history.map((h) => (
                        <li key={h.id}>
                            <span className="font-medium">{formatGhs(h.unit_price_pesewas)}</span>
                            <span className="text-gray-500"> from {formatDateTime(h.effective_from)}</span>
                            {h.set_by && <span className="text-gray-500"> · set by {h.set_by}</span>}
                        </li>
                    ))}
                </ul>
            </div>
            <div>
                <div className="text-xs font-semibold uppercase text-gray-500">Cost history</div>
                {product.cost_history.length === 0 && <p className="mt-2 text-gray-500">No cost set yet.</p>}
                <ul className="mt-2 space-y-1">
                    {product.cost_history.map((h) => (
                        <li key={h.id}>
                            <span className="font-medium">{formatGhs(h.unit_cost_pesewas)}</span>
                            <span className="text-gray-500"> from {formatDate(h.effective_from)}</span>
                            {h.set_by && <span className="text-gray-500"> · set by {h.set_by} on {formatDate(h.set_at)}</span>}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

const number = (n) => n.toLocaleString('en-GH');

const FILTERS = {
    active: (p) => p.status === 'active',
    low: (p) => p.status === 'active' && p.stock.low_stock,
    nocost: (p) => p.status === 'active' && p.cost_pesewas === null,
    retired: (p) => p.status === 'retired',
    all: () => true,
};

export default function ProductsIndex({ products }) {
    const [editing, setEditing] = useState(null);
    const [pricing, setPricing] = useState(null);
    const [costing, setCosting] = useState(null);
    const [creating, setCreating] = useState(false);
    const [expanded, setExpanded] = useState(null);
    const [retiring, setRetiring] = useState(null);
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState('active');

    const count = (key) => products.filter(FILTERS[key]).length;
    const tabs = [
        { key: 'active', label: 'Active', count: count('active') },
        ...(count('low') > 0 ? [{ key: 'low', label: 'Low stock', count: count('low') }] : []),
        ...(count('nocost') > 0 ? [{ key: 'nocost', label: 'No cost set', count: count('nocost') }] : []),
        ...(count('retired') > 0 ? [{ key: 'retired', label: 'Retired', count: count('retired') }] : []),
        { key: 'all', label: 'All', count: products.length },
    ];
    const shown = products.filter((p) => FILTERS[tab](p) && matches(search, p.name, p.sku, p.category));

    const reactivate = (product) => router.post(route('products.reactivate', product.id), {}, { preserveScroll: true });

    return (
        <PageShell
            title="Products"
            actions={
                <PrimaryButton onClick={() => setCreating(true)} className="gap-2">
                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                    New product
                </PrimaryButton>
            }
        >
            {products.length > 0 && (
                <ListToolbar search={search} onSearch={setSearch} placeholder="Search name, SKU or category" tabs={tabs} tab={tab} onTab={setTab} />
            )}

            {products.length === 0 ? (
                <NoMatches>No products yet. Use New product to add the first one.</NoMatches>
            ) : shown.length === 0 ? (
                <NoMatches onClear={search ? () => setSearch('') : null}>No products match.</NoMatches>
            ) : (
                <CardGrid>
                    {shown.map((p) => {
                        const pct = margin(p.price_pesewas, p.cost_pesewas);

                        return (
                            <RecordCard
                                key={p.id}
                                muted={p.status === 'retired'}
                                media={<PhotoButton product={p} />}
                                pills={
                                    <>
                                        <Pill tone={p.status === 'active' ? 'green' : 'gray'}>{p.status}</Pill>
                                        {p.status === 'active' && p.stock.low_stock && <Pill tone="amber">Low stock</Pill>}
                                        {p.status === 'active' && p.cost_pesewas === null && <Pill tone="soft">No cost set</Pill>}
                                    </>
                                }
                                date={p.created_at && `Added ${formatDate(p.created_at)}`}
                                title={p.name}
                                subtitle={<><span className="font-mono">{p.sku}</span> · {p.category} · per {p.unit}</>}
                                actions={
                                    <>
                                        <CardAction onClick={() => setPricing(p)}>Change price</CardAction>
                                        <CardAction onClick={() => setCosting(p)}>{p.cost_pesewas === null ? 'Set cost' : 'Change cost'}</CardAction>
                                        <CardAction onClick={() => setEditing(p)}>Edit</CardAction>
                                        {p.status === 'active' ? (
                                            <CardAction tone="muted" onClick={() => setRetiring(p)}>Retire</CardAction>
                                        ) : (
                                            <CardAction onClick={() => reactivate(p)}>Reactivate</CardAction>
                                        )}
                                    </>
                                }
                                footer={expanded === p.id && <History product={p} />}
                            >
                                <MiniStats
                                    items={[
                                        { label: 'Price', value: formatGhs(p.price_pesewas) },
                                        { label: 'Cost', value: p.cost_pesewas === null ? 'Not set' : formatGhs(p.cost_pesewas), tone: p.cost_pesewas === null ? 'amber' : null },
                                        { label: 'Margin', value: pct === null ? '—' : `${pct}%`, tone: pct === null ? 'muted' : null },
                                    ]}
                                />
                                <button
                                    type="button"
                                    onClick={() => setExpanded(expanded === p.id ? null : p.id)}
                                    aria-expanded={expanded === p.id}
                                    className="block text-xs font-medium text-sky-800 hover:underline"
                                >
                                    {expanded === p.id ? 'Hide price and cost history' : 'Price and cost history'}
                                </button>
                                <div className="space-y-2">
                                    <DetailLine icon="cube">
                                        <span className={p.stock.low_stock ? 'font-medium text-amber-800' : ''}>{number(p.stock.unassigned)} in warehouse</span>
                                        <span className="text-gray-400"> · reorder below {number(p.reorder_level)}</span>
                                    </DetailLine>
                                    <DetailLine icon="team">
                                        {number(p.stock.with_team)} with the team · {number(p.stock.online)} in the online shop
                                    </DetailLine>
                                    <DetailLine icon="bag">
                                        {p.stock.sold_30_days > 0 ? `${number(p.stock.sold_30_days)} sold in the last 30 days` : <span className="text-gray-400">No sales in the last 30 days</span>}
                                    </DetailLine>
                                </div>
                            </RecordCard>
                        );
                    })}
                </CardGrid>
            )}

            <ProductModal show={creating} onClose={() => setCreating(false)} />
            {editing && <ProductModal show product={editing} onClose={() => setEditing(null)} />}
            {pricing && <PriceModal product={pricing} onClose={() => setPricing(null)} />}
            {costing && <CostModal product={costing} onClose={() => setCosting(null)} />}
            {retiring && (
                <ConfirmModal
                    show
                    danger
                    title={`Retire ${retiring.name}?`}
                    confirmLabel="Retire product"
                    onClose={() => setRetiring(null)}
                    onConfirm={(done) => router.post(route('products.retire', retiring.id), {}, { preserveScroll: true, onFinish: done })}
                >
                    It will no longer appear in stock forms or the online shop. Its history is kept, and you can reactivate it at any time.
                    {retiring.stock.remaining > 0 && (
                        <p className="mt-2 font-medium text-amber-800">
                            {number(retiring.stock.remaining)} units are still in stock ({number(retiring.stock.unassigned)} in the warehouse,{' '}
                            {number(retiring.stock.with_team)} with the team, {number(retiring.stock.online)} online).
                        </p>
                    )}
                </ConfirmModal>
            )}
        </PageShell>
    );
}

function ProductModal({ show, product, onClose }) {
    const form = useForm(
        product
            ? { sku: product.sku, name: product.name, category: product.category, unit: product.unit, description: product.description ?? '', reorder_level: String(product.reorder_level) }
            : blank,
    );
    const photoInput = useRef(null);
    const photoPreview = useMemo(() => (form.data.photo ? URL.createObjectURL(form.data.photo) : null), [form.data.photo]);
    useEffect(() => () => photoPreview && URL.revokeObjectURL(photoPreview), [photoPreview]);

    const submit = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                if (photoInput.current) photoInput.current.value = '';
                onClose();
            },
        };
        product ? form.put(route('products.update', product.id), options) : form.post(route('products.store'), options);
    };

    return (
        <FormModal show={show} onClose={onClose} title={product ? `Edit ${product.name}` : 'New product'}
            onSubmit={submit} processing={form.processing} submitLabel={product ? 'Save' : 'Create product'}>
            <div className="grid grid-cols-2 gap-4">
                <Field label="Name" htmlFor="name" error={form.errors.name}>
                    <TextInput id="name" className="block w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </Field>
                <Field label="SKU code" htmlFor="sku" error={form.errors.sku}>
                    <TextInput id="sku" className="block w-full font-mono" value={form.data.sku} onChange={(e) => form.setData('sku', e.target.value.toUpperCase())} />
                </Field>
                <Field label="Category" htmlFor="category" error={form.errors.category}>
                    <TextInput id="category" className="block w-full" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} />
                </Field>
                <Field label="Unit of measure" htmlFor="unit" error={form.errors.unit} hint="e.g. pack, kg, tray">
                    <TextInput id="unit" className="block w-full" value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} />
                </Field>
                <Field label="Reorder level" htmlFor="reorder_level" error={form.errors.reorder_level} hint="Flag when unassigned stock falls below this">
                    <TextInput id="reorder_level" type="number" min="0" className="block w-full" value={form.data.reorder_level}
                        onChange={(e) => form.setData('reorder_level', e.target.value)} />
                </Field>
                {!product && (
                    <>
                        <Field label="Unit price (GHS)" htmlFor="price" error={form.errors.price}>
                            <TextInput id="price" inputMode="decimal" placeholder="0.00" className="block w-full" value={form.data.price}
                                onChange={(e) => form.setData('price', e.target.value)} />
                        </Field>
                        <Field label="Cost per unit (GHS, optional)" htmlFor="cost" error={form.errors.cost} hint="What one unit costs to make; used for profit">
                            <TextInput id="cost" inputMode="decimal" placeholder="0.00" className="block w-full" value={form.data.cost}
                                onChange={(e) => form.setData('cost', e.target.value)} />
                        </Field>
                    </>
                )}
                <Field label="Website description" htmlFor="description" error={form.errors.description} className="col-span-2"
                    hint="Shown on the product page of the online shop.">
                    <textarea id="description" rows={4} className="block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-600 focus:ring-sky-600"
                        value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </Field>
                {!product && (
                    <Field label="Website photo" htmlFor="photo" error={form.errors.photo} className="col-span-2"
                        hint="JPG or PNG, up to 4 MB. Shown on the online shop.">
                        <div className="flex items-center gap-4">
                            {photoPreview ? (
                                <img src={photoPreview} alt="" className="h-20 w-20 rounded-md object-cover ring-1 ring-gray-200" />
                            ) : (
                                <div className="flex h-20 w-20 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-400">No photo</div>
                            )}
                            <input id="photo" ref={photoInput} type="file" accept="image/*" className="text-sm"
                                onChange={(e) => form.setData('photo', e.target.files[0] ?? null)} />
                        </div>
                    </Field>
                )}
            </div>
        </FormModal>
    );
}

function PriceModal({ product, onClose }) {
    const form = useForm({ price: toCedisInput(product.price_pesewas) });
    const pesewas = toPesewas(form.data.price);

    const submit = (e) => {
        e.preventDefault();
        form.post(route('products.price', product.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <FormModal show onClose={onClose} title={`Change price: ${product.name}`} onSubmit={submit}
            processing={form.processing} submitLabel="Set new price">
            <p className="text-sm text-gray-600">
                Current price {formatGhs(product.price_pesewas)} per {product.unit}. The new price applies to new
                assignments and sales only; past records keep the price they were made at.
            </p>
            <Field label="New unit price (GHS)" htmlFor="new_price" error={form.errors.price}
                hint={pesewas !== null ? `= ${formatGhs(pesewas)}` : 'Enter an amount like 25 or 25.50'}>
                <TextInput id="new_price" inputMode="decimal" className="block w-full" value={form.data.price}
                    onChange={(e) => form.setData('price', e.target.value)} isFocused />
            </Field>
        </FormModal>
    );
}

function CostModal({ product, onClose }) {
    const today = todayInGhana();
    const form = useForm({ cost: product.cost_pesewas === null ? '' : toCedisInput(product.cost_pesewas), effective_from: today });
    const pesewas = toPesewas(form.data.cost);
    const newMargin = pesewas === null ? null : margin(product.price_pesewas, pesewas);

    const submit = (e) => {
        e.preventDefault();
        form.post(route('products.cost', product.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <FormModal show onClose={onClose} title={`${product.cost_pesewas === null ? 'Set' : 'Change'} cost: ${product.name}`} onSubmit={submit}
            processing={form.processing} submitLabel="Save cost">
            <p className="text-sm text-gray-600">
                What one {product.unit} costs to make or buy. Profit on each sale uses the cost in force on the day of the sale.
                {product.cost_pesewas !== null && <> Current cost {formatGhs(product.cost_pesewas)}.</>}
            </p>
            <Field label="Cost per unit (GHS)" htmlFor="new_cost" error={form.errors.cost}
                hint={pesewas !== null
                    ? `= ${formatGhs(pesewas)}${newMargin !== null ? ` · ${newMargin}% margin at today's price of ${formatGhs(product.price_pesewas)}` : ''}`
                    : 'Enter an amount like 15 or 15.50'}>
                <TextInput id="new_cost" inputMode="decimal" className="block w-full" value={form.data.cost}
                    onChange={(e) => form.setData('cost', e.target.value)} isFocused />
            </Field>
            <Field label="Applies to sales from" htmlFor="effective_from" error={form.errors.effective_from}
                hint={form.data.effective_from && form.data.effective_from < today
                    ? 'Sales already made from this date until the next cost change will be re-costed.'
                    : 'Leave as today unless this cost should also cover earlier sales.'}>
                <TextInput id="effective_from" type="date" max={today} className="block w-full" value={form.data.effective_from}
                    onChange={(e) => form.setData('effective_from', e.target.value)} />
            </Field>
        </FormModal>
    );
}
