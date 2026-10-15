import { timeAgo } from '@/lib/format';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useState } from 'react';

const POLL_MS = 30_000;

export default function NotificationBell() {
    const { unreadNotifications } = usePage().props;
    const [open, setOpen] = useState(false);
    const [unread, setUnread] = useState(unreadNotifications ?? 0);
    const [items, setItems] = useState(null);

    const apply = ({ data }) => {
        setUnread(data.unread);
        setItems(data.items);
    };

    const refresh = useCallback(() => {
        axios.get(route('notifications.index')).then(apply).catch(() => {});
    }, []);

    // Every page visit brings a fresh count; between visits, poll while the tab is visible.
    useEffect(() => setUnread(unreadNotifications ?? 0), [unreadNotifications]);

    useEffect(() => {
        const timer = setInterval(() => document.visibilityState === 'visible' && refresh(), POLL_MS);
        const onVisible = () => document.visibilityState === 'visible' && refresh();
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [refresh]);

    useEffect(() => {
        if (!open) return;
        refresh();
        const close = (e) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('keydown', close);
        return () => document.removeEventListener('keydown', close);
    }, [open, refresh]);

    const openItem = (item) => {
        setOpen(false);
        const go = () => item.url && router.visit(item.url);
        if (item.read) return go();
        axios.post(route('notifications.read', item.id)).then(apply).catch(() => {}).finally(go);
    };

    const readAll = () => axios.post(route('notifications.read-all')).then(apply).catch(() => {});

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                aria-label={unread > 0 ? `Notifications, ${unread} unread` : 'Notifications'}
                aria-expanded={open}
                className="relative rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
            >
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.6} stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
                {unread > 0 && (
                    <span className="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-none text-white">
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
                    <div className="fixed inset-x-2 top-14 z-50 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-gray-200 sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-96">
                        <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                            <h2 className="text-sm font-semibold text-gray-900">Notifications</h2>
                            {unread > 0 && (
                                <button type="button" onClick={readAll} className="text-xs font-medium text-sky-700 hover:underline">
                                    Mark all as read
                                </button>
                            )}
                        </div>

                        <div className="max-h-[28rem] overflow-y-auto">
                            {items === null ? (
                                <p className="px-4 py-8 text-center text-sm text-gray-500">Loading…</p>
                            ) : items.length === 0 ? (
                                <p className="px-4 py-8 text-center text-sm text-gray-500">You're all caught up.</p>
                            ) : (
                                <ul className="divide-y divide-gray-100">
                                    {items.map((item) => (
                                        <li key={item.id}>
                                            <button
                                                type="button"
                                                onClick={() => openItem(item)}
                                                className={'flex w-full gap-3 px-4 py-3 text-left hover:bg-gray-50 ' + (item.read ? '' : 'bg-sky-50/50')}
                                            >
                                                <span className={'mt-1.5 h-2 w-2 shrink-0 rounded-full ' + (item.read ? 'bg-transparent' : 'bg-sky-600')} />
                                                <span className="min-w-0">
                                                    <span className={'block text-sm ' + (item.read ? 'text-gray-700' : 'font-medium text-gray-900')}>{item.title}</span>
                                                    <span className="mt-0.5 block text-sm text-gray-500">{item.body}</span>
                                                    <span className="mt-1 block text-xs text-gray-400">{timeAgo(item.created_at)}</span>
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
