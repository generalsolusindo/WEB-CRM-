import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { pickFile, pickFiles } from '../../../utils/fileValidation';

export default function Show({ task, project, photos, statusOptions, canWork, checkedIn, canCheckIn, selfieUrl }) {
    const before = photos.filter((p) => p.category === 'task_before');
    const after = photos.filter((p) => p.category === 'task_after');

    const photoForm = useForm({ category: 'task_before', photos: [] });
    const checkInForm = useForm({ photo: null });

    function setStatus(status) {
        router.post(`/technician/tasks/${task.id}/status`, { status }, { preserveScroll: true });
    }
    function upload(e) {
        e.preventDefault();
        photoForm.post(`/technician/tasks/${task.id}/photos`, {
            forceFormData: true, preserveScroll: true, onSuccess: () => photoForm.reset('photos'),
        });
    }
    function checkIn(e) {
        e.preventDefault();
        checkInForm.post(`/technician/projects/${project.id}/checkin`, {
            forceFormData: true, preserveScroll: true, onSuccess: () => checkInForm.reset('photo'),
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

                {checkedIn ? (
                    <section className="flex items-center gap-4 rounded-xl border border-success/30 bg-success/5 p-4">
                        {selfieUrl && <img src={selfieUrl} alt="Selfie absen" className="h-14 w-14 rounded-lg object-cover" />}
                        <p className="text-sm text-success">Kamu sudah absen kehadiran di project ini.</p>
                    </section>
                ) : canCheckIn ? (
                    <section className="rounded-xl border border-warning/30 bg-warning/5 p-6">
                        <h2 className="font-semibold text-text">Absen Kehadiran</h2>
                        <p className="mt-1 text-sm text-text-muted">Wajib absen selfie sebelum bisa mengerjakan task ini.</p>
                        <form onSubmit={checkIn} className="mt-4 flex flex-wrap items-end gap-3">
                            <input type="file" accept=".jpg,.jpeg,.png" capture="user" onChange={(e) => pickFile(checkInForm, 'photo', e.target.files[0], 5)} className="text-sm" />
                            <button disabled={checkInForm.processing || !checkInForm.data.photo} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Kirim Absen</button>
                            <span className="w-full text-[11px] text-text-muted">maks 5 MB</span>
                            {checkInForm.errors.photo && <span className="w-full text-xs text-danger">{checkInForm.errors.photo}</span>}
                        </form>
                    </section>
                ) : (
                    <section className="rounded-xl border border-border bg-bg/50 p-4 text-sm text-text-muted">
                        Absen kehadiran hanya bisa dilakukan saat project sedang berjalan.
                    </section>
                )}

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
                    {!canWork && <p className="mt-2 text-xs text-text-muted">{checkedIn ? 'Status hanya bisa diubah saat project berjalan.' : 'Absen dulu sebelum mengubah status task.'}</p>}
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
                            <input type="file" multiple accept=".jpg,.jpeg,.png" onChange={(e) => pickFiles(photoForm, 'photos', e.target.files, 5)} className="text-sm" />
                            <button disabled={photoForm.processing || photoForm.data.photos.length === 0} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Upload</button>
                            <span className="w-full text-[11px] text-text-muted">bisa pilih beberapa foto sekaligus, maks 5 MB per foto</span>
                            {photoForm.errors.photos && <span className="w-full text-xs text-danger">{photoForm.errors.photos}</span>}
                        </form>
                    )}
                    {!canWork && <p className="mt-2 text-xs text-text-muted">{checkedIn ? 'Upload foto hanya bisa saat project berjalan.' : 'Absen dulu sebelum upload foto.'}</p>}
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
