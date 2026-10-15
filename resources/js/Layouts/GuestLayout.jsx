import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-gray-50 px-4 pt-10 sm:justify-center sm:pt-0">
            <Link href="/" className="flex items-center gap-2 text-sky-800">
                <ApplicationLogo className="h-10 w-10" />
                <span className="text-xl font-semibold tracking-tight">Blue Foods</span>
            </Link>

            <div className="mt-6 w-full overflow-hidden bg-white px-6 py-6 ring-1 ring-gray-200 sm:max-w-md sm:rounded-xl">
                {children}
            </div>
        </div>
    );
}
