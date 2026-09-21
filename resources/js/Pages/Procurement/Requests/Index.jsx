import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, FilterSelect, Button, DataTable, StatusBadge, EmptyState, Pagination } from '../../../Components/ui';

export default function Index({ requests, filters, statusOptions }) {
    const [form, setForm] = useState(filters);
    function set(name, value) { setForm((c) => ({ ...c, [name]: value })); }
    function submit(e) { e.preventDefault(); router.get('/procurement/procurement-requests', form, { preserveState: true, replace: true }); }

    const columns = [
        { key: 'number', label: 'Nomor PR', render: (pr) => <div><span className="font-semibold text-text">PR-{String(pr.id).padStart(6, '0')}</span>{pr.quotations_count > 0 && pr.status !== 'ready' && <div className="mt-1 text-xs font-medium text-warning">Costing ulang</div>}</div> },
        {
            key: 'customer',
            label: 'Customer',
            render: (pr) => (
                <div>
                    <div className="font-medium text-text">{pr.lead.contact.name}</div>
                    <div className="text-xs text-text-muted">{pr.lead.contact.company_name || '—'}</div>
                </div>
            ),
        },
        { key: 'lines_count', hideBelow: 'md', label: 'Line', align: 'right', render: (pr) => <span className="tabular-nums">{pr.lines_count}</span> },
        { key: 'status', label: 'Status', render: (pr) => <StatusBadge status={pr.status} label={statusOptions.find((s) => s.value === pr.status)?.label} /> },
        { key: 'created_at', hideBelow: 'md', label: 'Tanggal', render: (pr) => pr.created_at?.slice(0, 10) },
    ];

    return (
        <AppLayout>
            <Head title="Procurement Request" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader title="Procurement Request" subtitle="Antrean sourcing dari tim Sales. Yang perlu aksi ada di atas." />

                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={form.search} onChange={(v) => set('search', v)} placeholder="Cari nomor PR / customer" />
                        <FilterSelect value={form.status} onChange={(v) => set('status', v)}>
                            <option value="">Semua status</option>
                            {statusOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </FilterSelect>
                        <Button type="submit">Filter</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={requests.data}
                    rowKey="id"
                    rowHref={(pr) => `/procurement/procurement-requests/${pr.id}`}
                    empty={<EmptyState title="Tidak ada Procurement Request." />}
                    footer={<Pagination links={requests.links} />}
                />
            </div>
        </AppLayout>
    );
}
