import Field from '@/Components/Field';
import PageShell, { Badge, Card } from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import { formatDateTime } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const textarea =
    'block w-full rounded-md border-gray-300 font-sans text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500';

function countPastedPhones(raw) {
    if (!raw?.trim()) return 0;
    return raw
        .split(/[\n\r,;]+/)
        .map((p) => p.trim())
        .filter(Boolean).length;
}

export default function BulkSmsCreate({
    staff_audiences,
    public_audiences,
    people,
    recent,
    max_length,
    max_public,
}) {
    const [channel, setChannel] = useState('staff');
    const form = useForm({
        audience: 'marketing',
        message: '',
        user_ids: [],
        phones: '',
    });

    const switchChannel = (next) => {
        setChannel(next);
        form.setData({
            ...form.data,
            audience: next === 'staff' ? 'marketing' : 'public',
            user_ids: [],
            phones: next === 'staff' ? '' : form.data.phones,
        });
        form.clearErrors();
    };

    const selectedCount = useMemo(() => {
        if (form.data.audience === 'selected') return form.data.user_ids.length;
        if (form.data.audience === 'public') return countPastedPhones(form.data.phones);
        const list = channel === 'staff' ? staff_audiences : public_audiences;
        return list.find((a) => a.value === form.data.audience)?.count ?? 0;
    }, [channel, staff_audiences, public_audiences, form.data]);

    const toggleUser = (id) => {
        const ids = form.data.user_ids.includes(id)
            ? form.data.user_ids.filter((x) => x !== id)
            : [...form.data.user_ids, id];
        form.setData('user_ids', ids);
    };

    const submit = (e) => {
        e.preventDefault();
        if (selectedCount < 1 || !form.data.message.trim()) return;

        const where =
            form.data.audience === 'public'
                ? `${selectedCount} phone ${selectedCount === 1 ? 'number' : 'numbers'}`
                : form.data.audience === 'selected'
                  ? `${selectedCount} selected ${selectedCount === 1 ? 'person' : 'people'}`
                  : (channel === 'staff' ? staff_audiences : public_audiences).find((a) => a.value === form.data.audience)
                        ?.label ?? 'recipients';

        if (!confirm(`Send this text to ${where}?`)) return;
        form.post(route('bulk-sms.store'), { preserveScroll: true });
    };

    const length = form.data.message?.length ?? 0;
    const remaining = max_length - length;
    const segments = length === 0 ? 0 : Math.ceil(length / 160);
    const canSend = selectedCount > 0 && form.data.message.trim().length > 0 && !form.processing;

    return (
        <PageShell title="Bulk SMS">
            <div className="flex flex-wrap gap-2">
                <ChannelTab active={channel === 'staff'} onClick={() => switchChannel('staff')}>
                    Staff
                </ChannelTab>
                <ChannelTab active={channel === 'public'} onClick={() => switchChannel('public')}>
                    Public / customers
                </ChannelTab>
            </div>

            <form onSubmit={submit} className="grid gap-4 lg:grid-cols-5">
                <div className="space-y-4 lg:col-span-3">
                    <Card title={channel === 'staff' ? 'Staff recipients' : 'Public recipients'}>
                        <div className="space-y-2 p-4">
                            {(channel === 'staff' ? staff_audiences : public_audiences).map((a) => (
                                <AudienceOption
                                    key={a.value}
                                    option={a}
                                    checked={form.data.audience === a.value}
                                    onChange={() => form.setData('audience', a.value)}
                                />
                            ))}
                            {(form.errors.audience || form.errors.phones) && (
                                <p className="text-sm text-red-600">{form.errors.audience || form.errors.phones}</p>
                            )}
                        </div>

                        {form.data.audience === 'selected' && (
                            <div className="border-t border-gray-100 px-4 py-3">
                                <div className="mb-2 flex items-center justify-between text-sm">
                                    <span className="text-gray-600">People with a phone on file</span>
                                    <button
                                        type="button"
                                        className="font-medium text-sky-700 hover:underline"
                                        onClick={() =>
                                            form.setData(
                                                'user_ids',
                                                form.data.user_ids.length === people.length ? [] : people.map((p) => p.id),
                                            )
                                        }
                                    >
                                        {form.data.user_ids.length === people.length ? 'Clear all' : 'Select all'}
                                    </button>
                                </div>
                                <ul className="max-h-56 space-y-2 overflow-y-auto">
                                    {people.length === 0 && <li className="text-sm text-gray-500">No staff phones on file.</li>}
                                    {people.map((p) => (
                                        <label key={p.id} className="flex cursor-pointer items-center gap-3 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={form.data.user_ids.includes(p.id)}
                                                onChange={() => toggleUser(p.id)}
                                                className="rounded border-gray-300 text-sky-700 focus:ring-sky-500"
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="font-medium text-gray-900">{p.name}</span>
                                                <span className="text-gray-500"> · {p.role_label}</span>
                                            </span>
                                            <span className="shrink-0 tabular-nums text-gray-500">{p.phone}</span>
                                        </label>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {form.data.audience === 'public' && (
                            <div className="border-t border-gray-100 p-4">
                                <Field
                                    label="Phone numbers"
                                    htmlFor="bulk_phones"
                                    error={form.errors.phones}
                                    hint={`One per line, or separated by commas. Up to ${max_public}. Ghana numbers like 024 123 4567 are fine.`}
                                >
                                    <textarea
                                        id="bulk_phones"
                                        rows={8}
                                        value={form.data.phones}
                                        onChange={(e) => form.setData('phones', e.target.value)}
                                        className={textarea + ' font-mono'}
                                        placeholder={'024 123 4567\n055 987 6543\n+233201112233'}
                                    />
                                </Field>
                            </div>
                        )}

                        {form.data.audience === 'online_customers' && (
                            <div className="border-t border-gray-100 px-4 py-3 text-sm text-gray-600">
                                Sends to unique phone numbers from past online shop orders
                                {typeof public_audiences.find((a) => a.value === 'online_customers')?.count === 'number'
                                    ? ` (${public_audiences.find((a) => a.value === 'online_customers').count} on file)`
                                    : ''}
                                . Duplicates are removed automatically.
                            </div>
                        )}
                    </Card>

                    <Card title="Message">
                        <div className="space-y-3 p-4">
                            <Field
                                label="Text message"
                                htmlFor="bulk_message"
                                error={form.errors.message}
                                hint={`${remaining} characters left · ${segments} SMS segment${segments === 1 ? '' : 's'}`}
                            >
                                <textarea
                                    id="bulk_message"
                                    rows={6}
                                    maxLength={max_length}
                                    value={form.data.message}
                                    onChange={(e) => form.setData('message', e.target.value)}
                                    className={textarea}
                                    placeholder={
                                        channel === 'staff'
                                            ? 'e.g. Hand in cash by Friday afternoon. Thank you.'
                                            : 'e.g. Fresh coconut oil is back in stock at Blue Foods. Order on our website or WhatsApp us.'
                                    }
                                />
                            </Field>

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm text-gray-600">
                                    Ready for{' '}
                                    <span className="font-semibold text-gray-900">
                                        {selectedCount} {selectedCount === 1 ? 'recipient' : 'recipients'}
                                    </span>
                                </p>
                                <PrimaryButton disabled={!canSend}>
                                    {form.processing ? 'Queuing…' : `Send to ${selectedCount || 0}`}
                                </PrimaryButton>
                            </div>
                        </div>
                    </Card>
                </div>

                <div className="space-y-4 lg:col-span-2">
                    <Card title="Preview">
                        <div className="p-4">
                            <div className="mx-auto max-w-[220px] rounded-[1.5rem] bg-sky-950 p-3 shadow-sm ring-1 ring-sky-900">
                                <div className="rounded-2xl bg-white px-3 py-3 text-sm leading-relaxed text-gray-900">
                                    {form.data.message.trim() ? (
                                        form.data.message
                                    ) : (
                                        <span className="text-gray-400">Your message will appear here.</span>
                                    )}
                                </div>
                                <p className="mt-2 text-center text-[11px] text-sky-200/80">
                                    {selectedCount > 0
                                        ? `${selectedCount} ${selectedCount === 1 ? 'phone' : 'phones'}`
                                        : 'No recipients yet'}
                                </p>
                            </div>
                        </div>
                    </Card>

                    <Card title="Recently sent">
                        {recent.length === 0 ? (
                            <p className="p-4 text-sm text-gray-500">Nothing sent yet.</p>
                        ) : (
                            <ul className="divide-y divide-gray-100 text-sm">
                                {recent.map((r) => (
                                    <li key={r.id} className="px-4 py-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <div className="font-medium text-gray-900">{r.audience}</div>
                                                <div className="truncate text-gray-500">{r.message}</div>
                                            </div>
                                            <Badge tone={r.channel === 'public' ? 'amber' : 'blue'}>
                                                {r.count}
                                            </Badge>
                                        </div>
                                        <div className="mt-1 text-xs text-gray-400">
                                            {r.by} · {formatDateTime(r.sent_at)}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </form>
        </PageShell>
    );
}

function ChannelTab({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={
                'rounded-md px-4 py-2 text-sm font-medium transition ' +
                (active
                    ? 'bg-sky-800 text-white shadow-sm'
                    : 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50')
            }
        >
            {children}
        </button>
    );
}

function AudienceOption({ option, checked, onChange }) {
    return (
        <label
            className={
                'flex cursor-pointer items-center justify-between gap-3 rounded-lg border px-3 py-2.5 transition ' +
                (checked ? 'border-sky-600 bg-sky-50' : 'border-gray-200 hover:border-gray-300')
            }
        >
            <span className="flex items-center gap-3">
                <input
                    type="radio"
                    name="audience"
                    value={option.value}
                    checked={checked}
                    onChange={onChange}
                    className="border-gray-300 text-sky-700 focus:ring-sky-500"
                />
                <span className="font-medium text-gray-900">{option.label}</span>
            </span>
            {option.count !== null && option.count !== undefined && (
                <span className={'text-sm tabular-nums ' + (option.count === 0 ? 'text-amber-700' : 'text-gray-500')}>
                    {option.count === 0 ? 'None' : option.count}
                </span>
            )}
        </label>
    );
}
