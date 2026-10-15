import Field from '@/Components/Field';
import FormModal from '@/Components/FormModal';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import RecordCard, { CardAction, CardGrid, Initials, NotProvided, Pill } from '@/Components/RecordCard';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import { formatDate } from '@/lib/format';
import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function UsersIndex({ users, roles }) {
    const me = usePage().props.auth.user;
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);
    const [resetting, setResetting] = useState(null);

    const toggle = (user) => {
        const disabling = user.status === 'active';
        if (disabling && !confirm(`Disable ${user.name}? They will not be able to sign in. Their history is kept.`)) {
            return;
        }
        router.post(route(disabling ? 'users.disable' : 'users.enable', user.id), {}, { preserveScroll: true });
    };

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
            <CardGrid>
                {users.map((u) => (
                    <RecordCard
                        key={u.id}
                        muted={u.status === 'disabled'}
                        media={<Initials name={u.name} />}
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
                                <CardAction onClick={() => setResetting(u)}>Reset password</CardAction>
                                {u.id !== me.id && (
                                    <CardAction tone="muted" onClick={() => toggle(u)}>{u.status === 'active' ? 'Disable' : 'Enable'}</CardAction>
                                )}
                            </>
                        }
                    >
                        <p className="break-all">{u.email ?? <NotProvided />}</p>
                        <p>{u.phone ?? <NotProvided />}</p>
                    </RecordCard>
                ))}
            </CardGrid>

            <UserModal show={creating} roles={roles} onClose={() => setCreating(false)} />
            {editing && <UserModal show user={editing} roles={roles} onClose={() => setEditing(null)} />}
            {resetting && <ResetPasswordModal user={resetting} onClose={() => setResetting(null)} />}
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
                <Field label="Phone" htmlFor="phone" error={form.errors.phone} hint="e.g. 024 123 4567">
                    <TextInput id="phone" type="tel" className="block w-full" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                </Field>
                <Field label="Email" htmlFor="email" error={form.errors.email} hint="Phone or email is required">
                    <TextInput id="email" type="email" className="block w-full" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
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
