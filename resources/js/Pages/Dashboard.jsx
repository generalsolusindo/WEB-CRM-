import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    FiFilePlus, FiEdit3, FiClock, FiAward, FiArrowRight, FiCheckCircle,
    FiUserPlus, FiFileText, FiShoppingCart, FiBarChart2, FiUserCheck,
    FiAlertCircle, FiCreditCard, FiTruck, FiClipboard, FiPackage, FiTool,
    FiCalendar, FiCheckSquare, FiTrendingUp, FiTrendingDown, FiUsers,
} from 'react-icons/fi';
import AppLayout from '../Layouts/AppLayout';
import { getMenuForUser } from '../config/menuConfig';
import { StatusBadge } from '../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
}

export default function Dashboard({ salesActions = null, procurementActions = null, financeActions = null, operationalActions = null, managementOverview = null, projectManagerOverview = null }) {
    const { auth } = usePage().props;

    return (
        <AppLayout>
            <Head title="Dashboard" />
            {salesActions && <SalesDashboard data={salesActions} />}
            {managementOverview && (
                <div className="mx-auto max-w-6xl space-y-6">
                    <ManagementOverview data={managementOverview} menuBadges={auth?.menuBadges ?? {}} />
                </div>
            )}
            {procurementActions && <ProcurementDashboard data={procurementActions} />}
            {operationalActions && <OperationalDashboard data={operationalActions} />}
            {financeActions && <FinanceDashboard data={financeActions} />}
            {projectManagerOverview && <ProjectManagerDashboard data={projectManagerOverview} menuBadges={auth?.menuBadges ?? {}} />}
            {!salesActions && !managementOverview && !procurementActions && !operationalActions && !financeActions && !projectManagerOverview && (
                <GenericDashboard auth={auth} menuBadges={auth?.menuBadges ?? {}} />
            )}
        </AppLayout>
    );
}

/* ─────────────────────────── Sales ─────────────────────────── */

const TONE = {
    blue: 'bg-primary-soft text-primary-strong',
    amber: 'bg-warning-soft text-warning',
    rose: 'bg-danger-soft text-danger',
    green: 'bg-success-soft text-success',
    slate: 'bg-surface-2 text-text-muted',
};
const DOT = { blue: 'bg-primary', amber: 'bg-warning', rose: 'bg-danger', green: 'bg-success', slate: 'bg-text-faint' };
const BAR = { blue: 'bg-primary', amber: 'bg-warning', rose: 'bg-danger', green: 'bg-success', slate: 'bg-text-faint' };

