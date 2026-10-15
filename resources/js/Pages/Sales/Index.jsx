import Field from '@/Components/Field';
import FormModal from '@/Components/FormModal';
import PageShell, { Badge, Card, Table, Td, Th } from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { formatDateTime, formatGhs } from '@/lib/format';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function SalesIndex({ sales, members, filters }) {
    const [reversing, setReversing] = useState(null);

    return (
        <PageShell title="Sales">
            <div>
                <SelectInput
                    value={filters.member_id ?? ''}
                    onChange={(e) =>
                        router.get(route('sales.index'), { member_id: e.target.value || undefined }, { preserveState: true, replace: true })
                    }
                >
                    <option value="">All team members</option>
                    {members.map((m) => (
                        <option key={m.id} value={m.id}>{m.name}</option>
                    ))}
                </SelectInput>
            </div>

            <Card>
                <SalesTable sales={sales.data} showMember onReverse={setReversing} />
                <Pagination paginator={sales} />
            </Card>

            {reversing && <ReverseModal sale={reversing} onClose={() => setReversing(null)} />}
        </PageShell>
    );
}

export function SalesTable({ sales, showMember = false, onReverse = null }) {
    return (
        <Table>
            <thead className="bg-gray-50">
                <tr>
                    <Th>#</Th>
                    <Th>When</Th>
                    {showMember && <Th>Team member</Th>}
                    <Th>Product</Th>
                    <Th className="text-right">Qty</Th>
                    <Th className="text-right">Price</Th>
                    <Th className="text-right">Total</Th>
                    <Th>Customer</Th>
                    <Th />
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
                {sales.length === 0 && (
                    <tr>
                        <Td className="text-gray-500">No sales yet.</Td>
                    </tr>
                )}
                {sales.map((s) => (
                    <tr key={s.id} className={s.reversed ? 'text-gray-400' : ''}>
                        <Td className="text-gray-500">{s.id}</Td>
                        <Td>{formatDateTime(s.created_at)}</Td>
                        {showMember && <Td className="font-medium">{s.member}</Td>}
                        <Td>{s.product}</Td>
                        <Td className="text-right">{s.quantity}</Td>
                        <Td className="text-right">{formatGhs(s.unit_price_pesewas)}</Td>
                        <Td className={'text-right font-medium ' + (s.reversed ? 'line-through' : '')}>{formatGhs(s.total_pesewas)}</Td>
                        <Td className="text-gray-600">{s.customer_note ?? '—'}</Td>
                        <Td className="text-right text-sm">
                            {s.reversed ? (
                                <span title={s.reversal_reason}>
                                    <Badge tone="amber">Reversed</Badge>
                                    {s.reversal_reason && <span className="ms-2 text-xs text-gray-500">{s.reversal_reason}</span>}
                                </span>
                            ) : (
                                onReverse && (
                                    <button className="text-red-600 hover:underline" onClick={() => onReverse(s)}>Reverse</button>
                                )
                            )}
                        </Td>
                    </tr>
                ))}
            </tbody>
        </Table>
    );
}

function ReverseModal({ sale, onClose }) {
    const form = useForm({ reason: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('sales.reverse', sale.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <FormModal show onClose={onClose} title={`Reverse sale #${sale.id}`} onSubmit={submit}
            processing={form.processing} submitLabel="Reverse sale">
            <p className="text-sm text-gray-600">
                {sale.quantity} {sale.unit} of {sale.product} by {sale.member} for {formatGhs(sale.total_pesewas)}. The stock goes
                back to {sale.member}. The original sale is kept and marked reversed.
            </p>
            <Field label="Reason (required)" htmlFor="reverse_reason" error={form.errors.reason}>
                <TextInput id="reverse_reason" className="block w-full" value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)} isFocused />
            </Field>
        </FormModal>
    );
}
