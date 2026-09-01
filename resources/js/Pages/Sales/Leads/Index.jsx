import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

export default function Index({ leads, filters, stageOptions }) {
    const [form, setForm] = useState(filters);
    function set(name, value) { setForm((current) => ({ ...current, [name]: value })); }
    function submit(e) { e.preventDefault(); router.get('/sales/leads', form, { preserveState: true, replace: true }); }
    function reset() { router.get('/sales/leads', {}, { replace: true }); }

    return <AppLayout><Head title="Leads & Opportunities" /><div className="mx-auto max-w-7xl space-y-5">
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"><div><h1 className="text-2xl font-bold text-text">Leads & Opportunities</h1><p className="text-sm text-text-muted">Kelola pipeline customer milik Anda.</p></div><Link href="/sales/leads/create" className="rounded-lg bg-navy px-4 py-2 text-center text-sm font-semibold text-white hover:bg-navy-light">Tambah Lead</Link></div>
        <form onSubmit={submit} className="grid gap-3 rounded-xl border border-border bg-surface p-4 shadow-sm md:grid-cols-5">
            <input value={form.search} onChange={(e) => set('search', e.target.value)} placeholder="Cari contact/perusahaan" className="rounded-lg border border-border px-3 py-2 text-sm outline-none focus:border-navy md:col-span-2" />
            <select value={form.type} onChange={(e) => set('type', e.target.value)} className="rounded-lg border border-border px-3 py-2 text-sm"><option value="">Semua tipe</option><option value="lead">Lead</option><option value="opportunity">Opportunity</option></select>
            <select value={form.stage} onChange={(e) => set('stage', e.target.value)} className="rounded-lg border border-border px-3 py-2 text-sm"><option value="">Semua stage</option>{stageOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}</select>
            <div className="flex gap-2"><button className="flex-1 rounded-lg bg-navy px-3 py-2 text-sm text-white">Filter</button><button type="button" onClick={reset} className="rounded-lg border border-border px-3 py-2 text-sm">Reset</button></div>
        </form>
        <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm"><div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Customer</th><th className="px-4 py-3">Tipe</th><th className="px-4 py-3">Stage</th><th className="px-4 py-3">Source</th><th className="px-4 py-3">Requirement</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead><tbody className="divide-y divide-border">
            {leads.data.map((lead) => <tr key={lead.id} className="hover:bg-bg/70"><td className="px-4 py-3"><div className="font-medium text-text">{lead.contact.name}</div><div className="text-xs text-text-muted">{lead.contact.company_name || '—'}</div></td><td className="px-4 py-3"><Badge value={lead.type} /></td><td className="px-4 py-3 capitalize text-text-muted">{stageOptions.find((s) => s.value === lead.stage)?.label ?? lead.stage}</td><td className="px-4 py-3 text-text-muted">{lead.source || '—'}</td><td className="px-4 py-3 text-text-muted">{lead.requirements_count}</td><td className="px-4 py-3 text-right"><Link href={`/sales/leads/${lead.id}`} className="font-medium text-info hover:underline">Lihat</Link></td></tr>)}
            {leads.data.length === 0 && <tr><td colSpan="6" className="px-4 py-12 text-center text-text-muted">Belum ada lead yang sesuai.</td></tr>}
        </tbody></table></div><div className="border-t border-border p-4"><Pagination links={leads.links} /></div></div>
    </div></AppLayout>;
}
function Badge({ value }) { return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${value === 'opportunity' ? 'bg-info/10 text-info' : 'bg-warning/10 text-warning'}`}>{value === 'opportunity' ? 'Opportunity' : 'Lead'}</span>; }
