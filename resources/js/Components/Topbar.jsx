import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { FiBell, FiLogOut } from 'react-icons/fi';

export default function Topbar() {
    const { auth } = usePage().props;
    const user = auth?.user;
    const notifications = auth?.notifications ?? { unread_count: 0, items: [] };

    const [menuOpen, setMenuOpen] = useState(false);
    const [bellOpen, setBellOpen] = useState(false);
    const menuRef = useRef(null);
    const bellRef = useRef(null);

    useEffect(() => {
        function onClick(e) {
            if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false);
            if (bellRef.current && !bellRef.current.contains(e.target)) setBellOpen(false);
        }
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    function logout() {
        router.post('/logout');
    }

    function openNotification(item) {
        setBellOpen(false);
        router.post(`/notifications/${item.id}/read`);
    }

    const initial = user?.name?.charAt(0)?.toUpperCase() ?? '?';
    const unread = notifications.unread_count ?? 0;

    return (
        <header className="flex h-16 items-center justify-end gap-6 border-b border-border bg-surface px-8">
            <div className="relative" ref={bellRef}>
                <button onClick={() => setBellOpen((v) => !v)} className="relative flex items-center">
                    <FiBell className="h-5 w-5 text-text-muted" />
                    {unread > 0 && (
                        <span className="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </button>

                {bellOpen && (
                    <div className="absolute right-0 top-full z-10 mt-2 w-80 overflow-hidden rounded-lg border border-border bg-surface shadow-md">
                        <div className="border-b border-border px-4 py-2 text-xs font-semibold uppercase tracking-wide text-text-muted">
                            Notifikasi
                        </div>
                        <div className="max-h-96 overflow-y-auto">
                            {notifications.items.length === 0 && (
                                <p className="px-4 py-6 text-center text-sm text-text-muted">Tidak ada notifikasi.</p>
                            )}
                            {notifications.items.map((item) => (
                                <button
                                    key={item.id}
                                    onClick={() => openNotification(item)}
                                    className={`block w-full border-b border-border px-4 py-3 text-left text-sm last:border-b-0 hover:bg-bg ${item.read_at ? 'text-text-muted' : 'text-text'}`}
                                >
                                    {!item.read_at && <span className="mr-2 inline-block h-2 w-2 rounded-full bg-info align-middle" />}
                                    {item.message}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            <div className="relative" ref={menuRef}>
                <button onClick={() => setMenuOpen((v) => !v)} className="flex items-center gap-3">
                    <span className="flex h-9 w-9 items-center justify-center rounded-full bg-navy text-sm font-semibold text-white">
                        {initial}
                    </span>
                    <span className="text-left leading-tight">
                        <span className="block text-sm font-medium text-text">{user?.name}</span>
                        <span className="block text-xs text-text-muted">{user?.role}</span>
                    </span>
                </button>

                {menuOpen && (
                    <div className="absolute right-0 top-full z-10 mt-2 w-40 rounded-lg border border-border bg-surface py-1 shadow-md">
                        <button
                            onClick={logout}
                            className="flex w-full items-center gap-2 px-3 py-2 text-sm text-text-muted hover:bg-bg hover:text-danger"
                        >
                            <FiLogOut className="h-4 w-4" />
                            Logout
                        </button>
                    </div>
                )}
            </div>
        </header>
    );
}
