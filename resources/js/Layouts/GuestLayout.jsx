import ApplicationLogo from '@/Components/ApplicationLogo';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen bg-white">
            <div className="relative hidden w-3/5 lg:block xl:w-2/3">
                <img src="/images/login-farm.jpg" alt="Farmers holding baskets of fresh vegetables, plantains and yams on a farm" className="absolute inset-0 h-full w-full object-cover" />
                <div className="absolute inset-x-0 bottom-0 p-12 text-white">
                    {/* <p className="max-w-md text-3xl font-semibold leading-tight tracking-tight">From the farm to your table.</p>
                    < className="max-w-"
                    <p className="mt-3 max-w-md text-sm text-sky-100/80">Fresh farm goods, handled with care at every step.</p> */}
                </div>
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
