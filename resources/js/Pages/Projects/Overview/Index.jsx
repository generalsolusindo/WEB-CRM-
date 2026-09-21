import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, PillTabs, DataTable, StatusBadge, EmptyState, Pagination, DateRangeFilter } from '../../../Components/ui';

export default function Index({ projects, filters, statusOptions, role }) {
    const base = role === 'management' ? '/management/projects' : '/project-manager/projects';
    const isMgmt = role === 'management';

    function query(overrides) {
        return {
            ...(filters.status ? { status: filters.status } : {}),
            ...(filters.from ? { from: filters.from } : {}),
            ...(filters.to ? { to: filters.to } : {}),
            ...overrides,
        };
    }

    function setStatus(status) {
        router.get(base, query({ status: status || undefined }), { preserveState: true, replace: true });
    }

    function applyDates(from, to) {
        router.get(base, query({ from: from || undefined, to: to || undefined }), { preserveState: true, replace: true });
    }

    function resetDates() {
        router.get(base, query({ from: undefined, to: undefined }), { preserveState: true, replace: true });
    }

    const tabs = [{ value: '', label: 'Semua' }, ...statusOptions.map((o) => ({ value: o.value, label: o.label }))];

    const columns = [
        {
            key: 'number',
            label: 'Nomor',
            render: (p) => (
                <span className="flex items-center gap-2">
                    <span className="font-medium text-text">{p.number}</span>
                    {p.is_won && <StatusBadge status="won" label="Won" />}
                </span>
            ),
        },
        { key: 'customer', label: 'Customer', render: (p) => p.customer || '—' },
        { key: 'status', label: 'Status', render: (p) => <StatusBadge status={p.status} label={p.status_label} /> },
        {
            key: 'material',
            label: 'Material',
            render: (p) => (p.material_status && p.material_status.total > 0
                ? <span className={`badge ${p.material_status.is_complete ? 'badge-success' : 'badge-warning'}`}>{p.material_status.complete}/{p.material_status.total} lengkap</span>
                : '—'),
        },
        ...(isMgmt ? [{ key: 'delegated', hideBelow: 'md', label: 'Didelegasikan ke', render: (p) => p.delegated_to || '—' }] : []),
    ];

    return (
        <AppLayout>
            <Head title={isMgmt ? 'Semua Project' : 'Project Saya'} />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title={isMgmt ? 'Semua Project' : 'Project Saya'}
                    subtitle={isMgmt
                        ? 'Monitoring seluruh project — read-only. Eksekusi harian tetap di tangan Operational.'
                        : 'Project yang didelegasikan Manager kepada Anda.'}
                />
                <PillTabs tabs={tabs} value={filters.status || ''} onChange={setStatus} />
                {isMgmt && <DateRangeFilter from={filters.from} to={filters.to} onApply={applyDates} onReset={resetDates} />}
                <DataTable
                    columns={columns}
                    rows={projects.data}
                    rowKey="id"
                    rowHref={(p) => `${base}/${p.id}`}
                    empty={<EmptyState title={isMgmt ? 'Belum ada project.' : 'Belum ada project yang didelegasikan kepada Anda.'} />}
                    footer={<Pagination links={projects.links} />}
                />
            </div>
        </AppLayout>
    );
}
