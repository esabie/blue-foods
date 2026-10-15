import { Link } from '@inertiajs/react';

export default function NavLink({ active = false, className = '', children, ...props }) {
    return (
        <Link
            {...props}
            className={
                'block w-full rounded-md px-3 py-1.5 text-left text-sm font-medium transition duration-150 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300 ' +
                (active ? 'bg-white/10 text-white' : 'text-sky-100/75 hover:bg-white/5 hover:text-white') +
                ' ' +
                className
            }
        >
            {children}
        </Link>
    );
}
