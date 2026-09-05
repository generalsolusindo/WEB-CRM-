import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Show({ opportunity, canDelegate, projectManagerOptions = [] }) {
    const delegateForm = useForm({ project_manager_id: opportunity.delegated_to?.id ?? '' });
    const backHref = canDelegate ? '/management/opportunities' : '/project-manager/opportunities';

    function submitDelegate(e) {
        e.preventDefault();
        delegateForm.put(`/management/opportunities/${opportunity.id}/delegate`, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={opportunity.code} />
            <div className="mx-auto max-w-2xl space-y-5">
                <div>
                    <Link href={backHref} className="text-sm text-info">← Kembali</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">{opportunity.code}</h1>
                    <p className="text-sm text-text-muted">{opportunity.company || opportunity.customer}</p>
                </div>

                <section className="grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-2">
                    <Info label="Customer" value={opportunity.customer} />
                    <Info label="Perusahaan" value={opportunity.company} />
                    <Info label="Email / Telepon" value={[opportunity.email, opportunity.phone].filter(Boolean).join(' · ')} />
                    <Info label="Sales" value={opportunity.sales} />
                    <Info label="Tahap" value={opportunity.stage_label} />
                    <Info
                        label="Project Manager"
                        value={opportunity.delegated_to ? `${opportunity.delegated_to.name} (oleh ${opportunity.delegated_by}, ${new Date(opportunity.delegated_at).toLocaleDateString('id-ID')})` : 'Belum ditunjuk'}
                    />
                    {opportunity.notes && <div className="sm:col-span-2"><Info label="Catatan" value={opportunity.notes} /></div>}
                </section>

                {canDelegate && (
                    <form onSubmit={submitDelegate} className="space-y-3 rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <h2 className="font-semibold text-text">Tunjuk Project Manager</h2>
                        <p className="text-sm text-text-muted">Wajib ditunjuk — quotation dari opportunity ini baru bisa dikirim ke customer setelah diverifikasi PM di sini dan Manager.</p>
                        <select value={delegateForm.data.project_manager_id} onChange={(e) => delegateForm.setData('project_manager_id', e.target.value)} className="input">
                            <option value="">— Belum ditunjuk —</option>
                            {projectManagerOptions.map((pm) => <option key={pm.id} value={pm.id}>{pm.name}</option>)}
                        </select>
                        {delegateForm.errors.project_manager_id && <span className="block text-xs text-danger">{delegateForm.errors.project_manager_id}</span>}
                        <div className="flex justify-end">
                            <button disabled={delegateForm.processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">Simpan</button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}

function Info({ label, value }) {
    return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>;
}
