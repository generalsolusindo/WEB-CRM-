import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiFileText } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, FilterSelect, Button, DataTable, StatusBadge, Pagination, EmptyState } from '../../../Components/ui';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0));
}

export default function Index({ quotations, filters, statusOptions }) {
    const [form, setForm] = useState(filters);

    function submit(e) {
        e?.preventDefault();
        router.get('/sales/quotations', form, { preserveState: true, replace: true });
    }
    function reset() {
        setForm({ search: '', status: '' });
        router.get('/sales/quotations');
    }

    const columns = [
        {
            key: 'number', label: 'Nomor',
            render: (q) => <span className="font-semibold text-text">{q.number ?? `QT-${String(q.id).padStart(6, '0')} / R${q.revision_number}`}</span>,
        },
        {
            key: 'customer', label: 'Customer',
            render: (q) => (
                <div>
                    <div className="font-medium text-text">{q.contact.name}</div>
                    <div className="text-xs text-text-muted">{q.contact.company_name || '—'}</div>
                </div>
            ),
        },
        { key: 'status', label: 'Status', render: (q) => <StatusBadge status={q.status} /> },
        { key: 'valid_until', label: 'Valid Until', render: (q) => <span className="text-text-muted">{q.valid_until || '—'}</span> },
        { key: 'total', label: 'Total', align: 'right', render: (q) => <span className="font-semibold tabular-nums text-text">{money(q.total_amount)}</span> },
    ];

    return (
        <AppLayout>
            <Head title="Quotations" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader title="Quotations" subtitle="Quotation dibuat dari Procurement Request yang sudah Ready." />

                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={form.search} onChange={(v) => setForm({ ...form, search: v })} placeholder="Cari nomor, customer, perusahaan" />
                        <FilterSelect value={form.status} onChange={(v) => setForm({ ...form, status: v })} className="min-w-36">
                            <option value="">Semua status</option>
                            {statusOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </FilterSelect>
                        <Button type="submit">Filter</Button>
                        <Button type="button" variant="outline" onClick={reset}>Reset</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={quotations.data}
                    rowHref={(q) => `/sales/quotations/${q.id}`}
                    footer={<Pagination links={quotations.links} />}
                    empty={<EmptyState icon={FiFileText} title="Belum ada quotation" description="Selesaikan Procurement Request hingga status Ready terlebih dahulu." />}
                />
            </div>
        </AppLayout>
    );
}
