import PageShell, { Card } from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import SelectInput from '@/Components/SelectInput';
import { router } from '@inertiajs/react';
import { MovementsTable } from './Index';

export default function Movements({ movements, products, types, filters }) {
    const filter = (key, value) =>
        router.get(route('stock.movements'), { ...filters, [key]: value || undefined }, { preserveState: true, replace: true });

    return (
        <PageShell title="Stock movements">
            <div className="flex flex-wrap gap-3">
                <SelectInput value={filters.product_id ?? ''} onChange={(e) => filter('product_id', e.target.value)}>
                    <option value="">All products</option>
                    {products.map((p) => (
                        <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                </SelectInput>
                <SelectInput value={filters.type ?? ''} onChange={(e) => filter('type', e.target.value)}>
                    <option value="">All movement types</option>
                    {types.map((t) => (
                        <option key={t.value} value={t.value}>{t.label}</option>
                    ))}
                </SelectInput>
            </div>

            <Card>
                <MovementsTable movements={movements.data} />
                <Pagination paginator={movements} />
            </Card>
        </PageShell>
    );
}
