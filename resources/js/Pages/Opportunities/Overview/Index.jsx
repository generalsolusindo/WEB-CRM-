import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, StatusBadge, EmptyState, Pagination, PillTabs, DateRangeFilter } from '../../../Components/ui';

export default function Index({ opportunities, filters = {}, stageOptions = [], role }) {
    const base = role === 'management' ? '/management/opportunities' : '/project-manager/opportunities';
    const isMgmt = role === 'management';

    function query(overrides) {
        return {
            ...(filters.stage ? { stage: filters.stage } : {}),
            ...(filters.from ? { from: filters.from } : {}),
            ...(filters.to ? { to: filters.to } : {}),
            ...overrides,
        };
    }

    function setStage(stage) {
        router.get(base, query({ stage: stage || undefined }), { preserveState: true, replace: true });
    }

    function applyDates(from, to) {
        router.get(base, query({ from: from || undefined, to: to || undefined }), { preserveState: true, replace: true });
    }

    function resetDates() {
        router.get(base, query({ from: undefined, to: undefined }), { preserveState: true, replace: true });
    }

    const tabs = [{ value: '', label: 'Semua' }, ...stageOptions.map((o) => ({ value: o.value, label: o.label }))];

    const columns = [
        { key: 'code', label: 'Kode', render: (o) => <span className="font-medium text-text">{o.code}</span> },
        { key: 'customer', label: 'Customer', render: (o) => o.company || o.customer || '—' },
        { key: 'sales', hideBelow: 'md', label: 'Sales', render: (o) => o.sales },
        { key: 'stage', label: 'Tahap', render: (o) => <StatusBadge status={o.stage} label={o.stage_label} /> },
        ...(isMgmt ? [{
            key: 'delegated', hideBelow: 'md',
            label: 'Didelegasikan ke',
            render: (o) => (o.delegated_to
                ? <span className="text-text-muted">{o.delegated_to}</span>
                : <span className="badge badge-warning">Belum ditunjuk</span>),
        }] : []),
    ];

    return (
        <AppLayout>
            <Head title={isMgmt ? 'Opportunity' : 'Opportunity Saya'} />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title={isMgmt ? 'Opportunity' : 'Opportunity Saya'}
                    subtitle={isMgmt
                        ? 'Monitoring semua opportunity — tunjuk Project Manager untuk tiap opportunity di sini.'
                        : 'Opportunity yang didelegasikan Manager kepada Anda.'}
                />
                <PillTabs tabs={tabs} value={filters.stage || ''} onChange={setStage} />
                {isMgmt && <DateRangeFilter from={filters.from} to={filters.to} onApply={applyDates} onReset={resetDates} />}
                <DataTable
                    columns={columns}
                    rows={opportunities.data}
                    rowKey="id"
                    rowHref={(o) => `${base}/${o.id}`}
                    empty={<EmptyState title={isMgmt ? 'Belum ada opportunity.' : 'Belum ada opportunity yang didelegasikan kepada Anda.'} />}
                    footer={<Pagination links={opportunities.links} />}
                />
            </div>
        </AppLayout>
    );
}
