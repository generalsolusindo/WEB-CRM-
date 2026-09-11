import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, EmptyState, Pagination } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Index({ payments }) {
    const columns = [
        { key: 'paid_at', label: 'Tanggal', render: (p) => p.paid_at?.slice(0, 16).replace('T', ' ') },
        {
            key: 'invoice',
            label: 'Invoice',
            render: (p) => (
                <div>
                    <div className="font-semibold text-text">{p.invoice.number}</div>
                    <div className="text-xs uppercase text-text-muted">{p.invoice.invoice_phase} · {p.invoice.sales_order.number}</div>
                </div>
            ),
        },
        { key: 'customer', label: 'Customer', render: (p) => p.invoice.sales_order.contact.name },
        { key: 'amount_paid', label: 'Jumlah', align: 'right', render: (p) => <span className="font-semibold tabular-nums text-text">{money(p.amount_paid)}</span> },
        {
            key: 'proof',
            label: 'Bukti',
            render: (p) => (p.proof_count > 0
                ? <span className="badge badge-success">{p.proof_count} bukti</span>
                : <span className="badge badge-warning">Belum ada</span>),
        },
        { key: 'recorder', label: 'Dicatat oleh', render: (p) => p.recorder?.name ?? '—' },
    ];

    return (
        <AppLayout>
            <Head title="Pembayaran" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader title="Pembayaran" subtitle="Riwayat seluruh pembayaran. Pencatatan dilakukan dari halaman Invoice." />

                <DataTable
                    columns={columns}
                    rows={payments.data}
                    rowKey="id"
                    rowHref={(p) => `/finance/invoices/${p.invoice.id}`}
                    empty={<EmptyState title="Belum ada pembayaran tercatat." />}
                    footer={<Pagination links={payments.links} />}
                />
            </div>
        </AppLayout>
    );
}
