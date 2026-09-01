import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

export default function Index({ quotations, filters, statusOptions }) {
    const [form, setForm] = useState(filters);
    function submit(e) { e.preventDefault(); router.get('/sales/quotations', form, { preserveState: true, replace: true }); }

    return <AppLayout><Head title="Quotations" /><div className="mx-auto max-w-7xl space-y-5">
        <div><h1 className="text-2xl font-bold text-text">Quotations</h1><p className="text-sm text-text-muted">Quotation dibuat dari Procurement Request yang sudah Ready.</p></div>
        <form onSubmit={submit} className="grid gap-3 rounded-xl border border-border bg-surface p-4 shadow-sm sm:grid-cols-[1fr_220px_auto_auto]">
            <input value={form.search} onChange={(e) => setForm({ ...form, search: e.target.value })} placeholder="Cari nomor, customer, perusahaan" className="rounded-lg border border-border px-3 py-2 text-sm outline-none focus:border-navy" />
            <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} className="rounded-lg border border-border px-3 py-2 text-sm"><option value="">Semua status</option>{statusOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}</select>
            <button className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white">Filter</button>
            <button type="button" onClick={() => router.get('/sales/quotations')} className="rounded-lg border border-border px-4 py-2 text-sm">Reset</button>
        </form>
        <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm"><div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Nomor</th><th className="px-4 py-3">Customer</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Valid Until</th><th className="px-4 py-3 text-right">Total</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead><tbody className="divide-y divide-border">
            {quotations.data.map((q) => <tr key={q.id} className="hover:bg-bg/70"><td className="px-4 py-3 font-semibold text-text">{q.number ?? `QT-${String(q.id).padStart(6, '0')} / R${q.revision_number}`}</td><td className="px-4 py-3"><div className="font-medium text-text">{q.contact.name}</div><div className="text-xs text-text-muted">{q.contact.company_name || '—'}</div></td><td className="px-4 py-3"><Status value={q.status} /></td><td className="px-4 py-3 text-text-muted">{q.valid_until || '—'}</td><td className="px-4 py-3 text-right font-medium text-text">{money(q.total_amount)}</td><td className="px-4 py-3 text-right"><Link href={`/sales/quotations/${q.id}`} className="font-medium text-info hover:underline">Lihat</Link></td></tr>)}
            {quotations.data.length === 0 && <tr><td colSpan="6" className="px-4 py-12 text-center text-text-muted">Belum ada quotation. Selesaikan Procurement Request hingga Ready terlebih dahulu.</td></tr>}
        </tbody></table></div><div className="border-t border-border p-4"><Pagination links={quotations.links} /></div></div>
    </div></AppLayout>;
}
function Status({ value }) { const styles = { draft: 'bg-warning/10 text-warning', sent: 'bg-info/10 text-info', confirmed: 'bg-success/10 text-success', revised: 'bg-bg text-text-muted', rejected: 'bg-danger/10 text-danger' }; return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${styles[value]}`}>{value}</span>; }
function money(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0)); }
