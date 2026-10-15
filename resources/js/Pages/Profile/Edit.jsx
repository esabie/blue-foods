import PageShell, { Card, SURFACE } from '@/Components/PageShell';
import { usePage } from '@inertiajs/react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';

export default function Edit() {
    const user = usePage().props.auth.user;

    return (
        <PageShell title="Profile">
            <Card title="Account">
                <dl className="grid gap-4 p-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt className="text-gray-500">Name</dt>
                        <dd className="font-medium">{user.name}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-500">Role</dt>
                        <dd className="font-medium">{user.role_label}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-500">Email</dt>
                        <dd className="font-medium">{user.email ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-500">Phone</dt>
                        <dd className="font-medium">{user.phone ?? '—'}</dd>
                    </div>
                </dl>
                <p className="px-4 pb-4 text-xs text-gray-500">
                    Ask management to change your name, email or phone number.
                </p>
            </Card>

            <div className={SURFACE + ' p-4 sm:p-8'}>
                <UpdatePasswordForm className="max-w-xl" />
            </div>
        </PageShell>
    );
}
