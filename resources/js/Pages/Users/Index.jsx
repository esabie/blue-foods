import ConfirmModal from '@/Components/ConfirmModal';
import Field from '@/Components/Field';
import FormModal from '@/Components/FormModal';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import RecordCard, { CardAction, CardGrid, DetailLine, Initials, ListToolbar, matches, MiniStats, NoMatches, NotProvided, Pill } from '@/Components/RecordCard';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { formatDate, formatGhs, timeAgo } from '@/lib/format';
import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const ROLE_TONES = { management: 'sky', warehouse: 'amber', marketing: 'emerald' };

export default function UsersIndex({ users, roles }) {
    const me = usePage().props.auth.user;
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);
    const [resetting, setResetting] = useState(null);
    const [disabling, setDisabling] = useState(null);
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState('all');

    const disabledCount = users.filter((u) => u.status === 'disabled').length;
    const tabs = [
        { key: 'all', label: 'All', count: users.length },
        ...roles.map((r) => ({ key: r.value, label: r.label, count: users.filter((u) => u.role === r.value && u.status === 'active').length })),
        ...(disabledCount > 0 ? [{ key: 'disabled', label: 'Disabled', count: disabledCount }] : []),
    ];
    const shown = users.filter(
        (u) =>
            (tab === 'all' || (tab === 'disabled' ? u.status === 'disabled' : u.role === tab && u.status === 'active')) &&
            matches(search, u.name, u.email, u.phone, u.phone?.replace('+233', '0')),
    );

    const enable = (user) => router.post(route('users.enable', user.id), {}, { preserveScroll: true });

    return (
        <PageShell
            title="Users"
            actions={
                <PrimaryButton onClick={() => setCreating(true)} className="gap-2">
                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                    New user
                </PrimaryButton>
            }
        >
            <ListToolbar search={search} onSearch={setSearch} placeholder="Search name, phone or email" tabs={tabs} tab={tab} onTab={setTab} />

            {shown.length === 0 ? (
                <NoMatches onClear={search ? () => setSearch('') : null}>No users match.</NoMatches>
            ) : (
                <CardGrid>
                    {shown.map((u) => (
                        <RecordCard
                            key={u.id}
                            muted={u.status === 'disabled'}
                            media={<Initials name={u.name} tone={u.status === 'active' ? ROLE_TONES[u.role] : 'slate'} />}
                            pills={
                                <>
                                    <Pill tone={u.status === 'active' ? 'green' : 'gray'}>{u.status}</Pill>
                                    {u.locked && <Pill tone="red">Locked</Pill>}
                                </>
                            }
                            date={u.created_at && `Added ${formatDate(u.created_at)}`}
                            title={u.name}
                            subtitle={u.id === me.id ? `${u.role_label} · You` : u.role_label}
                            actions={
                                <>
                                    <CardAction onClick={() => setEditing(u)}>Edit</CardAction>
                                    <CardAction onClick={() => setResetting(u)}>{u.locked ? 'Unlock and reset password' : 'Reset password'}</CardAction>
                                    {u.id !== me.id &&
                                        (u.status === 'active' ? (
                                            <CardAction tone="muted" onClick={() => setDisabling(u)}>Disable</CardAction>
                                        ) : (
                                            <CardAction onClick={() => enable(u)}>Enable</CardAction>
                                        ))}
                                </>
                            }
                        >
                            <div className="space-y-2">
                                {u.email ? <DetailLine icon="envelope" href={`mailto:${u.email}`}>{u.email}</DetailLine> : <DetailLine icon="envelope"><NotProvided /></DetailLine>}
                                {u.phone ? <DetailLine icon="phone" href={`tel:${u.phone}`}>{u.phone}</DetailLine> : <DetailLine icon="phone"><NotProvided /></DetailLine>}
                                <DetailLine icon="clock">
                                    {u.last_login_at ? `Signed in ${timeAgo(u.last_login_at)}` : <span className="text-gray-400">Not signed in yet</span>}
                                    {u.team && <span className="text-gray-400"> · {u.team.last_sale_at ? `last sale ${timeAgo(u.team.last_sale_at)}` : 'no sales yet'}</span>}
                                </DetailLine>
                            </div>
                            {u.team && (
                                <MiniStats
                                    items={[
                                        { label: 'Stock held', value: `${u.team.units_held.toLocaleString('en-GH')} units`, tone: u.team.units_held === 0 ? 'muted' : null },
                                        {
                                            label: u.team.cash_overdue ? 'Cash overdue' : 'Cash to hand in',
                                            value: formatGhs(u.team.cash_owed_pesewas),
                                            tone: u.team.cash_overdue ? 'red' : u.team.cash_owed_pesewas === 0 ? 'muted' : null,
                                        },
                                    ]}
                                />
                            )}
                        </RecordCard>
                    ))}
                </CardGrid>
            )}

            <UserModal show={creating} roles={roles} onClose={() => setCreating(false)} />
            {editing && <UserModal show user={editing} roles={roles} onClose={() => setEditing(null)} />}
            {resetting && <ResetPasswordModal user={resetting} onClose={() => setResetting(null)} />}
            {disabling && (
                <ConfirmModal
                    show
                    danger
                    title={`Disable ${disabling.name}?`}
                    confirmLabel="Disable account"
                    onClose={() => setDisabling(null)}
                    onConfirm={(done) => router.post(route('users.disable', disabling.id), {}, { preserveScroll: true, onFinish: done })}
                >
                    They will be signed out and will not be able to sign in. Everything they recorded is kept, and you can enable the account again at any time.
                    {disabling.team?.units_held > 0 && (
                        <p className="mt-2 font-medium text-amber-800">They still hold {disabling.team.units_held.toLocaleString('en-GH')} units of stock.</p>
                    )}
                    {disabling.team?.cash_owed_pesewas > 0 && (
                        <p className="mt-1 font-medium text-amber-800">They still have {formatGhs(disabling.team.cash_owed_pesewas)} to hand in.</p>
                    )}
                </ConfirmModal>
            )}
        </PageShell>
    );
}