function SalesDashboard({ data }) {
    const groups = [
        { key: 'quotation', label: 'Buat Quotation', tone: 'blue', items: data.ready_without_quotation ?? [] },
        { key: 'draft', label: 'Quotation Draft', tone: 'amber', items: data.draft_quotations ?? [] },
        { key: 'followup', label: 'Follow-up', tone: 'rose', items: data.stale_sent_quotations ?? [] },
        { key: 'won', label: 'Tutup Won', tone: 'green', items: data.ready_to_win ?? [] },
    ];
    const total = groups.reduce((s, g) => s + g.items.length, 0);
    const flat = groups.flatMap((g) => g.items.map((it) => ({ ...it, group: g.key, groupLabel: g.label, tone: g.tone })));

    const [filter, setFilter] = useState('all');
    const shown = filter === 'all' ? flat : flat.filter((i) => i.group === filter);

    return (
        <div className="mx-auto max-w-6xl space-y-6">
            {/* Hero */}
            <section className="flex flex-wrap items-center justify-between gap-4 overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-navy to-primary p-7 text-white shadow-md">
                <div>
                    <p className="text-sm font-medium text-white/65">Ringkasan hari ini</p>
                    <h1 className="mt-1.5 text-2xl font-bold tracking-tight">
                        {total > 0 ? `${total} hal butuh tindak lanjut` : 'Semua sudah tertangani 🎉'}
                    </h1>
                    <p className="mt-1 text-sm text-white/70">Prospek, penawaran, dan closing dalam satu pandangan.</p>
                </div>
                <Link href="/sales/leads/create" className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-navy transition hover:bg-white/90">
                    <FiUserPlus className="h-4 w-4" /> Lead Baru
                </Link>
            </section>

            {/* KPI */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Kpi icon={FiFilePlus} tone="blue" label="Perlu Quotation" value={groups[0].items.length} href="/sales/leads" />
                <Kpi icon={FiEdit3} tone="amber" label="Quotation Draft" value={groups[1].items.length} href="/sales/quotations?status=draft" />
                <Kpi icon={FiClock} tone="rose" label="Sent > 7 hari" value={groups[2].items.length} href="/sales/quotations?status=sent" />
                <Kpi icon={FiAward} tone="green" label="Siap Ditutup Won" value={groups[3].items.length} href="/sales/sales-orders" />
            </div>

            <div className="grid gap-6 lg:grid-cols-3 lg:items-start">
                {/* Follow-up list */}
                <div className="card flex flex-col overflow-hidden p-0 lg:col-span-2 lg:min-h-[26rem]">
                    <div className="border-b border-border px-5 pb-3 pt-4">
                        <h2 className="text-base font-bold tracking-tight text-text">Perlu Tindak Lanjut</h2>
                        <div className="mt-3 flex flex-wrap gap-1.5">
                            <FilterPill active={filter === 'all'} onClick={() => setFilter('all')}>Semua <span className="opacity-60">{total}</span></FilterPill>
                            {groups.map((g) => (
                                <FilterPill key={g.key} active={filter === g.key} onClick={() => setFilter(g.key)}>
                                    {g.label} <span className="opacity-60">{g.items.length}</span>
                                </FilterPill>
                            ))}
                        </div>
                    </div>

                    {shown.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 px-6 py-14 text-center">
                            <FiCheckCircle className="h-8 w-8 text-success" />
                            <p className="text-sm font-medium text-text">Tidak ada yang perlu ditindaklanjuti.</p>
                            <p className="text-xs text-text-muted">Semua penawaran & prospek sudah tertangani.</p>
                        </div>
                    ) : (
                        <ul className="flex-1">
                            {shown.map((item, i) => (
                                <li key={i}>
                                    <Link href={item.href} className="flex items-center gap-3 border-b border-border px-5 py-3.5 transition last:border-0 hover:bg-bg">
                                        <span className={`h-2 w-2 shrink-0 rounded-full ${DOT[item.tone]}`} />
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-text">{item.label}</span>
                                        <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${TONE[item.tone]}`}>{item.groupLabel}</span>
                                        <FiArrowRight className="h-4 w-4 shrink-0 text-text-faint" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {/* Right column */}
                <div className="space-y-6">
                    <div className="card p-5">
                        <h3 className="text-sm font-bold tracking-tight text-text">Pintasan</h3>
                        <div className="mt-3 space-y-1">
                            <Shortcut icon={FiUserPlus} href="/sales/leads/create">Buat Lead Baru</Shortcut>
                            <Shortcut icon={FiFileText} href="/sales/quotations">Semua Quotation</Shortcut>
                            <Shortcut icon={FiShoppingCart} href="/sales/sales-orders">Sales Orders</Shortcut>
                            <Shortcut icon={FiUserCheck} href="/sales/contacts">Kontak</Shortcut>
                            <Shortcut icon={FiBarChart2} href="/sales/reports/leads">Laporan Lead</Shortcut>
                        </div>
                    </div>

                    {total > 0 && (
                        <div className="card p-5">
                            <h3 className="text-sm font-bold tracking-tight text-text">Distribusi Tindak Lanjut</h3>
                            <div className="mt-4 flex h-2.5 overflow-hidden rounded-full bg-bg">
                                {groups.filter((g) => g.items.length > 0).map((g) => (
                                    <div key={g.key} className={BAR[g.tone]} style={{ width: `${(g.items.length / total) * 100}%` }} />
                                ))}
                            </div>
                            <div className="mt-3 space-y-1.5">
                                {groups.map((g) => (
                                    <div key={g.key} className="flex items-center gap-2 text-xs">
                                        <span className={`h-2 w-2 rounded-full ${DOT[g.tone]}`} />
                                        <span className="flex-1 text-text-muted">{g.label}</span>
                                        <span className="font-semibold text-text">{g.items.length}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

/* ─────────────────────────── Finance ─────────────────────────── */

function FinanceDashboard({ data }) {
    const groups = [
        { key: 'upfront', label: 'Perlu Invoice Muka', tone: 'blue', icon: FiFilePlus, items: data.needs_upfront_invoice ?? [] },
        { key: 'draft', label: 'Invoice Draft', tone: 'amber', icon: FiEdit3, items: data.draft ?? [] },
        { key: 'unpaid', label: 'Belum Lunas', tone: 'slate', icon: FiClock, items: data.unpaid_sent ?? [] },
        { key: 'overdue', label: 'Jatuh Tempo', tone: 'rose', icon: FiAlertCircle, items: data.overdue ?? [] },
        { key: 'final', label: 'Siap Pelunasan', tone: 'green', icon: FiCheckCircle, items: data.ready_for_final ?? [] },
    ];
    const total = groups.reduce((s, g) => s + g.items.length, 0);
    const flat = groups.flatMap((g) => g.items.map((it) => ({ ...it, group: g.key, groupLabel: g.label, tone: g.tone })));

    const [filter, setFilter] = useState('all');
    const shown = filter === 'all' ? flat : flat.filter((i) => i.group === filter);

    return (
        <div className="mx-auto max-w-6xl space-y-6">
            <section className="flex flex-wrap items-center justify-between gap-4 overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-navy to-primary p-7 text-white shadow-md">
                <div>
                    <p className="text-sm font-medium text-white/65">Ringkasan hari ini</p>
                    <h1 className="mt-1.5 text-2xl font-bold tracking-tight">
                        {total > 0 ? `${total} hal butuh tindak lanjut` : 'Semua sudah tertangani 🎉'}
                    </h1>
                    <p className="mt-1 text-sm text-white/70">Invoice, penagihan, dan pelunasan dalam satu pandangan.</p>
                </div>
                <Link href="/finance/invoices" className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-navy transition hover:bg-white/90">
                    <FiFileText className="h-4 w-4" /> Semua Invoice
                </Link>
            </section>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                {groups.map((g) => (
                    <Kpi key={g.key} icon={g.icon} tone={g.tone} label={g.label} value={g.items.length} href="/finance/invoices" />
                ))}
            </div>

            <div className="grid gap-6 lg:grid-cols-3 lg:items-start">
                <div className="card flex flex-col overflow-hidden p-0 lg:col-span-2 lg:min-h-[26rem]">
                    <div className="border-b border-border px-5 pb-3 pt-4">
                        <h2 className="text-base font-bold tracking-tight text-text">Perlu Tindak Lanjut</h2>
                        <div className="mt-3 flex flex-wrap gap-1.5">
                            <FilterPill active={filter === 'all'} onClick={() => setFilter('all')}>Semua <span className="opacity-60">{total}</span></FilterPill>
                            {groups.map((g) => (
                                <FilterPill key={g.key} active={filter === g.key} onClick={() => setFilter(g.key)}>
                                    {g.label} <span className="opacity-60">{g.items.length}</span>
                                </FilterPill>
                            ))}
                        </div>
                    </div>

                    {shown.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 px-6 py-14 text-center">
                            <FiCheckCircle className="h-8 w-8 text-success" />
                            <p className="text-sm font-medium text-text">Tidak ada yang perlu ditindaklanjuti.</p>
                            <p className="text-xs text-text-muted">Semua invoice & penagihan sudah tertangani.</p>
                        </div>
                    ) : (
                        <ul className="flex-1">
                            {shown.map((item, i) => (
                                <li key={i}>
                                    <Link href={item.href} className="flex items-center gap-3 border-b border-border px-5 py-3.5 transition last:border-0 hover:bg-bg">
                                        <span className={`h-2 w-2 shrink-0 rounded-full ${DOT[item.tone]}`} />
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-text">{item.label}</span>
                                        <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${TONE[item.tone]}`}>{item.groupLabel}</span>
                                        <FiArrowRight className="h-4 w-4 shrink-0 text-text-faint" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="space-y-6">
                    <div className="card p-5">
                        <h3 className="text-sm font-bold tracking-tight text-text">Pintasan</h3>
                        <div className="mt-3 space-y-1">
                            <Shortcut icon={FiFileText} href="/finance/invoices">Invoice</Shortcut>
                            <Shortcut icon={FiClipboard} href="/finance/surveys">Survey</Shortcut>
                            <Shortcut icon={FiCreditCard} href="/finance/payments">Pembayaran</Shortcut>
                            <Shortcut icon={FiTruck} href="/finance/procurement-payments">Pembayaran Vendor</Shortcut>
                        </div>
                    </div>

                    {total > 0 && (
                        <div className="card p-5">
                            <h3 className="text-sm font-bold tracking-tight text-text">Distribusi Tindak Lanjut</h3>
                            <div className="mt-4 flex h-2.5 overflow-hidden rounded-full bg-bg">
                                {groups.filter((g) => g.items.length > 0).map((g) => (
                                    <div key={g.key} className={BAR[g.tone]} style={{ width: `${(g.items.length / total) * 100}%` }} />
                                ))}
                            </div>
                            <div className="mt-3 space-y-1.5">
                                {groups.map((g) => (
                                    <div key={g.key} className="flex items-center gap-2 text-xs">
                                        <span className={`h-2 w-2 rounded-full ${DOT[g.tone]}`} />
                                        <span className="flex-1 text-text-muted">{g.label}</span>
                                        <span className="font-semibold text-text">{g.items.length}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

/* ─────────────────────────── Procurement ─────────────────────────── */

function ProcurementDashboard({ data }) {
    const groups = [
        { key: 'submitted', label: 'PR Baru Masuk', tone: 'blue', icon: FiClipboard, href: '/procurement/procurement-requests', items: data.submitted ?? [] },
        { key: 'searching', label: 'Sedang Dicari', tone: 'amber', icon: FiClock, href: '/procurement/procurement-requests', items: data.searching ?? [] },
        { key: 'project', label: 'Pengadaan Project', tone: 'rose', icon: FiTruck, href: '/procurement/project-procurements', items: data.project_procurement ?? [] },
    ];
    const total = groups.reduce((s, g) => s + g.items.length, 0);
    const flat = groups.flatMap((g) => g.items.map((it) => ({ ...it, group: g.key, groupLabel: g.label, tone: g.tone })));

    const [filter, setFilter] = useState('all');
    const shown = filter === 'all' ? flat : flat.filter((i) => i.group === filter);

    return (
        <div className="mx-auto max-w-6xl space-y-6">
            <section className="flex flex-wrap items-center justify-between gap-4 overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-navy to-primary p-7 text-white shadow-md">
                <div>
                    <p className="text-sm font-medium text-white/65">Ringkasan hari ini</p>
                    <h1 className="mt-1.5 text-2xl font-bold tracking-tight">
                        {total > 0 ? `${total} hal butuh tindak lanjut` : 'Semua sudah tertangani 🎉'}
                    </h1>
                    <p className="mt-1 text-sm text-white/70">Procurement request, sourcing, dan pengadaan project dalam satu pandangan.</p>
                </div>
                <Link href="/procurement/procurement-requests" className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-navy transition hover:bg-white/90">
                    <FiClipboard className="h-4 w-4" /> Semua Procurement Request
                </Link>
            </section>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {groups.map((g) => (
                    <Kpi key={g.key} icon={g.icon} tone={g.tone} label={g.label} value={g.items.length} href={g.href} />
                ))}
            </div>

            <div className="grid gap-6 lg:grid-cols-3 lg:items-start">
                <div className="card flex flex-col overflow-hidden p-0 lg:col-span-2 lg:min-h-[26rem]">
                    <div className="border-b border-border px-5 pb-3 pt-4">
                        <h2 className="text-base font-bold tracking-tight text-text">Perlu Tindak Lanjut</h2>
                        <div className="mt-3 flex flex-wrap gap-1.5">
                            <FilterPill active={filter === 'all'} onClick={() => setFilter('all')}>Semua <span className="opacity-60">{total}</span></FilterPill>
                            {groups.map((g) => (
                                <FilterPill key={g.key} active={filter === g.key} onClick={() => setFilter(g.key)}>
                                    {g.label} <span className="opacity-60">{g.items.length}</span>
                                </FilterPill>
                            ))}
                        </div>
                    </div>

                    {shown.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 px-6 py-14 text-center">
                            <FiCheckCircle className="h-8 w-8 text-success" />
                            <p className="text-sm font-medium text-text">Tidak ada yang perlu ditindaklanjuti.</p>
                            <p className="text-xs text-text-muted">Semua procurement request & pengadaan sudah tertangani.</p>
                        </div>
                    ) : (
                        <ul className="flex-1">
                            {shown.map((item, i) => (
                                <li key={i}>
                                    <Link href={item.href} className="flex items-center gap-3 border-b border-border px-5 py-3.5 transition last:border-0 hover:bg-bg">
                                        <span className={`h-2 w-2 shrink-0 rounded-full ${DOT[item.tone]}`} />
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-text">{item.label}</span>
                                        <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${TONE[item.tone]}`}>{item.groupLabel}</span>
                                        <FiArrowRight className="h-4 w-4 shrink-0 text-text-faint" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="space-y-6">
                    <div className="card p-5">
                        <h3 className="text-sm font-bold tracking-tight text-text">Pintasan</h3>
                        <div className="mt-3 space-y-1">
                            <Shortcut icon={FiClipboard} href="/procurement/procurement-requests">Procurement Request</Shortcut>
                            <Shortcut icon={FiTruck} href="/procurement/project-procurements">Pengadaan Project</Shortcut>
                            <Shortcut icon={FiPackage} href="/procurement/vendors">Vendor &amp; Katalog Produk</Shortcut>
                            <Shortcut icon={FiClipboard} href="/procurement/surveys">Survey</Shortcut>
                            <Shortcut icon={FiTool} href="/procurement/technicians">Surveyor &amp; Teknisi</Shortcut>
                            <Shortcut icon={FiUserCheck} href="/procurement/vendor-accounts">Akun PIC Vendor</Shortcut>
                        </div>
                    </div>

                    {total > 0 && (
                        <div className="card p-5">
                            <h3 className="text-sm font-bold tracking-tight text-text">Distribusi Tindak Lanjut</h3>
                            <div className="mt-4 flex h-2.5 overflow-hidden rounded-full bg-bg">
                                {groups.filter((g) => g.items.length > 0).map((g) => (
                                    <div key={g.key} className={BAR[g.tone]} style={{ width: `${(g.items.length / total) * 100}%` }} />
                                ))}
                            </div>
                            <div className="mt-3 space-y-1.5">
                                {groups.map((g) => (
                                    <div key={g.key} className="flex items-center gap-2 text-xs">
                                        <span className={`h-2 w-2 rounded-full ${DOT[g.tone]}`} />
                                        <span className="flex-1 text-text-muted">{g.label}</span>
                                        <span className="font-semibold text-text">{g.items.length}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

/* ─────────────────────────── Operational ─────────────────────────── */

function OperationalDashboard({ data }) {
    const groups = [
        { key: 'needs_project', label: 'SO Siap Dibuat Project', tone: 'blue', icon: FiFilePlus, href: '/operational/projects', items: data.needs_project ?? [] },
        { key: 'planning', label: 'Project Perencanaan', tone: 'amber', icon: FiCalendar, href: '/operational/projects', items: data.planning ?? [] },
        { key: 'waiting_resource', label: 'Menunggu Barang', tone: 'rose', icon: FiTruck, href: '/operational/projects?status=waiting_resource', items: data.waiting_resource ?? [] },
        { key: 'bast', label: 'BAST Menunggu Verifikasi', tone: 'green', icon: FiCheckSquare, href: '/operational/projects?status=verification', items: data.bast_to_verify ?? [] },
    ];
    const total = groups.reduce((s, g) => s + g.items.length, 0);
    const flat = groups.flatMap((g) => g.items.map((it) => ({ ...it, group: g.key, groupLabel: g.label, tone: g.tone })));

    const [filter, setFilter] = useState('all');
    const shown = filter === 'all' ? flat : flat.filter((i) => i.group === filter);

    return (
        <div className="mx-auto max-w-6xl space-y-6">
            <section className="flex flex-wrap items-center justify-between gap-4 overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-navy to-primary p-7 text-white shadow-md">
                <div>
                    <p className="text-sm font-medium text-white/65">Ringkasan hari ini</p>
                    <h1 className="mt-1.5 text-2xl font-bold tracking-tight">
                        {total > 0 ? `${total} hal butuh tindak lanjut` : 'Semua sudah tertangani 🎉'}
                    </h1>
                    <p className="mt-1 text-sm text-white/70">Project, resource, dan BAST dalam satu pandangan.</p>
                </div>
                <Link href="/operational/projects" className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-navy transition hover:bg-white/90">
                    <FiCalendar className="h-4 w-4" /> Semua Project
                </Link>
            </section>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {groups.map((g) => (
                    <Kpi key={g.key} icon={g.icon} tone={g.tone} label={g.label} value={g.items.length} href={g.href} />
                ))}
            </div>

            <div className="grid gap-6 lg:grid-cols-3 lg:items-start">
                <div className="card flex flex-col overflow-hidden p-0 lg:col-span-2 lg:min-h-[26rem]">
                    <div className="border-b border-border px-5 pb-3 pt-4">
                        <h2 className="text-base font-bold tracking-tight text-text">Perlu Tindak Lanjut</h2>
                        <div className="mt-3 flex flex-wrap gap-1.5">
                            <FilterPill active={filter === 'all'} onClick={() => setFilter('all')}>Semua <span className="opacity-60">{total}</span></FilterPill>
                            {groups.map((g) => (
                                <FilterPill key={g.key} active={filter === g.key} onClick={() => setFilter(g.key)}>
                                    {g.label} <span className="opacity-60">{g.items.length}</span>
                                </FilterPill>
                            ))}
                        </div>
                    </div>

                    {shown.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 px-6 py-14 text-center">
                            <FiCheckCircle className="h-8 w-8 text-success" />
                            <p className="text-sm font-medium text-text">Tidak ada yang perlu ditindaklanjuti.</p>
                            <p className="text-xs text-text-muted">Semua project & BAST sudah tertangani.</p>
                        </div>
                    ) : (
                        <ul className="flex-1">
                            {shown.map((item, i) => (
                                <li key={i}>
                                    <Link href={item.href} className="flex items-center gap-3 border-b border-border px-5 py-3.5 transition last:border-0 hover:bg-bg">
                                        <span className={`h-2 w-2 shrink-0 rounded-full ${DOT[item.tone]}`} />
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-text">{item.label}</span>
                                        <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${TONE[item.tone]}`}>{item.groupLabel}</span>
                                        <FiArrowRight className="h-4 w-4 shrink-0 text-text-faint" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="space-y-6">
                    <div className="card p-5">
                        <h3 className="text-sm font-bold tracking-tight text-text">Pintasan</h3>
                        <div className="mt-3 space-y-1">
                            <Shortcut icon={FiCalendar} href="/operational/projects">Semua Project</Shortcut>
                            <Shortcut icon={FiTruck} href="/operational/projects?status=waiting_resource">Menunggu Barang</Shortcut>
                            <Shortcut icon={FiCheckSquare} href="/operational/projects?status=verification">Verifikasi BAST</Shortcut>
                            <Shortcut icon={FiClipboard} href="/operational/surveys">Survey</Shortcut>
                        </div>
                    </div>

                    {total > 0 && (
                        <div className="card p-5">
                            <h3 className="text-sm font-bold tracking-tight text-text">Distribusi Tindak Lanjut</h3>
                            <div className="mt-4 flex h-2.5 overflow-hidden rounded-full bg-bg">
                                {groups.filter((g) => g.items.length > 0).map((g) => (
                                    <div key={g.key} className={BAR[g.tone]} style={{ width: `${(g.items.length / total) * 100}%` }} />
                                ))}
                            </div>
                            <div className="mt-3 space-y-1.5">
                                {groups.map((g) => (
                                    <div key={g.key} className="flex items-center gap-2 text-xs">
                                        <span className={`h-2 w-2 rounded-full ${DOT[g.tone]}`} />
                                        <span className="flex-1 text-text-muted">{g.label}</span>
                                        <span className="font-semibold text-text">{g.items.length}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

/* ─────────────────────────── Project Manager ─────────────────────────── */

function ProjectManagerDashboard({ data, menuBadges = {} }) {
    const verifQuotation = menuBadges['/project-manager/quotations'] || 0;
    const persetujuan = menuBadges['/project-manager/procurement-payments'] || 0;
    const active = data.active_projects ?? [];
    const total = verifQuotation + persetujuan;

    return (
        <div className="mx-auto max-w-6xl space-y-6">
            <section className="flex flex-wrap items-center justify-between gap-4 overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-navy to-primary p-7 text-white shadow-md">
                <div>
                    <p className="text-sm font-medium text-white/65">Ringkasan hari ini</p>
                    <h1 className="mt-1.5 text-2xl font-bold tracking-tight">
                        {total > 0 ? `${total} hal butuh tindak lanjut` : 'Semua sudah tertangani 🎉'}
                    </h1>
                    <p className="mt-1 text-sm text-white/70">Tracking project, quotation, dan pengadaan yang kamu delegasikan.</p>
                </div>
                <Link href="/project-manager/projects" className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-navy transition hover:bg-white/90">
                    <FiCalendar className="h-4 w-4" /> Semua Project Saya
                </Link>
            </section>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Kpi icon={FiFileText} tone="amber" label="Verifikasi Quotation" value={verifQuotation} href="/project-manager/quotations" />
                <Kpi icon={FiCheckSquare} tone="rose" label="Persetujuan Pengadaan" value={persetujuan} href="/project-manager/procurement-payments" />
                <Kpi icon={FiCalendar} tone="blue" label="Project Aktif" value={active.length} href="/project-manager/projects" />
                <Kpi icon={FiCheckCircle} tone="green" label="Project Selesai" value={data.completed ?? 0} href="/project-manager/projects?status=completed" />
            </div>

            <div className="grid gap-6 lg:grid-cols-3 lg:items-start">
                <div className="card flex flex-col overflow-hidden p-0 lg:col-span-2 lg:min-h-[26rem]">
                    <div className="border-b border-border px-5 py-4">
                        <h2 className="text-base font-bold tracking-tight text-text">Tracking Project Aktif</h2>
                        <p className="mt-0.5 text-xs text-text-muted">Project yang sudah didelegasikan ke kamu dan belum Selesai.</p>
                    </div>

                    {active.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 px-6 py-14 text-center">
                            <FiCheckCircle className="h-8 w-8 text-success" />
                            <p className="text-sm font-medium text-text">Tidak ada project aktif saat ini.</p>
                            <p className="text-xs text-text-muted">Project baru akan muncul di sini begitu didelegasikan ke kamu.</p>
                        </div>
                    ) : (
                        <ul className="flex-1">
                            {active.map((p) => (
                                <li key={p.id}>
                                    <Link href={p.href} className="flex items-center gap-3 border-b border-border px-5 py-3.5 transition last:border-0 hover:bg-bg">
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium text-text">{p.number} {p.is_won && <StatusBadge status="won" label="Won" />}</span>
                                            <span className="block truncate text-xs text-text-muted">{p.customer || 'Tanpa customer'}</span>
                                        </span>
                                        <StatusBadge status={p.status} label={p.status_label} />
                                        <FiArrowRight className="h-4 w-4 shrink-0 text-text-faint" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="space-y-6">
                    <div className="card p-5">
                        <h3 className="text-sm font-bold tracking-tight text-text">Pintasan</h3>
                        <div className="mt-3 space-y-1">
                            <Shortcut icon={FiUserCheck} href="/project-manager/opportunities">Opportunity Saya</Shortcut>
                            <Shortcut icon={FiCalendar} href="/project-manager/projects">Project Saya</Shortcut>
                            <Shortcut icon={FiFileText} href="/project-manager/sows">SOW Menunggu TTD</Shortcut>
                        </div>
                    </div>

                    {data.by_status?.length > 0 && (
                        <div className="card p-5">
                            <h3 className="text-sm font-bold tracking-tight text-text">Distribusi Status Project</h3>
                            <div className="mt-3 space-y-1.5">
                                {data.by_status.filter((s) => s.count > 0).map((s) => (
                                    <div key={s.value} className="flex items-center gap-2 text-xs">
                                        <StatusBadge status={s.value} label={s.label} />
                                        <span className="flex-1" />
                                        <span className="font-semibold text-text">{s.count}</span>
                                    </div>
                                ))}
                                {data.by_status.every((s) => s.count === 0) && (
                                    <p className="text-xs text-text-muted">Belum ada project yang didelegasikan.</p>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function Kpi({ icon: Icon, tone, label, value, href }) {
    return (
        <Link href={href} className="card group flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
            <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${TONE[tone]}`}>
                <Icon className="h-5 w-5" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-xl font-bold leading-tight tracking-tight text-text sm:text-2xl">{value}</span>
                <span className="mt-1 block truncate text-xs font-medium text-text-muted">{label}</span>
            </span>
        </Link>
    );
}

function FilterPill({ active, onClick, children }) {
    return (
        <button
            onClick={onClick}
            className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                active ? 'bg-navy text-white' : 'bg-bg text-text-muted hover:text-text'
            }`}
        >
            {children}
        </button>
    );
}

function Shortcut({ icon: Icon, href, children }) {
    return (
        <Link href={href} className="group flex items-center gap-3 rounded-xl px-2.5 py-2 text-sm font-medium text-text-muted transition hover:bg-bg hover:text-text">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-bg text-text-faint transition group-hover:bg-primary-soft group-hover:text-primary-strong">
                <Icon className="h-4 w-4" />
            </span>
            <span className="flex-1">{children}</span>
            <FiArrowRight className="h-3.5 w-3.5 text-text-faint opacity-0 transition group-hover:opacity-100" />
        </Link>
    );
}

/* ───────────────────── other roles (sementara) ───────────────────── */

function LegacyActions({ groups }) {
    return (
        <div className="mx-auto max-w-3xl space-y-4">
            <h2 className="text-lg font-bold tracking-tight text-text">Perlu Aksi</h2>
            {groups.map(([title, items], i) => (
                <div key={i} className="card overflow-hidden p-0">
                    <div className="flex items-center justify-between border-b border-border px-5 py-3">
                        <span className="text-sm font-semibold text-text">{title}</span>
                        <span className="rounded-full bg-bg px-2 py-0.5 text-xs font-semibold text-text-muted">{items.length}</span>
                    </div>
                    {items.length === 0 ? (
                        <p className="px-5 py-4 text-sm text-text-muted">Tidak ada.</p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {items.map((item, j) => (
                                <li key={j}>
                                    <Link href={item.href} className="block px-5 py-3 text-sm font-medium text-primary transition hover:bg-bg">{item.label}</Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            ))}
        </div>
    );
}

const ROLE_TAGLINE = {
    technician: 'Cek tugas, survey, dan SOW kamu di sini.',
    hr: 'Review SOW yang menunggu persetujuanmu.',
    project_manager: 'Verifikasi quotation dan pengadaan project yang kamu delegasikan.',
    vendor: 'Lihat dan tanda tangani SOW project kamu.',
    administrator: 'Kelola user dan master data sistem.',
    warehouse: 'Kelola stok barang gudang di sini.',
};

const KPI_TONE_CYCLE = ['blue', 'amber', 'rose', 'green'];

function GenericDashboard({ auth, menuBadges = {} }) {
    const role = auth?.user?.role;
    const items = getMenuForUser(auth).filter((i) => i.href !== '#' && i.href !== '/dashboard');
    const withCount = items.filter((i) => Object.prototype.hasOwnProperty.call(menuBadges, i.href));
    const withoutCount = items.filter((i) => !Object.prototype.hasOwnProperty.call(menuBadges, i.href));
    const total = withCount.reduce((s, i) => s + (menuBadges[i.href] || 0), 0);

    return (
        <div className="mx-auto max-w-6xl space-y-6">
            <section className="flex flex-wrap items-center justify-between gap-4 overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-navy to-primary p-4 sm:p-7 text-white shadow-md">
                <div className="min-w-0 break-words">
                    <p className="text-sm font-medium text-white/65">Ringkasan hari ini</p>
                    <h1 className="mt-1.5 text-2xl font-bold tracking-tight">
                        {withCount.length === 0
                            ? auth?.user?.name
                            : total > 0 ? `${total} hal butuh tindak lanjut` : 'Semua sudah tertangani 🎉'}
                    </h1>
                    <p className="mt-1 text-sm text-white/70">{ROLE_TAGLINE[role] ?? 'Selamat bekerja hari ini.'}</p>
                </div>
            </section>

            {withCount.length > 0 && (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {withCount.map((item, i) => (
                        <Kpi
                            key={item.href}
                            icon={item.icon}
                            tone={KPI_TONE_CYCLE[i % KPI_TONE_CYCLE.length]}
                            label={item.label}
                            value={menuBadges[item.href] || 0}
                            href={item.href}
                        />
                    ))}
                </div>
            )}

            {withoutCount.length > 0 && (
                <div className="card max-w-sm p-5">
                    <h3 className="text-sm font-bold tracking-tight text-text">Pintasan</h3>
                    <div className="mt-3 space-y-1">
                        {withoutCount.map((item) => (
                            <Shortcut key={item.href} icon={item.icon} href={item.href}>{item.label}</Shortcut>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

/* ───────────────────── Management overview ───────────────────── */

function ManagementOverview({ data, menuBadges = {} }) {
    const total = (menuBadges['/management/quotations'] || 0) + (menuBadges['/management/sows'] || 0);
    const [range, setRange] = useState({ from: data.period.from, to: data.period.to });
    const [applying, setApplying] = useState(false);

    function applyRange(e) {
        e.preventDefault();
        setApplying(true);
        router.get('/dashboard', range, {
            preserveScroll: true,
            preserveState: true,
            only: ['managementOverview'],
            onFinish: () => setApplying(false),
        });
    }

    return (
        <div className="space-y-6">
            <section className="flex flex-wrap items-center justify-between gap-4 overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-navy to-primary p-7 text-white shadow-md">
                <div>
                    <p className="text-sm font-medium text-white/65">Ringkasan hari ini</p>
                    <h1 className="mt-1.5 text-2xl font-bold tracking-tight">
                        {total > 0 ? `${total} hal butuh tindak lanjut` : 'Semua sudah tertangani 🎉'}
                    </h1>
                    <p className="mt-1 text-sm text-white/70">Ringkasan monitoring lintas-departemen — klik kartu mana pun untuk lihat detail lengkapnya.</p>
                </div>
                {total > 0 && (
                    <div className="flex gap-2">
                        {(menuBadges['/management/quotations'] || 0) > 0 && (
                            <Link href="/management/quotations" className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-navy transition hover:bg-white/90">
                                <FiFileText className="h-4 w-4" /> Verifikasi Quotation ({menuBadges['/management/quotations']})
                            </Link>
                        )}
                        {(menuBadges['/management/sows'] || 0) > 0 && (
                            <Link href="/management/sows" className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-navy transition hover:bg-white/90">
                                <FiFileText className="h-4 w-4" /> SOW Menunggu TTD ({menuBadges['/management/sows']})
                            </Link>
                        )}
                    </div>
                )}
            </section>

            <PeriodFilter range={range} onChange={setRange} onSubmit={applyRange} applying={applying} label={data.period.label} />

            <RevenueSection data={data.revenue} periodLabel={data.period.label} />

            <ActivitySection data={data.activity} />

            <StatSection title="Sales" href="/management/opportunities" icon={FiUsers}>
                {data.sales.leads_by_stage.map((item) => (
                    <KpiCard
                        key={item.value} icon={FiUsers} tone={STAGE_TONE[item.value] ?? 'slate'}
                        label={item.label} value={item.count} href={`/management/opportunities?stage=${item.value}`}
                    />
                ))}
                <KpiCard icon={FiFileText} tone="blue" label="Quotation Terbuka (Draft/Sent)" value={data.sales.open_quotations} href="/management/quotations" />
                <KpiCard icon={FiBarChart2} tone="blue" label="Nilai Pipeline (Quotation Terbuka)" value={money(data.sales.pipeline_value)} href="/management/quotations" />
            </StatSection>

            <StatSection title="Procurement" href="/management/procurement-requests" icon={FiShoppingCart}>
                {data.procurement.by_status.map((item) => (
                    <KpiCard
                        key={item.value} icon={FiShoppingCart} tone={STAGE_TONE[item.value] ?? 'slate'}
                        label={item.label} value={item.count} href={`/management/procurement-requests?status=${item.value}`}
                    />
                ))}
            </StatSection>

            <StatSection title="Finance" href="/management/invoices" icon={FiCreditCard}>
                <KpiCard icon={FiCreditCard} tone="slate" warn={data.finance.outstanding_total > 0} label="Total Piutang Belum Lunas" value={money(data.finance.outstanding_total)} href="/management/invoices" />
                <KpiCard icon={FiAlertCircle} tone="rose" warn={data.finance.overdue_count > 0} label="Invoice Jatuh Tempo" value={data.finance.overdue_count} href="/management/invoices?status=overdue" />
            </StatSection>

            <StatSection title="Operational — Project" href="/management/projects" icon={FiCalendar}>
                {data.operational.projects_by_status.map((item) => (
                    <KpiCard
                        key={item.value} icon={FiCalendar} tone={STAGE_TONE[item.value] ?? 'slate'}
                        label={item.label} value={item.count} href={`/management/projects?status=${item.value}`}
                    />
                ))}
                <KpiCard icon={FiClock} tone="rose" warn={data.operational.tasks_overdue > 0} label="Task Telat (belum selesai, lewat jadwal)" value={data.operational.tasks_overdue} href="/management/projects" />
                <KpiCard icon={FiCheckSquare} tone="amber" warn={data.operational.bast_pending > 0} label="BAST Menunggu Verifikasi" value={data.operational.bast_pending} href="/management/projects" />
            </StatSection>

            <StatSection title="Survey" href="/management/surveys" icon={FiClipboard}>
                {data.survey.by_status.map((item) => (
                    <KpiCard
                        key={item.value} icon={FiClipboard} tone={STAGE_TONE[item.value] ?? 'slate'}
                        label={item.label} value={item.count} href={`/management/surveys?status=${item.value}`}
                    />
                ))}
            </StatSection>
        </div>
    );
}

/** Filter periode bebas untuk Pendapatan/Profit & Aktivitas — backlog/status di bagian
 * lain Dashboard SENGAJA tidak ikut filter ini (lihat catatan di DashboardController). */
function PeriodFilter({ range, onChange, onSubmit, applying, label }) {
    return (
        <form onSubmit={onSubmit} className="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div className="flex items-center gap-3 sm:contents">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-strong sm:mb-1">
                    <FiCalendar className="h-4 w-4" />
                </span>
                <span className="text-xs font-semibold text-text sm:hidden">Filter Periode</span>
            </div>
            <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div className="w-full sm:w-auto">
                    <label className="mb-1 block text-[11px] font-bold uppercase tracking-wider text-text-faint">Dari</label>
                    <input
                        type="date" value={range.from} max={range.to}
                        onChange={(e) => onChange((r) => ({ ...r, from: e.target.value }))}
                        className="w-full rounded-lg border border-border px-3 py-1.5 text-sm sm:w-auto"
                    />
                </div>
                <div className="w-full sm:w-auto">
                    <label className="mb-1 block text-[11px] font-bold uppercase tracking-wider text-text-faint">Sampai</label>
                    <input
                        type="date" value={range.to} min={range.from}
                        onChange={(e) => onChange((r) => ({ ...r, to: e.target.value }))}
                        className="w-full rounded-lg border border-border px-3 py-1.5 text-sm sm:w-auto"
                    />
                </div>
                <button type="submit" disabled={applying} className="btn btn-primary w-full sm:w-auto">
                    {applying ? 'Menerapkan…' : 'Terapkan Periode'}
                </button>
            </div>
            <span className="text-xs text-text-muted sm:ml-auto">Menampilkan aktivitas: <span className="font-semibold text-text">{label}</span></span>
        </form>
    );
}

/** Naik/turun dibanding periode sebelumnya dengan panjang yang sama. */
function DeltaBadge({ percent, invert = false }) {
    if (percent == null) return null;
    const isUp = percent > 0;
    const good = invert ? !isUp : isUp;
    if (percent === 0) return <span className="text-xs font-semibold text-text-muted">Sama seperti periode lalu</span>;
    const Icon = isUp ? FiTrendingUp : FiTrendingDown;
    return (
        <span className={`inline-flex items-center gap-1 text-xs font-semibold ${good ? 'text-success' : 'text-danger'}`}>
            <Icon className="h-3 w-3" /> {Math.abs(percent)}% dari periode lalu
        </span>
    );
}

/**
 * Sama persis dengan tone yang dipakai Kpi/TONE/DOT di dashboard Sales, Finance, dst
 * (lihat const TONE di atas) — sengaja dipakai ulang, bukan skema warna baru, supaya
 * Dashboard Management terasa satu web yang sama dengan dashboard role lain. `value`
 * di sini adalah status mentah (mis. 'won', 'ready', 'rejected'), bukan label terjemahan.
 */
const STAGE_TONE = {
    new: 'blue', qualified: 'blue', requirement: 'blue', procurement: 'amber', quotation: 'amber', negotiation: 'amber', won: 'green', lost: 'rose',
    submitted: 'amber', searching: 'blue', ready: 'green', rejected: 'rose',
    draft: 'slate', planning: 'blue', waiting_resource: 'amber', in_progress: 'blue', verification: 'amber', completed: 'green',
    scheduled: 'blue', finance_review: 'amber', awaiting_payment: 'amber', verified: 'green', closed: 'slate',
};

/** Kartu KPI generik dipakai ulang persis seperti Kpi di dashboard Sales/Finance
 * (icon di kiri, angka besar + label) — cuma ditambah baris "delta" opsional untuk
 * angka yang ikut filter periode (Pendapatan, Profit, Aktivitas). */
function KpiCard({ icon: Icon, tone, label, value, href, delta, warn = false }) {
    const effectiveTone = warn ? 'rose' : tone;
    return (
        <Link href={href} className="card group flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
            <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${TONE[effectiveTone]}`}>
                <Icon className="h-5 w-5" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-xl font-bold leading-tight tracking-tight text-text sm:text-2xl">{value}</span>
                <span className="mt-1 block truncate text-xs font-medium text-text-muted">{label}</span>
                {delta !== undefined && <span className="mt-1.5 block">{delta}</span>}
            </span>
        </Link>
    );
}

/** Ringkasan pendapatan & profit periode terpilih — angka finansial yang paling ingin
 * langsung dilihat Manager begitu buka Dashboard, dihitung dari project yang Won
 * (rumus sama persis dengan laporan "Profit Project"). Diletakkan paling atas,
 * sebelum ringkasan operasional lain, karena ini yang paling sering dicari duluan. */
function RevenueSection({ data, periodLabel }) {
    const isProfitNegative = Number(data.profit) < 0;

    return (
        <div>
            <div className="mb-3 flex items-center justify-between gap-3">
                <h3 className="text-sm font-bold tracking-tight text-text">Pendapatan &amp; Profit — {periodLabel}</h3>
                <Link href={data.href} className="flex items-center gap-1 text-xs font-semibold text-primary transition hover:underline">
                    Lihat rincian per project <FiArrowRight className="h-3 w-3" />
                </Link>
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                <KpiCard
                    icon={FiTrendingUp} tone="blue" label="Pendapatan (Harga Jual, project Won)" value={money(data.revenue)} href={data.href}
                    delta={<DeltaBadge percent={data.revenue_delta_percent} />}
                />
                <KpiCard
                    icon={FiAward} tone={isProfitNegative ? 'rose' : 'green'}
                    label={`Profit ${data.margin_percent != null ? `(${data.margin_percent}% dari HPP)` : ''}`} value={money(data.profit)} href={data.href}
                    delta={<DeltaBadge percent={data.profit_delta_percent} />}
                />
                <KpiCard
                    icon={FiCheckCircle} tone="green" label="Project Won periode ini" value={data.won_count} href={data.href}
                    delta={<DeltaBadge percent={data.won_count_delta_percent} />}
                />
            </div>
        </div>
    );
}

/** Aktivitas lintas-departemen dalam periode terpilih — pelengkap kartu backlog di
 * bawah (yang selalu menampilkan kondisi sekarang, tidak ikut filter periode). */
function ActivitySection({ data }) {
    const items = [
        { icon: FiUsers, tone: 'blue', label: 'Lead Baru', value: data.leads_created, delta: data.leads_created_delta_percent, href: '/management/opportunities' },
        { icon: FiFileText, tone: 'amber', label: 'Quotation Dibuat', value: data.quotations_created, delta: data.quotations_created_delta_percent, href: '/management/quotations' },
        { icon: FiShoppingCart, tone: 'blue', label: 'Procurement Request Baru', value: data.procurement_requests_created, delta: data.procurement_requests_created_delta_percent, href: '/management/procurement-requests' },
        { icon: FiCreditCard, tone: 'green', label: `Invoice Diterbitkan (${data.invoiced_count})`, value: money(data.invoiced_total), delta: data.invoiced_total_delta_percent, href: '/management/invoices' },
        { icon: FiTool, tone: 'slate', label: 'Survey Baru', value: data.surveys_created, delta: data.surveys_created_delta_percent, href: '/management/surveys' },
    ];

    return (
        <div>
            <h3 className="mb-3 text-sm font-bold tracking-tight text-text">Aktivitas Periode Ini</h3>
            {/* Maksimal 4 kolom (bukan 5) — halaman ini dibatasi max-w-6xl, dan salah satu
                kartu berisi nilai rupiah yang jauh lebih panjang dari angka biasa; di lebar
                berapa pun, 5 kolom dalam max-w-6xl selalu membuat "Rp ..." terpotong. */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {items.map((item) => (
                    <KpiCard key={item.label} icon={item.icon} tone={item.tone} label={item.label} value={item.value} href={item.href} delta={<DeltaBadge percent={item.delta} />} />
                ))}
            </div>
        </div>
    );
}

function StatSection({ title, href, icon: Icon, children }) {
    return (
        <div>
            <div className="mb-3 flex items-center justify-between gap-3">
                <div className="flex items-center gap-2.5">
                    {Icon && (
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary-strong">
                            <Icon className="h-4 w-4" />
                        </span>
                    )}
                    <h3 className="text-sm font-bold tracking-tight text-text">{title}</h3>
                </div>
                {href && (
                    <Link href={href} className="flex min-h-9 items-center gap-1 text-xs font-semibold text-primary transition hover:underline">
                        Lihat semua <FiArrowRight className="h-3 w-3" />
                    </Link>
                )}
            </div>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{children}</div>
        </div>
    );
}
