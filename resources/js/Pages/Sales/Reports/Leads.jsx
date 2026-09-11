import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, Button, Card, CardHeader, EmptyState } from '../../../Components/ui';

const DATE_CLS = 'rounded-lg border border-border-strong bg-surface px-3 py-2 text-sm text-text outline-none transition hover:border-text-faint focus:border-primary focus:shadow-[0_0_0_3px_var(--color-primary-ring)]';

export default function Leads({ filters, byStage, bySource, funnel }) {
    const [form, setForm] = useState(filters);
    const set = (name, value) => setForm((c) => ({ ...c, [name]: value }));

    function submit(e) {
        e.preventDefault();
        router.get('/sales/reports/leads', form, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title="Report Lead" />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader title="Report Lead" subtitle="Rekap pipeline lead milik Anda." />

                <form onSubmit={submit}>
                    <Toolbar>
                        <input type="date" value={form.from} onChange={(e) => set('from', e.target.value)} className={DATE_CLS} />
                        <span className="text-sm text-text-faint">—</span>
                        <input type="date" value={form.to} onChange={(e) => set('to', e.target.value)} className={DATE_CLS} />
                        <Button type="submit">Terapkan</Button>
                        <Button type="button" variant="outline" onClick={() => router.get('/sales/reports/leads', {}, { replace: true })}>Reset</Button>
                    </Toolbar>
                </form>

                <div className="grid gap-4 sm:grid-cols-4">
                    <Stat label="Total Lead" value={funnel.leads} />
                    <Stat label="Opportunity" value={funnel.opportunities} sub={`${funnel.lead_to_opportunity}% dari lead`} />
                    <Stat label="Won" value={funnel.won} sub={`${funnel.opportunity_to_won}% dari opportunity`} tone="success" />
                    <Stat label="Lost" value={funnel.lost} tone="danger" />
                </div>

                <div className="grid gap-5 md:grid-cols-2">
                    <TableCard title="Per Stage" rows={byStage} empty="Belum ada data stage." />
                    <TableCard title="Per Source" rows={bySource} empty="Belum ada data source." />
                </div>
            </div>
        </AppLayout>
    );
}

function Stat({ label, value, sub, tone }) {
    const valueColor = tone === 'success' ? 'text-success' : tone === 'danger' ? 'text-danger' : 'text-text';
    return (
        <Card>
            <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">{label}</div>
            <div className={`mt-1 text-2xl font-bold tracking-tight ${valueColor}`}>{value}</div>
            {sub && <div className="mt-0.5 text-xs text-text-muted">{sub}</div>}
        </Card>
    );
}

function TableCard({ title, rows, empty }) {
    return (
        <Card padded={false}>
            <CardHeader title={title} />
            {rows.length === 0 ? (
                <EmptyState title={empty} />
            ) : (
                <div className="divide-y divide-border">
                    {rows.map((row, i) => (
                        <div key={i} className="flex items-center justify-between px-5 py-2.5 text-sm">
                            <span className="text-text-muted">{row.label}</span>
                            <span className="font-semibold tabular-nums text-text">{row.total}</span>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}