function UserModal({ show, user, roles, onClose }) {
    const form = useForm(
        user
            ? { name: user.name, email: user.email ?? '', phone: user.phone ?? '' }
            : { name: '', email: '', phone: '', role: 'marketing', password: '' },
    );

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        user ? form.put(route('users.update', user.id), options) : form.post(route('users.store'), options);
    };

    return (
        <FormModal show={show} onClose={onClose} title={user ? `Edit ${user.name}` : 'New user'} onSubmit={submit}
            processing={form.processing} submitLabel={user ? 'Save' : 'Create account'}>
            <Field label="Full name" htmlFor="name" error={form.errors.name}>
                <TextInput id="name" className="block w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            </Field>
            <div className="grid grid-cols-2 gap-4">
                <Field label="Email" htmlFor="email" error={form.errors.email} hint="Used to sign in and reset the password">
                    <TextInput id="email" type="email" className="block w-full" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                </Field>
                <Field label="Phone (optional)" htmlFor="phone" error={form.errors.phone} hint="e.g. 024 123 4567">
                    <TextInput id="phone" type="tel" className="block w-full" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                </Field>
            </div>
            {!user && (
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Role" htmlFor="role" error={form.errors.role}>
                        <SelectInput id="role" className="block w-full" value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                            {roles.map((r) => (
                                <option key={r.value} value={r.value}>{r.label}</option>
                            ))}
                        </SelectInput>
                    </Field>
                    <Field label="Temporary password" htmlFor="password" error={form.errors.password} hint="At least 8 characters">
                        <TextInput id="password" type="text" autoComplete="new-password" className="block w-full" value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)} />
                    </Field>
                </div>
            )}
        </FormModal>
    );
}

function ResetPasswordModal({ user, onClose }) {
    const form = useForm({ password: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('users.reset-password', user.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <FormModal show onClose={onClose} title={`Reset password for ${user.name}`} onSubmit={submit}
            processing={form.processing} submitLabel="Reset password">
            <p className="text-sm text-gray-600">This also unlocks the account if it was locked after failed sign-ins.</p>
            <Field label="New password" htmlFor="reset_password" error={form.errors.password} hint="At least 8 characters">
                <TextInput id="reset_password" type="text" autoComplete="new-password" className="block w-full" value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)} isFocused />
            </Field>
        </FormModal>
    );
}
