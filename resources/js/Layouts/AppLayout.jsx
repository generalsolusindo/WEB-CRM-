import { useEffect, useRef, useState } from 'react';
import Sidebar from '../Components/Sidebar';
import Topbar from '../Components/Topbar';

export default function AppLayout({ children }) {
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
                    {children}
                </main>
            </div>
        </div>
    );
}
