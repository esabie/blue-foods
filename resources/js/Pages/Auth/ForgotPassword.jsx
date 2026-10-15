import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot password" />

            <h1 className="text-3xl font-semibold tracking-tight text-gray-900">Forgot your password?</h1>
            <p className="mt-2 text-sm text-gray-500">Enter your account email and we will send you a link to choose a new password.</p>

            {status && (
                <div className="mt-6 rounded-md bg-green-50 px-3 py-2 text-sm font-medium text-green-700">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-8">
                <InputLabel htmlFor="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="mt-1 block w-full py-2.5"
                    autoComplete="username"
                    isFocused={true}
                    onChange={(e) => setData('email', e.target.value)}
                />
                <InputError message={errors.email} className="mt-2" />

                <PrimaryButton className="mt-6 w-full py-3" disabled={processing}>
                    Email reset link
                </PrimaryButton>
            </form>

            <p className="mt-6 text-center text-sm text-gray-500">
                Remembered it?{' '}
                <Link href={route('login')} className="font-medium text-sky-700 hover:text-sky-900 hover:underline">
                    Back to log in
                </Link>
            </p>
        </GuestLayout>
    );
}
