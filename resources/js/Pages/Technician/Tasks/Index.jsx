import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Button, StatusBadge, EmptyState } from '../../../Components/ui';

const taskBadge = {
    pending: 'badge-warning',
    in_progress: 'badge-primary',
    done: 'badge-success',
};

export default function Index({ projects, statusOptions }) {
    return (
        <AppLayout>
            <Head title="Tugas Saya" />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader title="Tugas Saya" />

                {projects.length === 0 && <EmptyState title="Belum ada project yang ditugaskan kepada Anda." />}

                {projects.map((project) => {
                    const allDone = project.tasks.length > 0 && project.tasks.every((t) => t.status === 'done');
                    return (
                        <section key={project.id} className="card overflow-hidden p-0">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-5">
                                <div>
                                    <h2 className="font-semibold text-text">{project.number}</h2>
                                    <p className="text-xs text-text-muted">{project.sales_order} · {project.customer} · status project: {project.status}{project.is_leader ? ' · Anda leader' : ''}</p>
                                    {project.status === 'in_progress' && (
                                        <span className={`mt-1 inline-block badge ${project.checked_out ? 'badge-success' : project.checked_in ? 'badge-primary' : 'badge-warning'}`}>
                                            {project.checked_out ? 'Sudah absen pulang' : project.checked_in ? 'Sudah absen datang' : 'Belum absen'}
                                        </span>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button href={`/technician/projects/${project.id}/delivery-notes`} variant="outline" size="sm">Delivery Note</Button>
                                    {project.is_leader && project.status === 'in_progress' && (
                                        <Link
                                            href={`/technician/projects/${project.id}/bast/create`}
                                            className={`btn btn-primary ${allDone ? '' : 'pointer-events-none opacity-40'}`}
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
                                            <td className="px-5 py-3"><span className={`badge ${taskBadge[t.status]}`}>{statusOptions.find((s) => s.value === t.status)?.label ?? t.status}</span></td>
                                            <td className="px-5 py-3 text-right"><Link href={`/technician/tasks/${t.id}`} className="font-medium text-primary hover:underline">Buka</Link></td>
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
