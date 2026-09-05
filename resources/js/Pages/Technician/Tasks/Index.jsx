import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

const taskBadge = {
    pending: 'bg-warning/10 text-warning',
    in_progress: 'bg-info/10 text-info',
    done: 'bg-success/10 text-success',
};

export default function Index({ projects, statusOptions }) {
    return (
        <AppLayout>
            <Head title="Tugas Saya" />
            <div className="mx-auto max-w-4xl space-y-5">
                <h1 className="text-2xl font-bold text-text">Tugas Saya</h1>

                {projects.length === 0 && (
                    <div className="rounded-xl border border-border bg-surface p-8 text-center text-sm text-text-muted shadow-sm">
                        Belum ada project yang ditugaskan kepada Anda.
                    </div>
                )}

                {projects.map((project) => {
                    const allDone = project.tasks.length > 0 && project.tasks.every((t) => t.status === 'done');
                    return (
                        <section key={project.id} className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-5">
                                <div>
                                    <h2 className="font-semibold text-text">{project.number}</h2>
                                    <p className="text-xs text-text-muted">{project.sales_order} · {project.customer} · status project: {project.status}{project.is_leader ? ' · Anda leader' : ''}</p>
                                    {project.status === 'in_progress' && (
                                        <span className={`mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold ${project.checked_in ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'}`}>
                                            {project.checked_in ? 'Sudah absen' : 'Belum absen'}
                                        </span>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Link href={`/technician/projects/${project.id}/delivery-notes`} className="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-text">
                                        Delivery Note
                                    </Link>
                                    {project.is_leader && project.status === 'in_progress' && (
                                        <Link
                                            href={`/technician/projects/${project.id}/bast/create`}
                                            className={`rounded-lg px-4 py-2 text-sm font-semibold text-white ${allDone ? 'bg-navy' : 'pointer-events-none bg-navy/40'}`}
                                            title={allDone ? '' : 'Semua task harus selesai dulu'}
                                        >
                                            Submit BAST
                                        </Link>
                                    )}
                                </div>
                            </div>
                            <table className="w-full text-left text-sm">
                                <tbody className="divide-y divide-border">
                                    {project.tasks.map((t) => (
                                        <tr key={t.id} className="hover:bg-bg/70">
                                            <td className="px-5 py-3">
                                                <div className="font-medium text-text">{t.title}</div>
                                                <div className="text-xs text-text-muted">{t.scheduled_date || 'Tanpa jadwal'}</div>
                                            </td>
                                            <td className="px-5 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${taskBadge[t.status]}`}>{statusOptions.find((s) => s.value === t.status)?.label ?? t.status}</span></td>
                                            <td className="px-5 py-3 text-right"><Link href={`/technician/tasks/${t.id}`} className="font-medium text-info hover:underline">Buka</Link></td>
                                        </tr>
                                    ))}
                                    {project.tasks.length === 0 && <tr><td className="px-5 py-6 text-center text-text-muted">Belum ada task.</td></tr>}
                                </tbody>
                            </table>
                        </section>
                    );
                })}
            </div>
        </AppLayout>
    );
}
