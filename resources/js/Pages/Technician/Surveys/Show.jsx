import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';

const emptyItem = { item_name: '', qty: 1, unit: '', notes: '' };

export default function Show({ survey, report, canWork }) {
    const { data, setData, put, processing, errors } = useForm({
        summary: report?.summary ?? '',
        items: report?.items?.map((i) => ({ item_name: i.item_name, qty: i.qty, unit: i.unit ?? '', notes: i.notes ?? '' })) ?? [],
    });
    const uploadForm = useForm({ file: null });
    const [submitError, setSubmitError] = useState(null);

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
            <div className="mx-auto max-w-3xl space-y-5">
                <div>
                    <Link href="/technician/surveys" className="text-sm text-info">← Kembali</Link>
                    <div className="mt-2 flex items-center gap-3">
                        <h1 className="text-2xl font-bold text-text">{survey.code}</h1>
                        <span className="rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">{survey.status_label}</span>
                    </div>
                    <p className="text-sm text-text-muted">{survey.customer} · {survey.site_region}</p>
                </div>

                <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
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

                <section className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <h2 className="font-semibold text-text">Laporan Hasil Survey</h2>

                    <label className="block text-sm font-medium text-text">Ringkasan Hasil
                        <textarea rows="4" disabled={readOnly} value={data.summary} onChange={(e) => setData('summary', e.target.value)} className="input disabled:bg-bg" />
                        {errors.summary && <span className="text-xs text-danger">{errors.summary}</span>}
                    </label>

                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-sm font-medium text-text">Item Rekomendasi</span>
                            {!readOnly && <button type="button" onClick={addItem} className="text-sm font-semibold text-info">+ Tambah item</button>}
                        </div>
                        <div className="space-y-2">
                            {data.items.map((it, idx) => (
                                <div key={idx} className="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-[1fr,80px,80px,1fr,auto]">
                                    <input disabled={readOnly} value={it.item_name} onChange={(e) => setItem(idx, { item_name: e.target.value })} placeholder="Nama item" className="rounded-lg border border-border px-2 py-1.5 text-sm disabled:bg-bg" />
                                    <input disabled={readOnly} type="number" min="0" step="0.01" value={it.qty} onChange={(e) => setItem(idx, { qty: e.target.value })} placeholder="Qty" className="rounded-lg border border-border px-2 py-1.5 text-sm disabled:bg-bg" />
                                    <input disabled={readOnly} value={it.unit} onChange={(e) => setItem(idx, { unit: e.target.value })} placeholder="Unit" className="rounded-lg border border-border px-2 py-1.5 text-sm disabled:bg-bg" />
                                    <input disabled={readOnly} value={it.notes} onChange={(e) => setItem(idx, { notes: e.target.value })} placeholder="Catatan" className="rounded-lg border border-border px-2 py-1.5 text-sm disabled:bg-bg" />
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
                                <span key={a.id} className="flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-xs">
                                    <a href={a.url} target="_blank" rel="noreferrer" className="font-semibold text-info">{a.name}</a>
                                    {!readOnly && <button type="button" onClick={() => removeAttachment(a.id)} className="text-danger">×</button>}
                                </span>
                            ))}
                            {(report?.attachments ?? []).length === 0 && <p className="text-xs text-text-muted">Belum ada lampiran.</p>}
                        </div>
                        {!readOnly && (
                            <form onSubmit={upload} className="mt-3 flex items-center gap-2">
                                <input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(e) => uploadForm.setData('file', e.target.files[0] ?? null)} className="text-sm" />
                                <button disabled={uploadForm.processing || !uploadForm.data.file} className="rounded-lg border border-border px-3 py-1.5 text-sm disabled:opacity-50">Upload</button>
                                {uploadForm.errors.file && <span className="text-xs text-danger">{uploadForm.errors.file}</span>}
                            </form>
                        )}
                    </div>

                    {!readOnly && (
                        <div className="flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                            {submitError && <span className="mr-auto self-center text-xs text-danger">{submitError}</span>}
                            <button type="button" onClick={save} disabled={processing} className="rounded-lg border border-border px-4 py-2 text-sm font-semibold disabled:opacity-50">Simpan Draft</button>
                            <button type="button" onClick={submitReport} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white">Kirim Laporan</button>
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
