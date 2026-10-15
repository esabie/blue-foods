import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <h1 className="text-3xl font-semibold tracking-tight text-gray-900">Login to your account</h1>
            <p className="mt-2 text-sm text-gray-500">Enter your email below to login to your account.</p>

            {status && (
                <div className="mt-6 rounded-md bg-green-50 px-3 py-2 text-sm font-medium text-green-700">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-8">
                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full py-2.5"
                        placeholder="you@example.com"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-5">
                    <div className="flex items-center justify-between">
                        <InputLabel htmlFor="password" value="Password" />
                        <Link href={route('password.request')} className="text-sm font-medium text-sky-700 hover:text-sky-900 hover:underline">
                            Forgot password?
                        </Link>
                    </div>

                    <PasswordInput
                        id="password"
                        name="password"
                        value={data.password}
                        containerClassName="mt-1"
                        className="block w-full py-2.5"
                        placeholder="Enter your password"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-5 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600">
                            Remember me
                        </span>
                    </label>
                </div>

                <PrimaryButton className="mt-6 w-full py-3" disabled={processing}>
                    Log in
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
