import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, FilterSelect, Button, DataTable, StatusBadge, EmptyState, Pagination } from '../../../Components/ui';

function prj(id) {
    return `PRJ-${String(id).padStart(6, '0')}`;
}

export default function Index({ projects, filters, statusOptions }) {
    const [form, setForm] = useState(filters);

    function applyFilter(next) {
        const merged = { ...form, ...next };
        setForm(merged);
        router.get('/operational/projects', merged, { preserveState: true, replace: true });
    }

    const columns = [
        {
            key: 'project',
            label: 'Project',
            render: (p) => (
                <div>
                    <div className="font-semibold text-text">{prj(p.id)}</div>
                    <div className="text-xs text-text-muted">{p.sales_order.number}</div>
                </div>
            ),
        },
        { key: 'customer', label: 'Customer', render: (p) => p.sales_order.contact.name },
        { key: 'status', label: 'Status', render: (p) => <StatusBadge status={p.status} label={statusOptions.find((s) => s.value === p.status)?.label} /> },
    ];

    return (
        <AppLayout>
            <Head title="Project" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader title="Project" subtitle="Project otomatis muncul saat invoice muka Sales Order lunas." />

                <form onSubmit={(e) => { e.preventDefault(); applyFilter({}); }}>
                    <Toolbar>
                        <SearchInput
                            value={form.search}
                            onChange={(v) => setForm({ ...form, search: v })}
                            placeholder="Cari nomor SO / customer"
                        />
                        <FilterSelect value={form.status} onChange={(v) => applyFilter({ status: v })}>
                            <option value="">Semua status</option>
                            {statusOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </FilterSelect>
                        <Button type="submit">Cari</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={projects.data}
                    rowKey="id"
                    rowHref={(p) => `/operational/projects/${p.id}`}
                    empty={<EmptyState title="Belum ada project." />}
                    footer={<Pagination links={projects.links} />}
                />
            </div>
        </AppLayout>
    );
}
