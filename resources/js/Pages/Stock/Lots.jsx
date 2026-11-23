import PageShell, { Badge, Card } from '@/Components/PageShell';
import SelectInput from '@/Components/SelectInput';
import { formatDate } from '@/lib/format';
import { router } from '@inertiajs/react';

export default function Lots({ lots, products, filters, expiringDays = 14 }) {
    const filter = (productId) =>
        router.get(route('stock.lots'), { product_id: productId || undefined }, { preserveState: true, replace: true });

    return (
        <PageShell title="Production lots">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <p className="max-w-2xl text-sm text-gray-600">
                    Lots are created when you record production. Outbound stock uses the soonest-expiring lot first.
                    Management and warehouse get a bell alert when a lot enters the {expiringDays}-day window or expires with stock still on hand.
                </p>
                <SelectInput value={filters.product_id ?? ''} onChange={(e) => filter(e.target.value)}>
                    <option value="">All products</option>
                    {products.map((p) => (
                        <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                </SelectInput>
            </div>

            <Card>
                {lots.length === 0 ? (
                    <p className="px-4 py-10 text-center text-sm text-gray-500">No open lots. Record production to create one.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Lot</th>
                                    <th className="px-4 py-3 font-medium">Product</th>
                                    <th className="px-4 py-3 font-medium">Produced</th>
                                    <th className="px-4 py-3 font-medium">Expires</th>
                                    <th className="px-4 py-3 font-medium text-right">Remaining</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {lots.map((lot) => (
                                    <tr key={lot.id} className={lot.expired ? 'bg-red-50/50' : lot.expiring_soon ? 'bg-amber-50/40' : ''}>
                                        <td className="px-4 py-3">
                                            <span className="font-mono text-xs font-semibold text-sky-900">{lot.lot_code}</span>
                                            {lot.expired && (
                                                <span className="ml-2 inline-block"><Badge tone="red">Expired</Badge></span>
                                            )}
                                            {!lot.expired && lot.expiring_soon && (
                                                <span className="ml-2 inline-block"><Badge tone="amber">Expiring soon</Badge></span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-gray-900">{lot.product}</p>
                                            <p className="text-xs text-gray-500">{lot.sku}</p>
                                        </td>
                                        <td className="px-4 py-3 text-gray-600">{formatDate(lot.produced_on)}</td>
                                        <td className="px-4 py-3 text-gray-600">{lot.expires_on ? formatDate(lot.expires_on) : '—'}</td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            <span className="font-semibold text-gray-900">{lot.remaining.toLocaleString()}</span>
                                            <span className="text-gray-400"> / {lot.quantity.toLocaleString()} {lot.unit}</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </PageShell>
    );
}
