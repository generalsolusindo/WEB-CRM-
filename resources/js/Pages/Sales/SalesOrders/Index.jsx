import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiShoppingCart } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, FilterSelect, Button, DataTable, StatusBadge, Pagination, EmptyState } from '../../../Components/ui';

function label(v) {
    return String(v || '—').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
function date(v) {
    if (!v) return '—';
    const d = new Date(String(v).length <= 10 ? `${v}T00:00:00` : v);
    return Number.isNaN(d.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(d);
}
function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Index({ orders, filters, statusOptions }) {
    const [form, setForm] = useState(filters);

    function submit(e) {
        e?.preventDefault();
        router.get('/sales/sales-orders', form, { preserveState: true, replace: true });
    }

    const columns = [
        {
            key: 'number', label: 'Nomor',
            render: (o) => (
                <div>
                    <div className="font-semibold text-text">{o.number ?? `SO-${String(o.id).padStart(6, '0')}`}</div>
                    <div className="text-xs text-text-muted">{o.quotation.number ?? `QT-${String(o.quotation_id).padStart(6, '0')} / R${o.quotation.revision_number}`}</div>
                </div>
            ),
        },
        {
            key: 'customer', label: 'Customer',
            render: (o) => (
                <div>
                    <div className="font-medium text-text">{o.contact.name}</div>
                    <div className="text-xs text-text-muted">{o.contact.company_name || '—'}</div>
                </div>
            ),
        },
        {
            key: 'type', label: 'Tipe / Pembayaran',
            render: (o) => (
                <div className="text-text-muted">
                    <div>{label(o.order_type)}</div>
                    <div className="text-xs">{label(o.payment_rule)}</div>
                </div>
            ),
        },
        { key: 'status', label: 'Status SO', render: (o) => <StatusBadge status={o.status} label={label(o.status)} /> },
        {
            key: 'invoice', label: 'Invoice Terakhir',
            render: (o) => {
                const inv = o.invoices?.[0];
                if (!inv) return <span className="text-text-muted">Belum ada</span>;
                return (
                    <div>
                        <StatusBadge status={inv.status} label={label(inv.status)} />
                        <div className="mt-1 text-xs capitalize text-text-muted">{inv.invoice_phase} · jatuh tempo {date(inv.due_date)}</div>
                    </div>
                );
            },
        },
        { key: 'total', label: 'Total', align: 'right', render: (o) => <span className="font-semibold tabular-nums text-text">{money(o.total_amount)}</span> },
    ];

    return (
        <AppLayout>
            <Head title="Sales Orders" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader title="Sales Orders" subtitle="Pantau deal yang sudah dikonfirmasi dan perkembangan pembayaran dari Finance." />

                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={form.search} onChange={(v) => setForm({ ...form, search: v })} placeholder="Cari nomor SO, customer, perusahaan" />
                        <FilterSelect value={form.status} onChange={(v) => setForm({ ...form, status: v })} className="min-w-36">
                            <option value="">Semua status</option>
                            {statusOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </FilterSelect>
                        <Button type="submit">Filter</Button>
                        <Button type="button" variant="outline" onClick={() => router.get('/sales/sales-orders')}>Reset</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={orders.data}
                    rowHref={(o) => `/sales/sales-orders/${o.id}`}
                    footer={<Pagination links={orders.links} />}
                    empty={<EmptyState icon={FiShoppingCart} title="Belum ada Sales Order" description="Konfirmasi deal dari quotation berstatus Sent terlebih dahulu." />}
                />
            </div>
        </AppLayout>
    );
}
