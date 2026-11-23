import Field from '@/Components/Field';
import FormModal from '@/Components/FormModal';
import PageShell, { Badge, Card, Table, Td, Th } from '@/Components/PageShell';
import TextInput from '@/Components/TextInput';
import { formatDateTime } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function StockRequestsIndex({ pending, recent }) {
    const [fulfilling, setFulfilling] = useState(null);
    const [rejecting, setRejecting] = useState(null);

    const openFulfill = (r) => {
        if (!r.can_fulfill) {
            setRejecting(r);
            return;
        }
        setFulfilling(r);
    };

    return (
        <PageShell title="Stock requests">
            <Card title={`Waiting for the warehouse (${pending.length})`}>
                <Table>
                    <thead className="bg-gray-50">
                        <tr>
                            <Th>Requested</Th>
                            <Th>Team member</Th>
                            <Th>Product</Th>
                            <Th className="text-right">Qty</Th>
                            <Th className="text-right">In warehouse</Th>
                            <Th>Note</Th>
                            <Th />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {pending.length === 0 && (
                            <tr>
                                <Td className="text-gray-500">No stock requests waiting.</Td>
                            </tr>
                        )}
                        {pending.map((r) => (
                            <tr key={r.id}>
                                <Td>{formatDateTime(r.requested_at)}</Td>
                                <Td>
                                    <div className="font-medium">{r.member}</div>
                                    {r.cash_overdue && <Badge tone="amber">Cash overdue</Badge>}
                                </Td>
                                <Td>{r.product}</Td>
                                <Td className="text-right">{r.requested_quantity} {r.unit}</Td>
                                <Td className={'text-right ' + (r.can_fulfill ? '' : 'font-medium text-amber-700')}>
                                    {r.unassigned} {r.unit}
                                </Td>
                                <Td className="max-w-xs truncate text-gray-600">{r.member_note ?? '—'}</Td>
                                <Td className="space-x-3 text-right text-sm">
                                    <button
                                        className={'font-medium hover:underline ' + (r.can_fulfill ? 'text-emerald-700' : 'text-amber-700')}
                                        onClick={() => openFulfill(r)}
                                    >
                                        {r.can_fulfill ? 'Assign stock' : 'Cannot fulfill'}
                                    </button>
                                    <button className="text-gray-600 hover:underline" onClick={() => setRejecting(r)}>Decline</button>
                                </Td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            </Card>

            <Card title="Recently decided">
                <Table>
                    <thead className="bg-gray-50">
                        <tr>
                            <Th>Decided</Th>
                            <Th>Team member</Th>
                            <Th>Product</Th>
                            <Th className="text-right">Qty</Th>
                            <Th>Status</Th>
                            <Th>By</Th>
                            <Th>Note</Th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {recent.length === 0 && (
                            <tr>
                                <Td className="text-gray-500">Nothing yet.</Td>
                            </tr>
                        )}
                        {recent.map((r) => (
                            <tr key={r.id}>
                                <Td>{formatDateTime(r.decided_at)}</Td>
                                <Td>{r.member}</Td>
                                <Td>{r.product}</Td>
                                <Td className="text-right">
                                    {r.quantity} {r.unit}
                                    {r.status === 'confirmed' && r.quantity !== r.requested_quantity && (
                                        <div className="text-xs text-gray-500">of {r.requested_quantity} asked</div>
                                    )}
                                </Td>
                                <Td>
                                    <Badge tone={r.status === 'confirmed' ? 'green' : 'gray'}>{r.status}</Badge>
                                </Td>
                                <Td>{r.decided_by}</Td>
                                <Td className="text-gray-600">{r.decision_note ?? '—'}</Td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            </Card>

            {fulfilling && <AssignModal request={fulfilling} onClose={() => setFulfilling(null)} />}
            {rejecting && <DeclineModal request={rejecting} onClose={() => setRejecting(null)} />}
        </PageShell>
    );
}

function AssignModal({ request, onClose }) {
    const max = request.max_assignable;
    const form = useForm({
        quantity: String(max),
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('stock-requests.confirm', request.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <FormModal show onClose={onClose} title="Assign stock" onSubmit={submit} processing={form.processing} submitLabel="Assign">
            <p className="text-sm text-gray-600">
                {request.member} asked for {request.requested_quantity} {request.unit} of {request.product}.
                {max < request.requested_quantity
                    ? ` You can assign up to ${max} ${request.unit} from what is unassigned.`
                    : ` You can assign the full amount, or less if needed.`}
            </p>
            <Field
                label="Quantity to assign"
                htmlFor="assign_qty"
                error={form.errors.quantity}
                hint={`Up to ${max} ${request.unit} (${request.unassigned} unassigned in the warehouse)`}
            >
                <TextInput
                    id="assign_qty"
                    type="number"
                    inputMode="numeric"
                    min="1"
                    max={max}
                    className="block w-full"
                    value={form.data.quantity}
                    onChange={(e) => form.setData('quantity', e.target.value)}
                    isFocused
                />
            </Field>
        </FormModal>
    );
}

function DeclineModal({ request, onClose }) {
    const form = useForm({
        note: request.can_fulfill
            ? ''
            : `Only ${request.unassigned} ${request.unit} available in the warehouse right now.`,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('stock-requests.reject', request.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <FormModal show onClose={onClose} title="Decline stock request" onSubmit={submit} processing={form.processing} submitLabel="Decline">
            <p className="text-sm text-gray-600">
                {request.member} asked for {request.requested_quantity} {request.unit} of {request.product}.
                {!request.can_fulfill && ` The warehouse only has ${request.unassigned} ${request.unit} unassigned.`}
                {' '}Tell them why it cannot be assigned yet.
            </p>
            {request.unassigned < request.requested_quantity && (
                <p className="text-sm">
                    <Link
                        href={route('stock.index', { produce: request.product_id })}
                        className="font-medium text-sky-700 hover:underline"
                    >
                        Record production for {request.product}
                    </Link>
                    {' '}if you need to make more before fulfilling.
                </p>
            )}
            <Field label="Message to the team member" htmlFor="decline_note" error={form.errors.note}>
                <TextInput id="decline_note" className="block w-full" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} isFocused />
            </Field>
        </FormModal>
    );
}
