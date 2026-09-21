import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, PillTabs, Card, DataTable, StatusBadge, EmptyState, Pagination, DateRangeFilter } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
}

export default function Index({ projects, summary, filters }) {
    function query(overrides) {
        return {
            ...(filters.scope ? { scope: filters.scope } : {}),
            ...(filters.from ? { from: filters.from } : {}),
            ...(filters.to ? { to: filters.to } : {}),
            ...overrides,
        };
    }

    function setScope(scope) {
        router.get('/management/project-profit', query({ scope: scope || undefined }), { preserveState: true, replace: true });
    }

    function applyDates(from, to) {
        router.get('/management/project-profit', query({ from: from || undefined, to: to || undefined }), { preserveState: true, replace: true });
    }

    function resetDates() {
        router.get('/management/project-profit', query({ from: undefined, to: undefined }), { preserveState: true, replace: true });
    }

    const tabs = [
        { value: '', label: 'Semua' },
        { value: 'won', label: 'Won' },
        { value: 'running', label: 'Sedang Berjalan' },
    ];

    const columns = [
        { key: 'number', label: 'Nomor SO', render: (p) => <span className="font-medium text-text">{p.number}</span> },
        {
            key: 'customer',
            label: 'Customer',
            render: (p) => (
                <div>
                    <div className="font-medium text-text">{p.customer || '—'}</div>
                    <div className="text-xs text-text-muted">{p.company || '—'}</div>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (p) => (
                <div className="flex flex-col gap-1">
                    <StatusBadge status={p.project_status} label={p.project_status_label} />
                    {p.is_won && <span className="badge badge-success w-fit">Won</span>}
                </div>
            ),
        },
        { key: 'created_at', hideBelow: 'md', label: 'Mulai', render: (p) => p.created_at },
        {
            key: 'hpp',
            label: 'HPP',
            align: 'right',
            render: (p) => (
                <div>
                    <span className="tabular-nums text-text-muted">{money(p.hpp)}</span>
                    {p.uses_vendor_fee && <div className="text-[11px] text-text-muted">termasuk fee vendor</div>}
                </div>
            ),
        },
        { key: 'harga_jual', label: 'Harga Jual', align: 'right', render: (p) => <span className="tabular-nums text-text">{money(p.harga_jual)}</span> },
        {
            key: 'profit',
            label: 'Profit',
            align: 'right',
            render: (p) => (
                <div>
                    <div className={`font-semibold tabular-nums ${Number(p.profit) < 0 ? 'text-danger' : 'text-success'}`}>{money(p.profit)}</div>
                    {p.margin_percent != null && <div className="text-xs text-text-muted">{p.margin_percent}%</div>}
                </div>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Profit Project" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title="Profit Project"
                    subtitle="HPP, harga jual, dan profit tiap project — read-only, untuk pemantauan performa bisnis."
                    back={{ href: '/dashboard', label: 'Kembali ke Dashboard' }}
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">Total HPP</div>
                        <div className="mt-1 text-2xl font-bold tracking-tight text-text">{money(summary.total_hpp)}</div>
                        <div className="mt-0.5 text-xs text-text-muted">{summary.count} project</div>
                    </Card>
                    <Card>
                        <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">Total Harga Jual</div>
                        <div className="mt-1 text-2xl font-bold tracking-tight text-text">{money(summary.total_harga_jual)}</div>
                    </Card>
                    <Card>
                        <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">Total Profit</div>
                        <div className={`mt-1 text-2xl font-bold tracking-tight ${Number(summary.total_profit) < 0 ? 'text-danger' : 'text-success'}`}>{money(summary.total_profit)}</div>
                        {summary.total_hpp > 0 && (
                            <div className="mt-0.5 text-xs text-text-muted">{(summary.total_profit / summary.total_hpp * 100).toFixed(1)}% dari HPP</div>
                        )}
                    </Card>
                </div>

                <PillTabs tabs={tabs} value={filters.scope || ''} onChange={setScope} />
                <DateRangeFilter from={filters.from} to={filters.to} onApply={applyDates} onReset={resetDates} />

                <DataTable
                    columns={columns}
                    rows={projects.data}
                    rowKey="id"
                    empty={<EmptyState title="Tidak ada project pada rentang/filter ini." />}
                    footer={<Pagination links={projects.links} />}
                />
            </div>
        </AppLayout>
    );
}
