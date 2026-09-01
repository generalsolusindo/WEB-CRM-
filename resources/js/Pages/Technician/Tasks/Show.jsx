import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Show({ task, project, photos, statusOptions, canWork }) {
    const before = photos.filter((p) => p.category === 'task_before');
    const after = photos.filter((p) => p.category === 'task_after');

    const photoForm = useForm({ category: 'task_before', photo: null });

    function setStatus(status) {
        router.post(`/technician/tasks/${task.id}/status`, { status }, { preserveScroll: true });
    }
    function upload(e) {
        e.preventDefault();
        photoForm.post(`/technician/tasks/${task.id}/photos`, {
            forceFormData: true, preserveScroll: true, onSuccess: () => photoForm.reset('photo'),
        });
    }

    return (
        <AppLayout>
            <Head title={task.title} />
            <div className="mx-auto max-w-3xl space-y-5">
                <div>
                    <Link href="/technician/tasks" className="text-sm text-info">← Tugas Saya</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">{task.title}</h1>
                    <p className="text-sm text-text-muted">{project.number} · status project: {project.status}</p>
                </div>

                <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">Deskripsi</div>
                    <p className="mt-1 whitespace-pre-line text-sm text-text">{task.description || '—'}</p>
                    <div className="mt-3 text-xs text-text-muted">Jadwal: {task.scheduled_date || '—'}</div>
                </section>

                <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <h2 className="mb-3 font-semibold text-text">Status Kerja</h2>
                    <div className="flex flex-wrap gap-2">
                        {statusOptions.map((s) => (
                            <button
                                key={s.value}
                                disabled={!canWork || s.value === task.status}
                                onClick={() => setStatus(s.value)}
                                className={`rounded-lg px-4 py-2 text-sm font-semibold ${s.value === task.status ? 'bg-navy text-white' : 'border border-border text-text disabled:opacity-50'}`}
                            >
                                {s.label}
                            </button>
                        ))}
                    </div>
                    {!canWork && <p className="mt-2 text-xs text-text-muted">Status hanya bisa diubah saat project berjalan.</p>}
                </section>

                <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <h2 className="mb-3 font-semibold text-text">Foto Before / After</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <PhotoGrid title="Before" items={before} />
                        <PhotoGrid title="After" items={after} />
                    </div>
                    {canWork && (
                        <form onSubmit={upload} className="mt-4 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-bg/50 p-4">
                            <select value={photoForm.data.category} onChange={(e) => photoForm.setData('category', e.target.value)} className="rounded-lg border border-border px-2 py-2 text-sm">
                                <option value="task_before">Before</option>
                                <option value="task_after">After</option>
                            </select>
                            <input type="file" accept=".jpg,.jpeg,.png" onChange={(e) => photoForm.setData('photo', e.target.files[0] ?? null)} className="text-sm" />
                            <button disabled={photoForm.processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Upload</button>
                            {photoForm.errors.photo && <span className="w-full text-xs text-danger">{photoForm.errors.photo}</span>}
                        </form>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function PhotoGrid({ title, items }) {
    return (
        <div>
            <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">{title}</div>
            {items.length === 0 ? (
                <p className="text-sm text-text-muted">Belum ada.</p>
            ) : (
                <div className="grid grid-cols-2 gap-2">
                    {items.map((p) => (
                        <a key={p.id} href={p.url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border border-border">
                            <img src={p.url} alt={title} className="h-24 w-full object-cover" />
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}
