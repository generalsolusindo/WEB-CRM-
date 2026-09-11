import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { pickFile, pickFiles } from '../../../utils/fileValidation';
import { PageHeader } from '../../../Components/ui';

export default function Show({ task, project, photos, statusOptions, canWork, checkedIn, canCheckIn, selfieUrl, checkedOut, canCheckOut, checkoutSelfieUrl }) {
    const before = photos.filter((p) => p.category === 'task_before');
    const after = photos.filter((p) => p.category === 'task_after');

    const photoForm = useForm({ category: 'task_before', photos: [] });
    const checkInForm = useForm({ photo: null });
    const checkOutForm = useForm({ photo: null });

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
    function checkOut(e) {
        e.preventDefault();
        checkOutForm.post(`/technician/projects/${project.id}/checkout`, {
            forceFormData: true, preserveScroll: true, onSuccess: () => checkOutForm.reset('photo'),
        });
    }

    return (
        <AppLayout>
            <Head title={task.title} />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title={task.title}
                    subtitle={`${project.number} · status project: ${project.status}`}
                    back={{ href: '/technician/tasks', label: 'Tugas Saya' }}
                />

                <section className="card p-6">
                    <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">Deskripsi</div>
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
                            <button disabled={checkInForm.processing || !checkInForm.data.photo} className="btn btn-primary">Kirim Absen</button>
                            <span className="w-full text-[11px] text-text-muted">maks 5 MB</span>
                            {checkInForm.errors.photo && <span className="w-full text-xs text-danger">{checkInForm.errors.photo}</span>}
                        </form>
                    </section>
                ) : (
                    <section className="rounded-xl border border-border bg-surface-2 p-4 text-sm text-text-muted">
                        Absen kehadiran hanya bisa dilakukan saat project sedang berjalan.
                    </section>
                )}

                {checkedIn && (
                    checkedOut ? (
                        <section className="flex items-center gap-4 rounded-xl border border-success/30 bg-success/5 p-4">
                            {checkoutSelfieUrl && <img src={checkoutSelfieUrl} alt="Selfie checkout" className="h-14 w-14 rounded-lg object-cover" />}
                            <p className="text-sm text-success">Kamu sudah absen pulang di project ini.</p>
                        </section>
                    ) : canCheckOut ? (
                        <section className="rounded-xl border border-warning/30 bg-warning/5 p-6">
                            <h2 className="font-semibold text-text">Absen Pulang</h2>
                            <p className="mt-1 text-sm text-text-muted">Isi selfie sebelum meninggalkan lokasi project.</p>
                            <form onSubmit={checkOut} className="mt-4 flex flex-wrap items-end gap-3">
                                <input type="file" accept=".jpg,.jpeg,.png" capture="user" onChange={(e) => pickFile(checkOutForm, 'photo', e.target.files[0], 5)} className="text-sm" />
                                <button disabled={checkOutForm.processing || !checkOutForm.data.photo} className="btn btn-primary">Kirim Absen Pulang</button>
                                <span className="w-full text-[11px] text-text-muted">maks 5 MB</span>
                                {checkOutForm.errors.photo && <span className="w-full text-xs text-danger">{checkOutForm.errors.photo}</span>}
                            </form>
                        </section>
                    ) : null
                )}

                <section className="card p-6">
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

                <section className="card p-6">
                    <h2 className="mb-3 font-semibold text-text">Foto Before / After</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <PhotoGrid title="Before" items={before} />
                        <PhotoGrid title="After" items={after} />
                    </div>
                    {canWork && (
                        <form onSubmit={upload} className="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface-2 p-4">
                            <select value={photoForm.data.category} onChange={(e) => photoForm.setData('category', e.target.value)} className="rounded-lg border border-border px-2 py-2 text-sm">
                                <option value="task_before">Before</option>
                                <option value="task_after">After</option>
                            </select>
                            <input type="file" multiple accept=".jpg,.jpeg,.png" onChange={(e) => pickFiles(photoForm, 'photos', e.target.files, 5)} className="text-sm" />
                            <button disabled={photoForm.processing || photoForm.data.photos.length === 0} className="btn btn-primary">Upload</button>
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
            <div className="mb-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">{title}</div>
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
