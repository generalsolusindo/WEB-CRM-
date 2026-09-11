import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    FiFilePlus, FiEdit3, FiClock, FiAward, FiArrowRight, FiCheckCircle,
    FiUserPlus, FiFileText, FiShoppingCart, FiBarChart2, FiUserCheck,
} from 'react-icons/fi';
import AppLayout from '../Layouts/AppLayout';
import { getMenuForUser } from '../config/menuConfig';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
}

export default function Dashboard({ salesActions = null, procurementActions = null, financeActions = null, operationalActions = null, managementOverview = null }) {
    const { auth } = usePage().props;

    return (
        <AppLayout>
            <Head title="Dashboard" />
            {salesActions && <SalesDashboard data={salesActions} />}
            {managementOverview && (
                <div className="mx-auto max-w-5xl space-y-4">
                    <ManagementOverview data={managementOverview} />
                </div>
            )}
            {procurementActions && (
                <LegacyActions groups={[
                    ['PR baru masuk — belum dikerjakan', procurementActions.submitted],
                    ['PR sedang dicari — belum Ready', procurementActions.searching],
                    ['Pengadaan project belum selesai', procurementActions.project_procurement ?? []],
                ]} />
            )}
            {operationalActions && (
                <LegacyActions groups={[
                    ['Sales Order siap dibuat Project', operationalActions.needs_project],
                    ['Project tahap Perencanaan', operationalActions.planning],
                    ['Project menunggu barang', operationalActions.waiting_resource],
                    ['BAST menunggu verifikasi', operationalActions.bast_to_verify],
                ]} />
            )}
            {financeActions && (
                <LegacyActions groups={[
                    ['SO baru — perlu Invoice Muka', financeActions.needs_upfront_invoice],
                    ['Invoice masih Draft', financeActions.draft],
                    ['Invoice terkirim belum lunas', financeActions.unpaid_sent],
                    ['Invoice jatuh tempo', financeActions.overdue],
                    ['SO siap Invoice Pelunasan', financeActions.ready_for_final],
                ]} />
            )}
            {!salesActions && !managementOverview && !procurementActions && !operationalActions && !financeActions && (
                <GenericDashboard auth={auth} />
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
};
const DOT = { blue: 'bg-primary', amber: 'bg-warning', rose: 'bg-danger', green: 'bg-success' };
const BAR = { blue: 'bg-primary', amber: 'bg-warning', rose: 'bg-danger', green: 'bg-success' };

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
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

function Kpi({ icon: Icon, tone, label, value, href }) {
    return (
        <Link href={href} className="card group flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-md">
            <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${TONE[tone]}`}>
                <Icon className="h-5 w-5" />
            </span>
            <span className="min-w-0">
                <span className="block text-2xl font-bold leading-none tracking-tight text-text">{value}</span>
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

function GenericDashboard({ auth }) {
    const items = getMenuForUser(auth).filter((i) => i.href !== '#' && i.href !== '/dashboard');
    return (
        <div className="mx-auto max-w-3xl space-y-6">
            <section className="rounded-3xl bg-gradient-to-br from-navy via-navy to-primary p-7 text-white shadow-md">
                <p className="text-sm font-medium text-white/65">Selamat datang</p>
                <h1 className="mt-1.5 text-2xl font-bold tracking-tight">{auth?.user?.name}</h1>
            </section>
            <div className="grid gap-3 sm:grid-cols-2">
                {items.map((item, i) => {
                    const Icon = item.icon;
                    return (
                        <Link key={i} href={item.href} className="card group flex items-center gap-3 p-4 transition hover:-translate-y-0.5 hover:shadow-md">
                            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-soft text-primary-strong">
                                <Icon className="h-5 w-5" />
                            </span>
                            <span className="flex-1 text-sm font-semibold text-text">{item.label}</span>
                            <FiArrowRight className="h-4 w-4 text-text-faint transition group-hover:translate-x-0.5" />
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

/* ───────────────────── Management overview (tak diubah) ───────────────────── */

function ManagementOverview({ data }) {
    return (
        <div className="space-y-4">
            <h2 className="text-lg font-bold tracking-tight text-text">Ringkasan Monitoring</h2>
            <StatSection title="Sales">
                <StatGrid items={data.sales.leads_by_stage} />
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    <Stat label="Quotation Terbuka (Draft/Sent)" value={data.sales.open_quotations} />
                    <Stat label="Nilai Pipeline (Quotation Terbuka)" value={money(data.sales.pipeline_value)} />
                </div>
            </StatSection>
            <StatSection title="Procurement"><StatGrid items={data.procurement.by_status} /></StatSection>
            <StatSection title="Finance">
                <div className="grid gap-3 sm:grid-cols-2">
                    <Stat label="Total Piutang Belum Lunas" value={money(data.finance.outstanding_total)} warn={data.finance.outstanding_total > 0} />
                    <Stat label="Invoice Jatuh Tempo" value={data.finance.overdue_count} warn={data.finance.overdue_count > 0} />
                </div>
            </StatSection>
            <StatSection title="Operational — Project">
                <StatGrid items={data.operational.projects_by_status} />
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    <Stat label="Task Telat (belum selesai, lewat jadwal)" value={data.operational.tasks_overdue} warn={data.operational.tasks_overdue > 0} />
                    <Stat label="BAST Menunggu Verifikasi" value={data.operational.bast_pending} warn={data.operational.bast_pending > 0} />
                </div>
            </StatSection>
            <StatSection title="Survey"><StatGrid items={data.survey.by_status} /></StatSection>
        </div>
    );
}

function StatSection({ title, children }) {
    return (
        <div className="card p-5">
            <h3 className="mb-3 text-sm font-bold tracking-tight text-text">{title}</h3>
            {children}
        </div>
    );
}

function StatGrid({ items }) {
    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {items.map((item, i) => (
                <div key={i} className="rounded-xl bg-bg px-3 py-2.5">
                    <div className="text-lg font-bold text-text">{item.count}</div>
                    <div className="text-xs text-text-muted">{item.label}</div>
                </div>
            ))}
        </div>
    );
}

function Stat({ label, value, warn = false }) {
    return (
        <div className="rounded-xl bg-bg px-3 py-2.5">
            <div className={`text-lg font-bold ${warn ? 'text-danger' : 'text-text'}`}>{value}</div>
            <div className="text-xs text-text-muted">{label}</div>
        </div>
    );
}
