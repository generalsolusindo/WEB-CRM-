import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, StatusBadge, StageStepper } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';

export default function Show({ project, canDelegate, projectManagerOptions = [] }) {
    const delegateForm = useForm({ project_manager_id: project.delegated_to?.id ?? '' });

    function submitDelegate(e) {
        e.preventDefault();
        feedback.expect({ success: { title: 'Delegasi project diperbarui', style: 'popup' } });
        delegateForm.put(`/management/projects/${project.id}/delegate`, { preserveScroll: true });
    }

    const backHref = canDelegate ? '/management/projects' : '/project-manager/projects';

    return (
        <AppLayout>
            <Head title={project.number} />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title={(
                        <span className="flex flex-wrap items-center gap-3">
                            {project.number}
                            <StatusBadge status={project.status} label={project.status_label} />
                            {project.is_won && <StatusBadge status="won" label="Deal Won" />}
                        </span>
                    )}
                    subtitle={`${project.customer} · ${project.company || 'Tanpa perusahaan'} · ${project.sales_order}`}
                    back={{ href: backHref, label: 'Kembali' }}
                />

                <section className="card p-6">
                    <h2 className="mb-4 font-semibold text-text">Progress Project</h2>
                    <StageStepper stages={project.stage_options} current={project.status} />
                </section>

                <section className="grid gap-x-6 gap-y-4 card p-6 sm:grid-cols-2">
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
                    <form onSubmit={submitDelegate} className="space-y-3 card p-6">
                        <h2 className="font-semibold text-text">Delegasi Project</h2>
                        <p className="text-sm text-text-muted">Serahkan pengawasan project ini ke salah satu Project Manager, atau kosongkan untuk kembali dipegang Manager.</p>
                        <select value={delegateForm.data.project_manager_id} onChange={(e) => delegateForm.setData('project_manager_id', e.target.value)} className="input">
                            <option value="">— Dipegang Manager (tidak didelegasikan) —</option>
                            {projectManagerOptions.map((pm) => <option key={pm.id} value={pm.id}>{pm.name}</option>)}
                        </select>
                        {delegateForm.errors.project_manager_id && <span className="block text-xs text-danger">{delegateForm.errors.project_manager_id}</span>}
                        <div className="flex justify-end">
                            <button disabled={delegateForm.processing} className="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                )}

                <section className="card p-6">
                    <h2 className="font-semibold text-text">Tim Teknisi</h2>
                    {project.technicians.length === 0 ? (
                        <p className="mt-2 text-sm text-text-muted">Belum ditugaskan.</p>
                    ) : (
                        <ul className="mt-2 space-y-1 text-sm text-text">
                            {project.technicians.map((t, i) => <li key={i}>{t.name}{t.is_leader ? ' (leader)' : ''}</li>)}
                        </ul>
                    )}
                </section>

                <section className="card p-6">
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

                <section className="card p-6">
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

                <section className="card p-6">
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
    return <div><div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>;
}
