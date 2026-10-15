import Field from '@/Components/Field';
import FormModal from '@/Components/FormModal';
import PageShell, { Badge, Card, Table, Td, Th } from '@/Components/PageShell';
import TextInput from '@/Components/TextInput';
import { formatDateTime } from '@/lib/format';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ReturnsIndex({ pending, recent }) {
    const [rejecting, setRejecting] = useState(null);

    const confirmReceived = (r) => {
        if (confirm(`Confirm you have received ${r.quantity} ${r.unit} of ${r.product} from ${r.member}?`)) {
            router.post(route('returns.confirm', r.id), {}, { preserveScroll: true });
        }
    };

    return (
        <PageShell title="Returns">
            <Card title={`Waiting for the warehouse (${pending.length})`}>
                <Table>
                    <thead className="bg-gray-50">
                        <tr>
                            <Th>Requested</Th>
                            <Th>Team member</Th>
                            <Th>Product</Th>
                            <Th className="text-right">Qty</Th>
                            <Th />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {pending.length === 0 && (
                            <tr>
                                <Td className="text-gray-500">No returns waiting.</Td>
                            </tr>
                        )}
                        {pending.map((r) => (
                            <tr key={r.id}>
                                <Td>{formatDateTime(r.requested_at)}</Td>
                                <Td className="font-medium">{r.member}</Td>
                                <Td>{r.product}</Td>
                                <Td className="text-right">{r.quantity} {r.unit}</Td>
                                <Td className="space-x-3 text-right text-sm">
                                    <button className="font-medium text-emerald-700 hover:underline" onClick={() => confirmReceived(r)}>
                                        Confirm received
                                    </button>
                                    <button className="text-gray-600 hover:underline" onClick={() => setRejecting(r)}>Reject</button>
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
                                <Td className="text-right">{r.quantity} {r.unit}</Td>
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

            {rejecting && <RejectModal request={rejecting} onClose={() => setRejecting(null)} />}
        </PageShell>
    );
}

function RejectModal({ request, onClose }) {
    const form = useForm({ note: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('returns.reject', request.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <FormModal show onClose={onClose} title="Reject return" onSubmit={submit} processing={form.processing} submitLabel="Reject">
            <p className="text-sm text-gray-600">
                {request.member} asked to return {request.quantity} {request.unit} of {request.product}. The stock stays with them.
            </p>
            <Field label="Note (optional)" htmlFor="reject_note" error={form.errors.note}>
                <TextInput id="reject_note" className="block w-full" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
            </Field>
        </FormModal>
    );
}
