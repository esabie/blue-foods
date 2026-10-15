import Field from '@/Components/Field';
import FormModal from '@/Components/FormModal';
import PageShell, { Badge, Card, Stat, StatusDot, Table, Td, Th } from '@/Components/PageShell';
import Pagination from '@/Components/Pagination';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { formatDate, formatDateTime, formatGhs, toCedisInput, toPesewas } from '@/lib/format';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function CashIndex({ totals, dueDays, positions, handIns, members, filters }) {
    const [recording, setRecording] = useState(null);
    const [cancelling, setCancelling] = useState(null);
    const owing = positions.filter((p) => p.outstanding_pesewas > 0);

    return (
        <PageShell
            title="Cash"
            actions={
                <PrimaryButton disabled={owing.length === 0} onClick={() => setRecording({ member: owing[0] })}>
                    Record hand-in
                </PrimaryButton>
            }
        >
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat
                    label="To hand in"
                    value={formatGhs(totals.outstanding_pesewas)}
                    icon="banknotes"
                    accent="amber"
                    hint={`${totals.owing} ${totals.owing === 1 ? 'member' : 'members'} owing`}
                />
                <Stat
                    label="Overdue"
                    value={totals.overdue}
                    tone={totals.overdue > 0 ? 'warn' : 'default'}
                    icon="warning"
                    accent={totals.overdue > 0 ? 'amber' : 'gray'}
                    hint={
                        totals.overdue > 0 ? `Sales older than ${dueDays} days` : <StatusDot tone="green">Everyone is up to date</StatusDot>
                    }
                />
                <Stat label="Received today" value={formatGhs(totals.today_pesewas)} icon="clock" accent="teal" />
                <Stat label="Received this month" value={formatGhs(totals.month_pesewas)} icon="calendar" accent="sky" />
            </div>

            <Card title="Team balances">
                <Table>
                    <thead className="bg-gray-50">
                        <tr>
                            <Th>Team member</Th>
                            <Th className="text-right">Sales</Th>
                            <Th className="text-right">Handed in</Th>
                            <Th className="text-right">To hand in</Th>
                            <Th>Owing since</Th>
                            <Th>Last hand-in</Th>
                            <Th />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {positions.length === 0 && (
                            <tr>
                                <Td className="text-gray-500">No team members yet.</Td>
                            </tr>
                        )}
                        {positions.map((p) => (
                            <tr key={p.user_id}>
                                <Td className="font-medium">
                                    {p.name}
                                    {!p.active && <span className="ms-2"><Badge>Disabled</Badge></span>}
                                </Td>
                                <Td className="text-right">{formatGhs(p.sold_pesewas)}</Td>
                                <Td className="text-right">{formatGhs(p.handed_in_pesewas)}</Td>
                                <Td className="text-right font-semibold">
                                    {p.outstanding_pesewas < 0 ? (
                                        <span className="font-normal text-gray-500">{formatGhs(-p.outstanding_pesewas)} over</span>
                                    ) : (
                                        formatGhs(p.outstanding_pesewas)
                                    )}
                                </Td>
                                <Td>
                                    {p.owing_since ? formatDate(p.owing_since) : '—'}
                                    {p.overdue && <span className="ms-2"><Badge tone="amber">Overdue</Badge></span>}
                                </Td>
                                <Td className="text-gray-600">{p.last_hand_in_at ? formatDateTime(p.last_hand_in_at) : 'Never'}</Td>
                                <Td className="text-right text-sm">
                                    {p.outstanding_pesewas > 0 && (
                                        <button className="text-sky-700 hover:underline" onClick={() => setRecording({ member: p })}>
                                            Record hand-in
                                        </button>
                                    )}
                                </Td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            </Card>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-baseline gap-4">
                    <h2 className="text-base font-semibold text-gray-900">Hand-ins</h2>
                    <Link href={route('reports.show', 'cash-hand-ins')} className="text-sm text-sky-700 hover:underline">
                        Report and spreadsheet export
                    </Link>
                </div>
                <SelectInput
                    value={filters.member_id ?? ''}
                    onChange={(e) =>
                        router.get(route('cash.index'), { member_id: e.target.value || undefined }, { preserveState: true, preserveScroll: true, replace: true })
                    }
                >
                    <option value="">All team members</option>
                    {members.map((m) => (
                        <option key={m.id} value={m.id}>{m.name}</option>
                    ))}
                </SelectInput>
            </div>

            <Card>
                <Table>
                    <thead className="bg-gray-50">
                        <tr>
                            <Th>#</Th>
                            <Th>When</Th>
                            <Th>Team member</Th>
                            <Th className="text-right">Amount</Th>
                            <Th>Method</Th>
                            <Th>Received by</Th>
                            <Th>Note</Th>
                            <Th />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {handIns.data.length === 0 && (
                            <tr>
                                <Td className="text-gray-500">No hand-ins recorded yet.</Td>
                            </tr>
                        )}
                        {handIns.data.map((h) => (
                            <tr key={h.id} className={h.cancelled ? 'text-gray-400' : ''}>
                                <Td className="text-gray-500">{h.id}</Td>
                                <Td>{formatDateTime(h.created_at)}</Td>
                                <Td className="font-medium">{h.member}</Td>
                                <Td className={'text-right font-medium ' + (h.cancelled ? 'line-through' : '')}>{formatGhs(h.amount_pesewas)}</Td>
                                <Td>
                                    {h.method_label}
                                    {h.reference && <span className="ms-1 text-xs text-gray-500">{h.reference}</span>}
                                </Td>
                                <Td className="text-gray-600">{h.received_by}</Td>
                                <Td className="text-gray-600">{h.note ?? '—'}</Td>
                                <Td className="text-right text-sm">
                                    {h.cancelled ? (
                                        <span title={`Cancelled by ${h.cancelled_by}`}>
                                            <Badge tone="amber">Cancelled</Badge>
                                            <span className="ms-2 text-xs text-gray-500">{h.cancel_reason}</span>
                                        </span>
                                    ) : (
                                        <button className="text-red-600 hover:underline" onClick={() => setCancelling(h)}>Cancel</button>
                                    )}
                                </Td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
                <Pagination paginator={handIns} />
            </Card>

            {recording && <RecordModal owing={owing} initial={recording.member} onClose={() => setRecording(null)} />}
            {cancelling && <CancelModal handIn={cancelling} onClose={() => setCancelling(null)} />}
        </PageShell>
    );
}

function RecordModal({ owing, initial, onClose }) {
    const form = useForm({
        member_id: initial?.user_id ?? '',
        amount: toCedisInput(initial?.outstanding_pesewas),
        method: 'cash',
        reference: '',
        note: '',
    });
    const member = owing.find((p) => String(p.user_id) === String(form.data.member_id));
    const pesewas = toPesewas(form.data.amount);
    const left = member && pesewas !== null ? member.outstanding_pesewas - pesewas : null;

    const pickMember = (id) => {
        const next = owing.find((p) => String(p.user_id) === String(id));
        form.setData({ ...form.data, member_id: id, amount: toCedisInput(next?.outstanding_pesewas) });
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('cash.store'), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <FormModal show onClose={onClose} title="Record hand-in" onSubmit={submit} processing={form.processing} submitLabel="Record hand-in">
            <Field label="Team member" htmlFor="handin_member" error={form.errors.member_id}>
                <SelectInput id="handin_member" className="block w-full" value={form.data.member_id} onChange={(e) => pickMember(e.target.value)}>
                    {owing.map((p) => (
                        <option key={p.user_id} value={p.user_id}>
                            {p.name} (owes {formatGhs(p.outstanding_pesewas)})
                        </option>
                    ))}
                </SelectInput>
            </Field>

            <Field
                label="Amount received (GHS)"
                htmlFor="handin_amount"
                error={form.errors.amount}
                hint={left === null ? null : left > 0 ? `${formatGhs(left)} still to hand in afterwards` : left === 0 ? 'Clears the balance' : null}
            >
                <TextInput id="handin_amount" inputMode="decimal" placeholder="0.00" className="block w-full" value={form.data.amount}
                    onChange={(e) => form.setData('amount', e.target.value)} isFocused />
            </Field>

            <fieldset>
                <legend className="block text-sm font-medium text-gray-700">How was it handed in?</legend>
                <div className="mt-1 inline-flex gap-0.5 rounded-lg bg-gray-200/60 p-0.5 text-sm">
                    {[['cash', 'Cash'], ['mobile_money', 'Mobile money']].map(([value, label]) => (
                        <label key={value}
                            className={'cursor-pointer rounded-md px-3 py-1.5 font-medium ' +
                                (form.data.method === value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900')}>
                            <input type="radio" name="method" value={value} className="sr-only" checked={form.data.method === value}
                                onChange={() => form.setData('method', value)} />
                            {label}
                        </label>
                    ))}
                </div>
            </fieldset>

            {form.data.method === 'mobile_money' && (
                <Field label="Transaction ID" htmlFor="handin_reference" error={form.errors.reference}
                    hint="From the mobile money confirmation message">
                    <TextInput id="handin_reference" className="block w-full" value={form.data.reference}
                        onChange={(e) => form.setData('reference', e.target.value)} />
                </Field>
            )}

            <Field label="Note (optional)" htmlFor="handin_note" error={form.errors.note}>
                <TextInput id="handin_note" className="block w-full" value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)} />
            </Field>
        </FormModal>
    );
}

function CancelModal({ handIn, onClose }) {
    const form = useForm({ reason: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('cash.cancel', handIn.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <FormModal show onClose={onClose} title={`Cancel hand-in #${handIn.id}`} onSubmit={submit}
            processing={form.processing} submitLabel="Cancel hand-in">
            <p className="text-sm text-gray-600">
                {formatGhs(handIn.amount_pesewas)} from {handIn.member} on {formatDateTime(handIn.created_at)}. {handIn.member} will
                owe this amount again. The original record is kept and marked cancelled.
            </p>
            <Field label="Reason (required)" htmlFor="cancel_reason" error={form.errors.reason}>
                <TextInput id="cancel_reason" className="block w-full" value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)} isFocused />
            </Field>
        </FormModal>
    );
}
