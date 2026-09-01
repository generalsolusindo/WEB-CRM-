import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Leads({ filters, byStage, bySource, funnel }) {
    const [form, setForm] = useState(filters);

    function set(name, value) {
        setForm((c) => ({ ...c, [name]: value }));
    }
    function submit(e) {
        e.preventDefault();
        router.get('/sales/reports/leads', form, { preserveState: true, replace: true });
    }
    function reset() {
        router.get('/sales/reports/leads', {}, { replace: true });
    }

    return (
        <AppLayout>
            <Head title="Report Lead" />
            <div className="mx-auto max-w-5xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">Report Lead</h1>
                    <p className="text-sm text-text-muted">Rekap pipeline lead milik Anda.</p>
                </div>

                <form onSubmit={submit} className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface p-4 shadow-sm">
                    <label className="text-sm font-medium text-text">Dari
                        <input type="date" value={form.from} onChange={(e) => set('from', e.target.value)} className="input" />
                    </label>
                    <label className="text-sm font-medium text-text">Sampai
                        <input type="date" value={form.to} onChange={(e) => set('to', e.target.value)} className="input" />
                    </label>
                    <button className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white">Terapkan</button>
                    <button type="button" onClick={reset} className="rounded-lg border border-border px-4 py-2 text-sm">Reset</button>
                </form>

                <div className="grid gap-4 sm:grid-cols-4">
                    <Stat label="Total Lead" value={funnel.leads} />
                    <Stat label="Opportunity" value={funnel.opportunities} sub={`${funnel.lead_to_opportunity}% dari lead`} />
                    <Stat label="Won" value={funnel.won} sub={`${funnel.opportunity_to_won}% dari opportunity`} />
                    <Stat label="Lost" value={funnel.lost} />
                </div>

                <div className="grid gap-5 md:grid-cols-2">
                    <TableCard title="Per Stage" rows={byStage} />
                    <TableCard title="Per Source" rows={bySource} empty="Belum ada data source." />
                </div>
            </div>
        </AppLayout>
    );
}

function Stat({ label, value, sub }) {
    return (
        <div className="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div>
            <div className="mt-1 text-2xl font-bold text-text">{value}</div>
            {sub && <div className="text-xs text-text-muted">{sub}</div>}
        </div>
    );
}

function TableCard({ title, rows, empty = 'Tidak ada data.' }) {
    return (
        <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
            <div className="border-b border-border px-5 py-3 text-sm font-semibold text-text">{title}</div>
            {rows.length === 0 ? (
                <p className="px-5 py-4 text-sm text-text-muted">{empty}</p>
            ) : (
                <table className="w-full text-left text-sm">
                    <tbody className="divide-y divide-border">
                        {rows.map((row, i) => (
                            <tr key={i}>
                                <td className="px-5 py-2 text-text-muted">{row.label}</td>
                                <td className="px-5 py-2 text-right font-medium text-text">{row.total}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
