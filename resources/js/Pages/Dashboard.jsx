import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
}

export default function Dashboard({ salesActions = null, procurementActions = null, financeActions = null, operationalActions = null, managementOverview = null }) {
    const { auth } = usePage().props;
    const user = auth?.user;

    function logout() {
        router.post('/logout');
    }

    return (
        <AppLayout>
            <Head title="Dashboard" />

            <div className="mx-auto max-w-3xl space-y-5">
                <div className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <h1 className="text-xl font-semibold text-text">
                        Selamat datang, {user?.name}
                    </h1>
                    <p className="mt-1 text-sm text-text-muted">Role Anda: {user?.role}</p>
                    <button
                        onClick={logout}
                        className="mt-5 rounded-lg bg-navy px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-light"
                    >
                        Logout
                    </button>
                </div>

                {managementOverview && <ManagementOverview data={managementOverview} />}

                {procurementActions && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold text-text">Perlu Aksi</h2>
                        <ActionCard title="PR baru masuk — belum dikerjakan" items={procurementActions.submitted} />
                        <ActionCard title="PR sedang dicari — belum Ready" items={procurementActions.searching} />
                        <ActionCard title="Pengadaan project belum selesai" items={procurementActions.project_procurement ?? []} />
                    </div>
                )}

                {operationalActions && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold text-text">Perlu Aksi</h2>
                        <ActionCard title="Sales Order siap dibuat Project" items={operationalActions.needs_project} />
                        <ActionCard title="Project tahap Perencanaan" items={operationalActions.planning} />
                        <ActionCard title="Project menunggu barang" items={operationalActions.waiting_resource} />
                        <ActionCard title="BAST menunggu verifikasi" items={operationalActions.bast_to_verify} />
                    </div>
                )}

                {financeActions && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold text-text">Perlu Aksi</h2>
                        <ActionCard title="SO baru — perlu Invoice Muka" items={financeActions.needs_upfront_invoice} />
                        <ActionCard title="Invoice masih Draft" items={financeActions.draft} />
                        <ActionCard title="Invoice terkirim belum lunas" items={financeActions.unpaid_sent} />
                        <ActionCard title="Invoice jatuh tempo" items={financeActions.overdue} />
                        <ActionCard title="SO siap Invoice Pelunasan" items={financeActions.ready_for_final} />
                    </div>
                )}

                {salesActions && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold text-text">Perlu Aksi</h2>
                        <ActionCard
                            title="Procurement Ready — buat Quotation"
                            items={salesActions.ready_without_quotation}
                        />
                        <ActionCard
                            title="Quotation masih Draft"
                            items={salesActions.draft_quotations}
                        />
                        <ActionCard
                            title="Quotation Sent lebih dari 7 hari"
                            items={salesActions.stale_sent_quotations}
                        />
                        <ActionCard
                            title="Sales Order siap ditutup sebagai Won"
                            items={salesActions.ready_to_win}
                        />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function ManagementOverview({ data }) {
    return (
        <div className="space-y-4">
            <h2 className="text-lg font-semibold text-text">Ringkasan Monitoring</h2>

            <StatSection title="Sales">
                <StatGrid items={data.sales.leads_by_stage} />
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    <Stat label="Quotation Terbuka (Draft/Sent)" value={data.sales.open_quotations} />
                    <Stat label="Nilai Pipeline (Quotation Terbuka)" value={money(data.sales.pipeline_value)} />
                </div>
            </StatSection>

            <StatSection title="Procurement">
                <StatGrid items={data.procurement.by_status} />
            </StatSection>

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

            <StatSection title="Survey">
                <StatGrid items={data.survey.by_status} />
            </StatSection>
        </div>
    );
}

function StatSection({ title, children }) {
    return (
        <div className="rounded-xl border border-border bg-surface p-5 shadow-sm">
            <h3 className="mb-3 text-sm font-semibold text-text">{title}</h3>
            {children}
        </div>
    );
}

function StatGrid({ items }) {
    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {items.map((item, i) => (
                <div key={i} className="rounded-lg bg-bg px-3 py-2">
                    <div className="text-lg font-bold text-text">{item.count}</div>
                    <div className="text-xs text-text-muted">{item.label}</div>
                </div>
            ))}
        </div>
    );
}

function Stat({ label, value, warn = false }) {
    return (
        <div className="rounded-lg bg-bg px-3 py-2">
            <div className={`text-lg font-bold ${warn ? 'text-danger' : 'text-text'}`}>{value}</div>
            <div className="text-xs text-text-muted">{label}</div>
        </div>
    );
}

function ActionCard({ title, items }) {
    return (
        <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
            <div className="flex items-center justify-between border-b border-border px-5 py-3">
                <span className="text-sm font-semibold text-text">{title}</span>
                <span className="rounded-full bg-bg px-2 py-0.5 text-xs font-semibold text-text-muted">
                    {items.length}
                </span>
            </div>
            {items.length === 0 ? (
                <p className="px-5 py-4 text-sm text-text-muted">Tidak ada.</p>
            ) : (
                <ul className="divide-y divide-border">
                    {items.map((item, i) => (
                        <li key={i}>
                            <Link href={item.href} className="block px-5 py-3 text-sm text-info hover:bg-bg">
                                {item.label}
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
