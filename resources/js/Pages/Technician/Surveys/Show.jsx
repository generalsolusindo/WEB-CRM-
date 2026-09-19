import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import { pickFile } from '../../../utils/fileValidation';
import { PageHeader, StatusBadge } from '../../../Components/ui';

const emptyItem = { item_name: '', qty: 1, unit: '', notes: '' };

export default function Show({ survey, report, canWork, canSubmit, checkedIn, canCheckIn, selfieUrl, checkedOut, canCheckOut, checkoutSelfieUrl }) {
    const { data, setData, put, processing, errors } = useForm({
        summary: report?.summary ?? '',
        items: report?.items?.map((i) => ({ item_name: i.item_name, qty: i.qty, unit: i.unit ?? '', notes: i.notes ?? '' })) ?? [],
    });
    const uploadForm = useForm({ file: null });
    const checkInForm = useForm({ photo: null });
    const checkOutForm = useForm({ photo: null });
    const [submitError, setSubmitError] = useState(null);

    function checkIn(e) {
        e.preventDefault();
        checkInForm.post(`/technician/surveys/${survey.id}/checkin`, {
            forceFormData: true, preserveScroll: true, onSuccess: () => checkInForm.reset('photo'),
        });
    }
    function checkOut(e) {
        e.preventDefault();
        checkOutForm.post(`/technician/surveys/${survey.id}/checkout`, {
            forceFormData: true, preserveScroll: true, onSuccess: () => checkOutForm.reset('photo'),
        });
    }

    function save(e) { e?.preventDefault(); put(`/technician/surveys/${survey.id}/report`, { preserveScroll: true }); }
    function setItem(idx, patch) { setData('items', data.items.map((it, i) => (i === idx ? { ...it, ...patch } : it))); }
    function addItem() { setData('items', [...data.items, { ...emptyItem }]); }
    function removeItem(idx) { setData('items', data.items.filter((_, i) => i !== idx)); }

    function upload(e) {
        e.preventDefault();
        uploadForm.post(`/technician/surveys/${survey.id}/report/attachments`, { forceFormData: true, preserveScroll: true, onSuccess: () => uploadForm.reset() });
    }
    function removeAttachment(id) {
        if (confirm('Hapus lampiran ini?')) router.delete(`/technician/surveys/${survey.id}/report/attachments/${id}`, { preserveScroll: true });
    }
    function submitReport() {
        setSubmitError(null);
        // Simpan draft terbaru dulu, baru kirim.
        put(`/technician/surveys/${survey.id}/report`, {
            preserveScroll: true,
            onSuccess: () => router.post(`/technician/surveys/${survey.id}/report/submit`, {}, {
                onError: (errs) => setSubmitError(Object.values(errs)[0] ?? 'Gagal mengirim laporan.'),
            }),
        });
    }

    const readOnly = !canWork;

    return (
        <AppLayout>
            <Head title={survey.code} />
            <div className="mx-auto min-w-0 break-words max-w-3xl space-y-5">
                <PageHeader
                    title={<span className="flex flex-wrap items-center gap-3">{survey.code} <StatusBadge status={survey.status} label={survey.status_label} tone="warning" /></span>}
                    subtitle={`${survey.customer} · ${survey.site_region}`}
                    back={{ href: '/technician/surveys', label: 'Kembali' }}
                />
                {(survey.team ?? []).length > 0 && (
                    <p className="-mt-2 text-xs text-text-muted">
                        Tim: {survey.team.map((t) => `${t.name}${t.is_leader ? ' (leader)' : ''}`).join(', ')}
                    </p>
                )}

                {checkedIn ? (
                    <section className="flex items-center gap-4 rounded-xl border border-success/30 bg-success/5 p-4">
                        {selfieUrl && <img src={selfieUrl} alt="Selfie absen" className="h-14 w-14 rounded-lg object-cover" />}
                        <p className="text-sm text-success">Kamu sudah absen kehadiran di survey ini.</p>
                    </section>
                ) : canCheckIn ? (
                    <section className="rounded-xl border border-warning/30 bg-warning/5 p-4 sm:p-6">
                        <h2 className="font-semibold text-text">Absen Kehadiran</h2>
                        <p className="mt-1 text-sm text-text-muted">Wajib absen selfie sebelum bisa mengisi laporan survey.</p>
                        <form onSubmit={checkIn} className="mt-4 flex flex-wrap items-end gap-3">
                            <input type="file" accept=".jpg,.jpeg,.png" capture="user" onChange={(e) => pickFile(checkInForm, 'photo', e.target.files[0], 5)} className="min-w-0 w-full sm:w-auto sm:flex-1 text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong" />
                            <button disabled={checkInForm.processing || !checkInForm.data.photo} className="btn btn-primary">Kirim Absen</button>
                            <span className="w-full text-[11px] text-text-muted">maks 5 MB</span>
                            {checkInForm.errors.photo && <span className="w-full text-xs text-danger">{checkInForm.errors.photo}</span>}
                        </form>
                    </section>
                ) : null}

                {checkedIn && (
                    checkedOut ? (
                        <section className="flex items-center gap-4 rounded-xl border border-success/30 bg-success/5 p-4">
                            {checkoutSelfieUrl && <img src={checkoutSelfieUrl} alt="Selfie checkout" className="h-14 w-14 rounded-lg object-cover" />}
                            <p className="text-sm text-success">Kamu sudah absen pulang di survey ini.</p>
                        </section>
                    ) : canCheckOut ? (
                        <section className="rounded-xl border border-warning/30 bg-warning/5 p-4 sm:p-6">
                            <h2 className="font-semibold text-text">Absen Pulang</h2>
                            <p className="mt-1 text-sm text-text-muted">Isi selfie sebelum meninggalkan lokasi survey.</p>
                            <form onSubmit={checkOut} className="mt-4 flex flex-wrap items-end gap-3">
                                <input type="file" accept=".jpg,.jpeg,.png" capture="user" onChange={(e) => pickFile(checkOutForm, 'photo', e.target.files[0], 5)} className="min-w-0 w-full sm:w-auto sm:flex-1 text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong" />
                                <button disabled={checkOutForm.processing || !checkOutForm.data.photo} className="btn btn-primary">Kirim Absen Pulang</button>
                                <span className="w-full text-[11px] text-text-muted">maks 5 MB</span>
                                {checkOutForm.errors.photo && <span className="w-full text-xs text-danger">{checkOutForm.errors.photo}</span>}
                            </form>
                        </section>
                    ) : null
                )}

                <section className="card p-4 sm:p-6">
                    <h2 className="font-semibold text-text">Arahan Operasional</h2>
                    <p className="mt-2 whitespace-pre-line text-sm text-text">{survey.briefing || 'Belum ada arahan.'}</p>
                    <div className="mt-3 text-xs text-text-muted">Lokasi: {survey.site_address}</div>
                </section>

                {report?.status === 'rejected' && report.review_notes && (
                    <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
                        <strong>Perlu revisi (rev.{report.revision}):</strong> {report.review_notes}
                    </div>
                )}
                {report?.status === 'verified' && (
                    <div className="rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm text-success">Laporan sudah diverifikasi Operasional.</div>
                )}
                {report?.status === 'submitted' && (
                    <div className="rounded-lg border border-info/20 bg-info/10 px-4 py-3 text-sm text-info">Laporan sedang menunggu verifikasi Operasional.</div>
                )}

                <section className="space-y-4 card p-4 sm:p-6">
                    <h2 className="font-semibold text-text">Laporan Hasil Survey</h2>
                    {readOnly && !checkedIn && <p className="text-xs text-warning">Absen dulu sebelum bisa mengisi laporan.</p>}

                    <label className="block text-sm font-medium text-text">Ringkasan Hasil
                        <textarea rows="4" disabled={readOnly} value={data.summary} onChange={(e) => setData('summary', e.target.value)} className="input disabled:bg-bg" />
                        {errors.summary && <span className="text-xs text-danger">{errors.summary}</span>}
                    </label>

                    <div>
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <span className="text-sm font-medium text-text">Item Rekomendasi</span>
                            {!readOnly && <button type="button" onClick={addItem} className="text-sm font-semibold text-info">+ Tambah item</button>}
                        </div>
                        <div className="space-y-2">
                            {data.items.map((it, idx) => (
                                <div key={idx} className="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_70px_70px_minmax(0,1fr)_auto]">
                                    <input disabled={readOnly} value={it.item_name} onChange={(e) => setItem(idx, { item_name: e.target.value })} placeholder="Nama item" className="min-w-0 w-full rounded-lg border border-border px-2 py-1.5 text-sm disabled:bg-bg" />
                                    <input disabled={readOnly} type="number" min="0" step="0.01" value={it.qty} onChange={(e) => setItem(idx, { qty: e.target.value })} placeholder="Qty" className="min-w-0 w-full rounded-lg border border-border px-2 py-1.5 text-sm disabled:bg-bg" />
                                    <input disabled={readOnly} value={it.unit} onChange={(e) => setItem(idx, { unit: e.target.value })} placeholder="Unit" className="min-w-0 w-full rounded-lg border border-border px-2 py-1.5 text-sm disabled:bg-bg" />
                                    <input disabled={readOnly} value={it.notes} onChange={(e) => setItem(idx, { notes: e.target.value })} placeholder="Catatan" className="min-w-0 w-full rounded-lg border border-border px-2 py-1.5 text-sm disabled:bg-bg" />
                                    {!readOnly && <button type="button" onClick={() => removeItem(idx)} className="text-sm text-danger">Hapus</button>}
                                </div>
                            ))}
                            {data.items.length === 0 && <p className="text-xs text-text-muted">Belum ada item rekomendasi.</p>}
                        </div>
                        {errors.items && <span className="text-xs text-danger">{errors.items}</span>}
                    </div>

                    <div>
                        <span className="text-sm font-medium text-text">Foto / Dokumen</span>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {(report?.attachments ?? []).map((a) => (
                                <span key={a.id} className="flex flex-wrap items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-xs">
                                    <a href={a.url} target="_blank" rel="noreferrer" className="min-w-0 break-all font-semibold text-info">{a.name}</a>
                                    {!readOnly && <button type="button" onClick={() => removeAttachment(a.id)} className="text-danger">×</button>}
                                </span>
                            ))}
                            {(report?.attachments ?? []).length === 0 && <p className="text-xs text-text-muted">Belum ada lampiran.</p>}
                        </div>
                        {!readOnly && (
                            <form onSubmit={upload} className="mt-3 flex flex-wrap items-center gap-2">
                                <input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(e) => pickFile(uploadForm, 'file', e.target.files[0], 5)} className="min-w-0 w-full sm:w-auto sm:flex-1 text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong" />
                                <span className="ml-2 text-[11px] text-text-muted">maks 5 MB</span>
                                <button disabled={uploadForm.processing || !uploadForm.data.file} className="rounded-lg border border-border px-3 py-1.5 text-sm disabled:opacity-50">Upload</button>
                                {uploadForm.errors.file && <span className="text-xs text-danger">{uploadForm.errors.file}</span>}
                            </form>
                        )}
                    </div>

                    {!readOnly && (
                        <div className="flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                            {submitError && <span className="mr-auto self-center text-xs text-danger">{submitError}</span>}
                            <button type="button" onClick={save} disabled={processing} className="btn btn-outline">Simpan Draft</button>
                            {canSubmit
                                ? <button type="button" onClick={submitReport} className="btn btn-primary">Kirim Laporan</button>
                                : <span className="self-center text-xs text-text-muted">Hanya leader tim yang mengirim laporan final.</span>}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
