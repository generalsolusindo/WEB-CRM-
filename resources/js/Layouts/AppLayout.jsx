import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { FiCheckCircle, FiAlertTriangle, FiX } from 'react-icons/fi';
import Sidebar from '../Components/Sidebar';
import Topbar from '../Components/Topbar';

function Flash({ tone, children, onClose }) {
    const styles = tone === 'error'
        ? 'border-danger/25 bg-danger-soft text-danger'
        : 'border-success/25 bg-success-soft text-success';
    const Icon = tone === 'error' ? FiAlertTriangle : FiCheckCircle;

    return (
        <div className={`mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm font-medium shadow-sm ${styles}`}>
            <Icon className="mt-0.5 h-4 w-4 shrink-0" />
            <span className="min-w-0 flex-1 break-words">{children}</span>
            <button onClick={onClose} className="opacity-60 transition hover:opacity-100"><FiX className="h-4 w-4" /></button>
        </div>
    );
}

export default function AppLayout({ children }) {
    const { flash } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef(null);

    useEffect(() => {
        const dialog = menuRef.current;
        if (menuOpen && !dialog.open) dialog.showModal();
        if (!menuOpen && dialog.open) dialog.close();
    }, [menuOpen]);

    useEffect(() => {
        const desktop = window.matchMedia('(min-width: 1024px)');
        const closeOnDesktop = () => { if (desktop.matches) setMenuOpen(false); };
        desktop.addEventListener('change', closeOnDesktop);
        return () => desktop.removeEventListener('change', closeOnDesktop);
    }, []);
    const [dismissed, setDismissed] = useState({ success: false, error: false });

    useEffect(() => {
        setDismissed({ success: false, error: false });
    }, [flash?.success, flash?.error]);

    return (
        <div className="flex h-dvh min-h-0 gap-4 bg-bg p-2 sm:p-4">
            <div className="hidden min-h-0 w-[248px] shrink-0 lg:flex"><Sidebar /></div>
            <dialog
                ref={menuRef}
                id="mobile-navigation"
                aria-label="Menu utama"
                onClose={() => setMenuOpen(false)}
                onClick={(event) => { if (event.target === event.currentTarget) setMenuOpen(false); }}
                className="fixed inset-y-0 left-0 right-auto m-0 h-dvh max-h-none w-[min(20rem,calc(100vw-2rem))] max-w-none bg-transparent p-2 backdrop:bg-black/40"
            >
                <Sidebar onNavigate={() => setMenuOpen(false)} onClose={() => setMenuOpen(false)} />
            </dialog>
            <div className="flex min-w-0 flex-1 flex-col gap-4">
                <Topbar menuOpen={menuOpen} onMenuToggle={() => setMenuOpen(true)} />
                <main className="min-h-0 min-w-0 flex-1 overflow-y-auto pb-6 pr-0.5">
                    {flash?.success && !dismissed.success && (
                        <Flash tone="success" onClose={() => setDismissed((d) => ({ ...d, success: true }))}>{flash.success}</Flash>
                    )}
                    {flash?.error && !dismissed.error && (
                        <Flash tone="error" onClose={() => setDismissed((d) => ({ ...d, error: true }))}>{flash.error}</Flash>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
