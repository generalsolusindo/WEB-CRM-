import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Show({ project, canDelegate, projectManagerOptions = [] }) {
    const delegateForm = useForm({ project_manager_id: project.delegated_to?.id ?? '' });

    function submitDelegate(e) {
        e.preventDefault();
        delegateForm.put(`/management/projects/${project.id}/delegate`, { preserveScroll: true });
    }

    const backHref = canDelegate ? '/management/projects' : '/project-manager/projects';

    return (
        <AppLayout>
            <Head title={project.number} />
            <div className="mx-auto max-w-3xl space-y-5">
                <div>
                    <Link href={backHref} className="text-sm text-info">← Kembali</Link>
                    <div className="mt-2 flex items-center gap-3">
                        <h1 className="text-2xl font-bold text-text">{project.number}</h1>
                        <span className="rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">{project.status_label}</span>
                    </div>
                    <p className="text-sm text-text-muted">{project.customer} · {project.company || 'Tanpa perusahaan'} · {project.sales_order}</p>
                </div>

                <section className="grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-2">
                    <Info label="Tipe Order" value={project.order_type} />
                    <Info
                        label="Status Material"
                        value={project.material_status && project.material_status.total > 0
                            ? `${project.material_status.complete} dari ${project.material_status.total} item lengkap terkirim`
                            : 'Tidak ada baris material'}
                    />
                    <Info
                        label="Didelegasikan ke"
                        value={project.delegated_to ? `${project.delegated_to.name} (oleh ${project.delegated_by}, ${new Date(project.delegated_at).toLocaleDateString('id-ID')})` : 'Belum didelegasikan — dipegang Manager'}
                    />
                </section>

                {canDelegate && (
                    <form onSubmit={submitDelegate} className="space-y-3 rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <h2 className="font-semibold text-text">Delegasi Project</h2>
                        <p className="text-sm text-text-muted">Serahkan pengawasan project ini ke salah satu Project Manager, atau kosongkan untuk kembali dipegang Manager.</p>
                        <select value={delegateForm.data.project_manager_id} onChange={(e) => delegateForm.setData('project_manager_id', e.target.value)} className="input">
                            <option value="">— Dipegang Manager (tidak didelegasikan) —</option>
                            {projectManagerOptions.map((pm) => <option key={pm.id} value={pm.id}>{pm.name}</option>)}
                        </select>
                        {delegateForm.errors.project_manager_id && <span className="block text-xs text-danger">{delegateForm.errors.project_manager_id}</span>}
                        <div className="flex justify-end">
                            <button disabled={delegateForm.processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">Simpan</button>
                        </div>
                    </form>
                )}

                <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <h2 className="font-semibold text-text">Tim Teknisi</h2>
                    {project.technicians.length === 0 ? (
                        <p className="mt-2 text-sm text-text-muted">Belum ditugaskan.</p>
                    ) : (
                        <ul className="mt-2 space-y-1 text-sm text-text">
                            {project.technicians.map((t, i) => <li key={i}>{t.name}{t.is_leader ? ' (leader)' : ''}</li>)}
                        </ul>
                    )}
                </section>

                <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <h2 className="font-semibold text-text">Task</h2>
                    {project.tasks.length === 0 ? (
                        <p className="mt-2 text-sm text-text-muted">Belum ada task.</p>
                    ) : (
                        <ul className="mt-2 divide-y divide-border text-sm">
                            {project.tasks.map((t) => (
                                <li key={t.id} className="flex justify-between py-2">
                                    <span className="text-text">{t.title}</span>
                                    <span className="text-text-muted">{t.status}{t.scheduled_date ? ` · ${t.scheduled_date}` : ''}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <h2 className="font-semibold text-text">Absensi Kehadiran</h2>
                    {project.check_ins.length === 0 ? (
                        <p className="mt-2 text-sm text-text-muted">Belum ada teknisi yang absen.</p>
                    ) : (
                        <div className="mt-3 flex flex-wrap gap-4">
                            {project.check_ins.map((c) => (
                                <a key={c.id} href={c.url} target="_blank" rel="noreferrer" className="flex items-center gap-3 rounded-lg border border-border p-2 text-sm">
                                    <img src={c.url} alt={c.technician} className="h-12 w-12 rounded-lg object-cover" />
                                    <div>
                                        <div className="font-medium text-text">{c.technician}</div>
                                        <div className="text-xs text-text-muted">{new Date(c.at).toLocaleString('id-ID')}</div>
                                    </div>
                                </a>
                            ))}
                        </div>
                    )}
                </section>

                <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <h2 className="font-semibold text-text">BAST</h2>
                    {project.bast_records.length === 0 ? (
                        <p className="mt-2 text-sm text-text-muted">Belum ada BAST.</p>
                    ) : (
                        <ul className="mt-2 divide-y divide-border text-sm">
                            {project.bast_records.map((b) => (
                                <li key={b.id} className="flex justify-between py-2">
                                    <span className="text-text">{b.submitter} · {b.submitted_at ? new Date(b.submitted_at).toLocaleString('id-ID') : '—'}</span>
                                    <span className="text-text-muted">{b.status}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function Info({ label, value }) {
    return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>;
}
