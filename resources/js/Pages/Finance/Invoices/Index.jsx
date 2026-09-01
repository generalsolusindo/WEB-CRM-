import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

const statusBadge = {
    draft: 'bg-warning/10 text-warning',
    sent: 'bg-info/10 text-info',
    partially_paid: 'bg-info/10 text-info',
    paid: 'bg-success/10 text-success',
    overdue: 'bg-danger/10 text-danger',
    cancelled: 'bg-text-muted/10 text-text-muted',
};

export default function Index({ needsInvoice, readyForFinal = [], invoices, filters, phaseOptions, statusOptions }) {
    const [tab, setTab] = useState(needsInvoice.length > 0 || readyForFinal.length > 0 ? 'needs' : 'all');

    function createFinal(soId) {
        if (confirm('Buat Invoice Pelunasan (Final) untuk Sales Order ini?')) {
            router.post(`/finance/sales-orders/${soId}/final-invoice`);
        }
    }
    const [form, setForm] = useState(filters);

    function applyFilter(next) {
        const merged = { ...form, ...next };
        setForm(merged);
        router.get('/finance/invoices', merged, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title="Invoice" />
            <div className="mx-auto max-w-6xl space-y-5">
                <h1 className="text-2xl font-bold text-text">Invoice</h1>

                <div className="flex gap-2 border-b border-border">
                    <button onClick={() => setTab('needs')} className={`px-4 py-2 text-sm font-medium ${tab === 'needs' ? 'border-b-2 border-navy text-text' : 'text-text-muted'}`}>
                        Perlu Invoice ({needsInvoice.length + readyForFinal.length})
                    </button>
                    <button onClick={() => setTab('all')} className={`px-4 py-2 text-sm font-medium ${tab === 'all' ? 'border-b-2 border-navy text-text' : 'text-text-muted'}`}>
                        Semua Invoice
                    </button>
                </div>

                {tab === 'needs' ? (
                  <div className="space-y-5">
                    {readyForFinal.length > 0 && (
                        <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                            <div className="border-b border-border px-4 py-3 text-sm font-semibold text-text">SO Siap Invoice Pelunasan (Final)</div>
                            <table className="w-full text-left text-sm">
                                <tbody className="divide-y divide-border">
                                    {readyForFinal.map((so) => (
                                        <tr key={so.id} className="hover:bg-bg/70">
                                            <td className="px-4 py-3 font-semibold text-text">{so.number}</td>
                                            <td className="px-4 py-3 text-text-muted">{so.customer}</td>
                                            <td className="px-4 py-3 text-right"><button onClick={() => createFinal(so.id)} className="font-semibold text-info hover:underline">Buat Final Invoice</button></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Sales Order</th><th className="px-4 py-3">Customer</th><th className="px-4 py-3">Tipe</th><th className="px-4 py-3 text-right">Nilai SO</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {needsInvoice.map((so) => (
                                    <tr key={so.id} className="hover:bg-bg/70">
                                        <td className="px-4 py-3 font-semibold text-text">{so.number}</td>
                                        <td className="px-4 py-3 text-text-muted">{so.customer}</td>
                                        <td className="px-4 py-3 text-text-muted">{so.order_type}</td>
                                        <td className="px-4 py-3 text-right text-text-muted">{money(so.total)}</td>
                                        <td className="px-4 py-3 text-right"><Link href={`/finance/sales-orders/${so.id}/invoices/create`} className="font-semibold text-info hover:underline">Buat Invoice</Link></td>
                                    </tr>
                                ))}
                                {needsInvoice.length === 0 && <tr><td colSpan="5" className="px-4 py-12 text-center text-text-muted">Semua Sales Order sudah punya invoice muka.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                  </div>
                ) : (
                    <div className="space-y-4">
                        <div className="flex flex-wrap gap-3 rounded-xl border border-border bg-surface p-4 shadow-sm">
                            <select value={form.phase} onChange={(e) => applyFilter({ phase: e.target.value })} className="rounded-lg border border-border px-3 py-2 text-sm">
                                <option value="">Semua fase</option>
                                {phaseOptions.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
                            </select>
                            <select value={form.status} onChange={(e) => applyFilter({ status: e.target.value })} className="rounded-lg border border-border px-3 py-2 text-sm">
                                <option value="">Semua status</option>
                                {statusOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                            </select>
                        </div>
                        <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Nomor</th><th className="px-4 py-3">Customer</th><th className="px-4 py-3">Fase</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Total</th><th className="px-4 py-3 text-right">Terbayar</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                                    <tbody className="divide-y divide-border">
                                        {invoices.data.map((inv) => (
                                            <tr key={inv.id} className="hover:bg-bg/70">
                                                <td className="px-4 py-3 font-semibold text-text">{inv.number}<div className="text-xs font-normal text-text-muted">{inv.sales_order.number}</div></td>
                                                <td className="px-4 py-3 text-text-muted">{inv.sales_order.contact?.name ?? '—'}</td>
                                                <td className="px-4 py-3 uppercase text-text-muted">{inv.invoice_phase}</td>
                                                <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusBadge[inv.status]}`}>{statusOptions.find((s) => s.value === inv.status)?.label ?? inv.status}</span></td>
                                                <td className="px-4 py-3 text-right text-text-muted">{money(Number(inv.amount) + Number(inv.tax_amount))}</td>
                                                <td className="px-4 py-3 text-right text-text-muted">{money(inv.total_paid)}</td>
                                                <td className="px-4 py-3 text-right"><Link href={`/finance/invoices/${inv.id}`} className="font-medium text-info hover:underline">Lihat</Link></td>
                                            </tr>
                                        ))}
                                        {invoices.data.length === 0 && <tr><td colSpan="7" className="px-4 py-12 text-center text-text-muted">Belum ada invoice.</td></tr>}
                                    </tbody>
                                </table>
                            </div>
                            <div className="border-t border-border p-4"><Pagination links={invoices.links} /></div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
