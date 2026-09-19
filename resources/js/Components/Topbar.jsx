import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { FiBell, FiMenu } from 'react-icons/fi';

const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

export default function Topbar({ menuOpen, onMenuToggle }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const notifications = auth?.notifications ?? { unread_count: 0, items: [] };

    const [bellOpen, setBellOpen] = useState(false);
    const bellRef = useRef(null);

    useEffect(() => {
        function onClick(e) {
            if (bellRef.current && !bellRef.current.contains(e.target)) setBellOpen(false);
        }
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    const unread = notifications.unread_count ?? 0;
    const firstName = user?.name?.split(' ')[0] ?? '';
    const now = new Date();
    const today = `${DAYS[now.getDay()]}, ${now.getDate()} ${MONTHS[now.getMonth()]} ${now.getFullYear()}`;

    return (
        <header className="flex shrink-0 items-center justify-between gap-2 rounded-2xl border border-border bg-surface px-3 py-3 sm:px-5 shadow-sm">
            <button type="button" onClick={onMenuToggle} aria-label="Buka menu" aria-expanded={menuOpen} aria-controls="mobile-navigation" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-bg text-text lg:hidden"><FiMenu className="h-5 w-5" /></button>
            <div className="min-w-0 flex-1 break-words leading-tight">
                <h2 className="text-[15px] font-bold tracking-tight text-text">Halo, {firstName} 👋</h2>
                <p className="text-xs font-medium text-text-muted">{today}</p>
            </div>

            <div className="relative shrink-0" ref={bellRef}>
                <button
                    onClick={() => setBellOpen((v) => !v)}
                    className={`relative flex h-10 w-10 items-center justify-center rounded-full transition ${
                        bellOpen ? 'bg-navy text-white' : 'bg-bg text-text-muted hover:bg-border hover:text-text'
                    }`}
                    aria-label="Notifikasi"
                >
                    <FiBell className="h-[18px] w-[18px]" />
                    {unread > 0 && (
                        <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white ring-2 ring-surface">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </button>

                {bellOpen && (
                    <div className="absolute right-0 top-full z-20 mt-3 w-[min(22rem,calc(100vw-3rem))] overflow-hidden rounded-2xl border border-border bg-surface shadow-lg">
                        <div className="flex items-center justify-between border-b border-border px-4 py-3">
                            <span className="text-sm font-bold tracking-tight text-text">Notifikasi</span>
                            {unread > 0 && <span className="rounded-full bg-primary-soft px-2 py-0.5 text-[11px] font-bold text-primary-strong">{unread} baru</span>}
                        </div>
                        <div className="max-h-[min(24rem,60dvh)] overflow-y-auto">
                            {notifications.items.length === 0 && (
                                <p className="px-4 py-10 text-center text-sm text-text-muted">Belum ada notifikasi.</p>
                            )}
                            {notifications.items.map((item) => (
                                <button
                                    key={item.id}
                                    onClick={() => { setBellOpen(false); router.post(`/notifications/${item.id}/read`); }}
                                    className={`flex w-full gap-3 border-b border-border px-4 py-3 text-left text-sm transition last:border-b-0 hover:bg-bg ${
                                        item.read_at ? 'text-text-muted' : 'text-text'
                                    }`}
                                >
                                    <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${item.read_at ? 'bg-transparent' : 'bg-primary'}`} />
                                    <span className="min-w-0 break-words leading-snug">{item.message}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </header>
    );
}
