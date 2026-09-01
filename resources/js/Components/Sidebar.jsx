import { Link, router, usePage } from '@inertiajs/react';
import { FiLogOut } from 'react-icons/fi';
import { getMenuForUser } from '../config/menuConfig';

export default function Sidebar() {
    const { auth } = usePage().props;
    const currentUrl = usePage().url;
    const items = getMenuForUser(auth);

    function logout() {
        router.post('/logout');
    }

    return (
        <aside className="flex h-screen w-64 flex-col border-r border-border bg-surface">
            <div className="flex h-16 items-center border-b border-border px-6">
                <span className="text-lg font-bold text-navy">GS CRM</span>
            </div>

            <nav className="flex-1 space-y-1 overflow-y-auto p-3">
                {items.map((item, i) => {
                    const Icon = item.icon;
                    const isDisabled = item.href === '#';
                    const isActive = !isDisabled && currentUrl === item.href;

                    const base = 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium';

                    if (isDisabled) {
                        return (
                            <div
                                key={i}
                                className={`${base} cursor-not-allowed text-text-muted opacity-50`}
                            >
                                <Icon className="h-4 w-4 shrink-0" />
                                <span>{item.label}</span>
                            </div>
                        );
                    }

                    return (
                        <Link
                            key={i}
                            href={item.href}
                            className={`${base} ${
                                isActive
                                    ? 'bg-navy text-white'
                                    : 'text-text-muted hover:bg-bg hover:text-text'
                            }`}
                        >
                            <Icon className="h-4 w-4 shrink-0" />
                            <span>{item.label}</span>
                        </Link>
                    );
                })}
            </nav>

            <div className="border-t border-border p-3">
                <button
                    onClick={logout}
                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-text-muted hover:text-danger"
                >
                    <FiLogOut className="h-4 w-4 shrink-0" />
                    <span>Logout</span>
                </button>
            </div>
        </aside>
    );
}
