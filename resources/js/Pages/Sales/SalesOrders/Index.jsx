import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Pagination from '../../../Components/Pagination';
import AppLayout from '../../../Layouts/AppLayout';

export default function Index({ orders, filters, statusOptions }) {
    const [form, setForm] = useState(filters);

    function submit(event) {
        event.preventDefault();
        router.get('/sales/sales-orders', form, { preserveState: true, replace: true });
    }

    return <AppLayout><Head title="Sales Orders" /><div className="mx-auto max-w-7xl space-y-5">
        <div><h1 className="text-2xl font-bold text-text">Sales Orders</h1><p className="text-sm text-text-muted">Pantau deal yang sudah dikonfirmasi dan perkembangan pembayaran dari Finance.</p></div>
        <form onSubmit={submit} className="grid gap-3 rounded-xl border border-border bg-surface p-4 shadow-sm sm:grid-cols-[1fr_220px_auto_auto]">
            <input value={form.search} onChange={(event) => setForm({ ...form, search: event.target.value })} placeholder="Cari nomor SO, customer, perusahaan" className="rounded-lg border border-border px-3 py-2 text-sm outline-none focus:border-navy" />
            <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })} className="rounded-lg border border-border px-3 py-2 text-sm"><option value="">Semua status</option>{statusOptions.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}</select>
            <button className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white">Filter</button>
            <button type="button" onClick={() => router.get('/sales/sales-orders')} className="rounded-lg border border-border px-4 py-2 text-sm">Reset</button>
        </form>
        <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm"><div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Nomor</th><th className="px-4 py-3">Customer</th><th className="px-4 py-3">Tipe / Pembayaran</th><th className="px-4 py-3">Status SO</th><th className="px-4 py-3">Invoice Terakhir</th><th className="px-4 py-3 text-right">Total</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead><tbody className="divide-y divide-border">
            {orders.data.map((order) => { const invoice = order.invoices?.[0]; return <tr key={order.id} className="hover:bg-bg/70"><td className="px-4 py-3"><div className="font-semibold text-text">{order.number ?? `SO-${String(order.id).padStart(6, '0')}`}</div><div className="text-xs text-text-muted">{order.quotation.number ?? `QT-${String(order.quotation_id).padStart(6, '0')} / R${order.quotation.revision_number}`}</div></td><td className="px-4 py-3"><div className="font-medium text-text">{order.contact.name}</div><div className="text-xs text-text-muted">{order.contact.company_name || '—'}</div></td><td className="px-4 py-3"><div>{label(order.order_type)}</div><div className="text-xs text-text-muted">{label(order.payment_rule)}</div></td><td className="px-4 py-3"><Status value={order.status} /></td><td className="px-4 py-3">{invoice ? <><InvoiceStatus value={invoice.status} /><div className="mt-1 text-xs capitalize text-text-muted">{invoice.invoice_phase} · jatuh tempo {date(invoice.due_date)}</div></> : <span className="text-text-muted">Belum ada</span>}</td><td className="px-4 py-3 text-right font-medium text-text">{money(order.total_amount)}</td><td className="px-4 py-3 text-right"><Link href={`/sales/sales-orders/${order.id}`} className="font-medium text-info hover:underline">Lihat</Link></td></tr>; })}
            {orders.data.length === 0 && <tr><td colSpan="7" className="px-4 py-12 text-center text-text-muted">Belum ada Sales Order. Konfirmasi deal dari quotation berstatus Sent terlebih dahulu.</td></tr>}
        </tbody></table></div><div className="border-t border-border p-4"><Pagination links={orders.links} /></div></div>
    </div></AppLayout>;
}

function Status({ value }) { const styles = { confirmed: 'bg-info/10 text-info', in_progress: 'bg-warning/10 text-warning', completed: 'bg-success/10 text-success', cancelled: 'bg-danger/10 text-danger' }; return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${styles[value] || 'bg-bg text-text-muted'}`}>{label(value)}</span>; }
function InvoiceStatus({ value }) { const styles = { draft: 'bg-bg text-text-muted', sent: 'bg-info/10 text-info', partially_paid: 'bg-warning/10 text-warning', paid: 'bg-success/10 text-success', overdue: 'bg-danger/10 text-danger', cancelled: 'bg-danger/10 text-danger' }; return <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${styles[value] || 'bg-bg text-text-muted'}`}>{label(value)}</span>; }
function label(value) { return String(value || '—').replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase()); }
function date(value) { if (!value) return '—'; const d = new Date(String(value).length <= 10 ? `${value}T00:00:00` : value); return Number.isNaN(d.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(d); }
function money(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0)); }
