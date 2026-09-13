import { Link, router, usePage } from '@inertiajs/react';
import { FiLogOut } from 'react-icons/fi';
import { getMenuForUser } from '../config/menuConfig';

const ROLE_LABEL = {
    sales: 'Sales', procurement: 'Procurement', operational: 'Operasional', technician: 'Teknisi',
    finance: 'Finance', management: 'Manajemen', administrator: 'Administrator',
    project_manager: 'Project Manager', hr: 'HR', vendor: 'Vendor', warehouse: 'Gudang',
};

/** Cocokkan item menu dengan URL aktif — exact, prefix path, atau query-string yang sama. */
function isActive(href, url) {
    if (href === '#') return false;
    const [hPath, hQuery] = href.split('?');
    const [uPath, uQuery] = url.split('?');
    if (hQuery) return uPath === hPath && (uQuery ?? '') === hQuery;
    if (uPath === hPath) return true;
    if (hPath !== '/dashboard' && uPath.startsWith(hPath + '/')) return true;
    return false;
}

export default function Sidebar() {
    const { auth } = usePage().props;
    const url = usePage().url;
    const items = getMenuForUser(auth);
    const menuBadges = auth?.menuBadges ?? {};
    const user = auth?.user;
    const initial = user?.name?.charAt(0)?.toUpperCase() ?? '?';

    return (
        <aside className="flex w-[248px] shrink-0 flex-col rounded-2xl border border-border bg-surface shadow-sm">
            {/* Brand */}
            <div className="flex flex-col items-center px-5 pb-4 pt-6 text-center">
                <img src="/images/logo-gs.png" alt="General Solusindo" className="h-12 w-auto" />
                <span className="mt-2 block text-[11px] font-medium uppercase tracking-[0.14em] text-text-faint">CRM Internal</span>
            </div>

            {/* Nav */}
            <nav className="flex-1 space-y-0.5 overflow-y-auto px-3 py-2">
                {items.map((item, i) => {
                    const Icon = item.icon;
                    const disabled = item.href === '#';
                    const active = isActive(item.href, url);
                    const badge = menuBadges[item.href] ?? 0;

                    if (disabled) {
                        return (
                            <div key={i} className="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-text-faint">
                                <Icon className="h-[18px] w-[18px] shrink-0" />
                                <span>{item.label}</span>
                            </div>
                        );
                    }

                    return (
                        <Link
                            key={i}
                            href={item.href}
                            className={`group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition ${
                                active
                                    ? 'bg-navy text-white shadow-sm'
                                    : 'text-text-muted hover:bg-bg hover:text-text'
                            }`}
                        >
                            <Icon className={`h-[18px] w-[18px] shrink-0 ${active ? 'text-white' : 'text-text-faint group-hover:text-text'}`} />
                            <span className="truncate">{item.label}</span>
                            {badge > 0 && (
                                <span className={`ml-auto flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[11px] font-bold ${
                                    active ? 'bg-white text-navy' : 'bg-danger text-white'
                                }`}>
                                    {badge}
                                </span>
                            )}
                        </Link>
                    );
                })}
            </nav>

            {/* User + logout */}
            <div className="border-t border-border p-3">
                <div className="flex items-center gap-3 rounded-xl px-2 py-2">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy text-sm font-semibold text-white">
                        {initial}
                    </span>
                    <span className="min-w-0 leading-tight">
                        <span className="block truncate text-sm font-semibold text-text">{user?.name}</span>
                        <span className="block text-xs text-text-muted">{ROLE_LABEL[user?.role] ?? user?.role}</span>
                    </span>
                </div>
                <button
                    onClick={() => router.post('/logout')}
                    className="mt-1 flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-text-muted transition hover:bg-danger-soft hover:text-danger"
                >
                    <FiLogOut className="h-[18px] w-[18px] shrink-0" />
                    <span>Keluar</span>
                </button>
            </div>
        </aside>
    );
}
