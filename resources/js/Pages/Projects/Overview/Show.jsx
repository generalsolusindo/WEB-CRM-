import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, StatusBadge, StageStepper } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('id-ID') : null;
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('id-ID') : null;
}

function formatDuration(fromIso) {
    if (!fromIso) return null;
    const ms = Date.now() - new Date(fromIso).getTime();
    const days = Math.floor(ms / (1000 * 60 * 60 * 24));
    if (days <= 0) {
        const hours = Math.max(1, Math.floor(ms / (1000 * 60 * 60)));
        return `${hours} jam`;
    }
    return `${days} hari`;
}

function formatRupiah(value) {
    return `Rp${Number(value ?? 0).toLocaleString('id-ID')}`;
}

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
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 className="font-semibold text-text">Progress Project</h2>
                        <div className="flex flex-wrap items-center gap-2 text-xs">
                            {project.is_overdue && <StatusBadge status="overdue" label="Terlambat dari Target" />}
                            {project.status !== 'completed' && formatDuration(project.current_stage_since) && (
                                <span className="rounded-full bg-bg px-2.5 py-1 font-semibold text-text-muted">
                                    {formatDuration(project.current_stage_since)} di tahap ini
                                </span>
                            )}
                        </div>
                    </div>
                    <StageStepper stages={project.stage_options} current={project.status} />

                    <div className="mt-4 flex flex-wrap gap-x-6 gap-y-1 border-t border-border pt-4 text-sm">
                        <span className="text-text-muted">
                            Rencana Mulai: <span className="font-medium text-text">{formatDate(project.planned_start) || 'Belum diatur'}</span>
                        </span>
                        <span className="text-text-muted">
                            Target Selesai: <span className={`font-medium ${project.is_overdue ? 'text-danger' : 'text-text'}`}>{formatDate(project.planned_end) || 'Belum diatur'}</span>
                        </span>
                    </div>

                    {project.status_histories.length > 0 && (
                        <div className="mt-4 border-t border-border pt-4">
                            <h3 className="mb-2 text-xs font-bold uppercase tracking-wider text-text-faint">Riwayat Tahap</h3>
                            <ul className="space-y-1.5 text-sm">
                                {project.status_histories.map((h) => (
                                    <li key={h.id} className="flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5">
                                        <span className="text-text">
                                            {h.from_label ? `${h.from_label} → ${h.to_label}` : h.to_label}
                                        </span>
                                        <span className="text-xs text-text-muted">
                                            {formatDateTime(h.at)}{h.changed_by ? ` · ${h.changed_by}` : ''}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
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

                <section className="card p-6">
                    <h2 className="font-semibold text-text">Invoice</h2>
                    {project.invoices.length === 0 ? (
                        <p className="mt-2 text-sm text-text-muted">Belum ada invoice untuk Sales Order ini.</p>
                    ) : (
                        <ul className="mt-2 divide-y divide-border text-sm">
                            {project.invoices.map((inv) => (
                                <li key={inv.id} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2">
                                    <span className="text-text">
                                        {inv.number}{inv.phase_label ? ` · ${inv.phase_label}` : ''}
                                    </span>
                                    <span className="flex items-center gap-3">
                                        <span className="text-text-muted">{formatRupiah(inv.paid_amount)} / {formatRupiah(inv.grand_total)}</span>
                                        <StatusBadge status={inv.status} label={inv.status_label} />
                                    </span>
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
