import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import NotificationBell from '@/Components/NotificationBell';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

// Sections of [heading, links]; each link is [label, route name, pattern that marks it active].
const NAV = {
    management: [
        [null, [['Dashboards', 'dashboards.overview', 'dashboards.*']]],
        ['Stock', [['Stock', 'stock.index'], ['Movements', 'stock.movements'], ['Returns', 'returns.index']]],
        ['Sales', [['Online orders', 'orders.index'], ['Sales', 'sales.index'], ['Cash', 'cash.index'], ['Reports', 'reports.show', 'reports.*']]],
        ['Admin', [['Products', 'products.index'], ['Users', 'users.index'], ['Website', 'website.edit'], ['Audit log', 'audit.index']]],
    ],
    warehouse: [
        [null, [['Dashboard', 'dashboards.stock']]],
        ['Stock', [['Stock', 'stock.index'], ['Movements', 'stock.movements'], ['Returns', 'returns.index']]],
        ['Online shop', [['Online orders', 'orders.index']]],
    ],
    marketing: [
        [null, [['My stock', 'member.home'], ['My sales', 'member.sales']]],
    ],
};

function initials(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
}

function Sidebar({ user }) {
    const sections = NAV[user.role] ?? [];
    const isActive = ([, name, pattern]) => route().current(pattern ?? name);

    return (
        <div className="flex h-full flex-col bg-sky-950">
            <Link href={route('dashboard')} className="flex h-14 shrink-0 items-center gap-2 px-5 text-white">
                <ApplicationLogo className="h-7 w-7 text-sky-300" />
                <span className="font-semibold tracking-tight">Blue Foods</span>
            </Link>

            <nav className="flex-1 space-y-5 overflow-y-auto px-3 pb-4 pt-8">
                {sections.map(([heading, links], i) => (
                    <div key={heading ?? i}>
                        {heading && <p className="mb-1 px-3 text-xs font-medium text-sky-200/50">{heading}</p>}
                        <div className="space-y-0.5">
                            {links.map((link) => (
                                <NavLink key={link[1]} href={route(link[1])} active={isActive(link)}>
                                    {link[0]}
                                </NavLink>
                            ))}
                        </div>
                    </div>
                ))}
            </nav>
        </div>
    );
}

function UserMenu({ user }) {
    return (
        <Dropdown>
            <Dropdown.Trigger>
                <button
                    type="button"
                    className="flex items-center gap-2 rounded-full py-1 pe-1 ps-1 text-sm transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 sm:pe-2"
                >
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-800 text-xs font-semibold text-white">
                        {initials(user.name)}
                    </span>
                    <span className="hidden text-left leading-tight sm:block">
                        <span className="block font-medium text-gray-900">{user.name}</span>
                        <span className="block text-xs text-gray-500">{user.role_label}</span>
                    </span>
                    <svg className="hidden h-4 w-4 text-gray-400 sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                    </svg>
                </button>
            </Dropdown.Trigger>
            <Dropdown.Content>
                <div className="border-b border-gray-100 px-4 py-2 sm:hidden">
                    <p className="text-sm font-medium text-gray-900">{user.name}</p>
                    <p className="text-xs text-gray-500">{user.role_label}</p>
                </div>
                <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                <Dropdown.Link href={route('logout')} method="post" as="button">
                    Log out
                </Dropdown.Link>
            </Dropdown.Content>
        </Dropdown>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const { auth, flash, errors } = usePage().props;
    const pageError = errors?.return || errors?.user || errors?.order || errors?.payment;
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        if (!menuOpen) return;
        const close = (e) => e.key === 'Escape' && setMenuOpen(false);
        document.addEventListener('keydown', close);
        return () => document.removeEventListener('keydown', close);
    }, [menuOpen]);

    return (
        <div className="min-h-screen bg-gray-50">
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-60 lg:block">
                <Sidebar user={auth.user} />
            </aside>

            {menuOpen && (
                <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true">
                    <div className="absolute inset-0 bg-gray-900/50" onClick={() => setMenuOpen(false)} />
                    <div className="absolute inset-y-0 left-0 w-64 max-w-[80%] shadow-xl">
                        <Sidebar user={auth.user} />
                    </div>
                </div>
            )}

            <div className="lg:pl-60">
                <div className="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        onClick={() => setMenuOpen(true)}
                        aria-label="Open menu"
                        className="-ms-2 rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none lg:hidden"
                    >
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <Link href={route('dashboard')} className="flex items-center gap-2 text-sky-900 lg:hidden">
                        <ApplicationLogo className="h-7 w-7 text-sky-700" />
                        <span className="font-semibold tracking-tight">Blue Foods</span>
                    </Link>

                    <div className="ms-auto flex items-center gap-1 sm:gap-2">
                        <NotificationBell />
                        <UserMenu user={auth.user} />
                    </div>
                </div>

                {header && <header className="mx-auto max-w-7xl px-4 pt-6 sm:px-6 sm:pt-8 lg:px-8">{header}</header>}

                {(flash?.success || pageError) && (
                    <div className="mx-auto mt-4 max-w-7xl px-2 sm:px-6 lg:px-8">
                        {flash?.success && (
                            <div className="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">
                                {flash.success}
                            </div>
                        )}
                        {pageError && (
                            <div className="mt-2 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                                {pageError}
                            </div>
                        )}
                    </div>
                )}

                <main>{children}</main>
            </div>
        </div>
    );
}
