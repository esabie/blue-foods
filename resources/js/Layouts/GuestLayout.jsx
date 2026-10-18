import ApplicationLogo from '@/Components/ApplicationLogo';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen bg-white">
            <div className="relative hidden w-3/5 lg:block xl:w-2/3">
                <img
                    src="/images/login-team.jpg"
                    alt="Two colleagues looking at a tablet together"
                    className="absolute inset-0 h-full w-full object-cover object-center"
                />
            </div>

            <div className="flex w-full items-center justify-center px-8 py-16 sm:px-16 lg:w-2/5 lg:px-12 xl:w-1/3 xl:px-16 2xl:px-20">
                <div className="w-full max-w-sm">
                    {/* Plain link: the public site is not an Inertia page. */}
                    <a href="/" className="mb-12 inline-flex items-center gap-2 text-sky-800">
                        <ApplicationLogo className="h-9 w-9" />
                        <span className="text-lg font-semibold tracking-tight">Blue Foods</span>
                    </a>
                    {children}
                </div>
            </div>
        </div>
    );
}
