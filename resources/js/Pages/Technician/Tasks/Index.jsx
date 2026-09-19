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
            <div className="mx-auto min-w-0 break-words max-w-4xl space-y-5">
                <PageHeader title="Tugas Saya" />

                {projects.length === 0 && <EmptyState title="Belum ada project yang ditugaskan kepada Anda." />}

                {projects.map((project) => {
                    const allDone = project.tasks.length > 0 && project.tasks.every((t) => t.status === 'done');
                    return (
                        <section key={project.id} className="card overflow-hidden p-0">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-5">
                                <div className="min-w-0 max-w-full">
                                    <h2 className="font-semibold text-text">{project.number}</h2>
                                    <p className="text-xs text-text-muted">{project.sales_order} · {project.customer} · status project: {project.status}{project.is_leader ? ' · Anda leader' : ''}</p>
                                    {project.status === 'in_progress' && (
                                        <span className={`mt-1 inline-block badge ${project.checked_out ? 'badge-success' : project.checked_in ? 'badge-primary' : 'badge-warning'}`}>
                                            {project.checked_out ? 'Sudah absen pulang' : project.checked_in ? 'Sudah absen datang' : 'Belum absen'}
                                        </span>
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
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
                            <div className="text-sm">
                                <ul className="divide-y divide-border">
                                    {project.tasks.map((t) => (
                                        <li key={t.id} className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 px-4 py-3 hover:bg-bg/70 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:px-5">
                                            <div className="min-w-0 first:col-span-2 sm:first:col-span-1">
                                                <div className="font-medium text-text">{t.title}</div>
                                                <div className="text-xs text-text-muted">{t.scheduled_date || 'Tanpa jadwal'}</div>
                                            </div>
                                            <div className="min-w-0 first:col-span-2 sm:first:col-span-1"><span className={`badge ${taskBadge[t.status]}`}>{statusOptions.find((s) => s.value === t.status)?.label ?? t.status}</span></div>
                                            <div className="text-right"><Link href={`/technician/tasks/${t.id}`} className="font-medium text-primary hover:underline">Buka</Link></div>
                                        </li>
                                    ))}
                                    {project.tasks.length === 0 && <li><div className="px-5 py-6 text-center text-text-muted">Belum ada task.</div></li>}
                                </ul>
                            </div>
                        </section>
                    );
                })}
            </div>
        </AppLayout>
    );
}
