import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

const doneStatuses = ['verified', 'closed', 'cancelled'];

export default function SurveyPanel({ leadId, surveys = [], requestable = false, deliveryOptions = [] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        site_region: '',
        site_address: '',
        delivery_mode: 'internal',
        billable: false,
        notes: '',
    });

    const hasOpenSurvey = surveys.some((s) => !doneStatuses.includes(s.status));

    function submit(e) {
        e.preventDefault();
        post(`/sales/leads/${leadId}/surveys`, {
            preserveScroll: true,
            onSuccess: () => { reset(); setOpen(false); },
        });
    }

    function finalize(surveyId, copyItems) {
        const msg = copyItems
            ? 'Salin item rekomendasi ke daftar requirement dan tutup survey?'
            : 'Tutup survey ini tanpa menyalin item?';
        if (confirm(msg)) {
            router.post(`/sales/leads/${leadId}/surveys/${surveyId}/finalize`, { copy_items: copyItems }, { preserveScroll: true });
        }
    }

    function cancelSurvey(surveyId) {
        const reason = prompt('Alasan pembatalan survey (opsional):');
        if (reason === null) return;
        router.post(`/sales/leads/${leadId}/surveys/${surveyId}/cancel`, { reason }, { preserveScroll: true });
    }

    return (
        <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-text">Survey Lapangan</h2>
                    <p className="text-sm text-text-muted">Minta survey sebelum menyusun requirement. Proses berjalan di Procurement → Finance → Operasional; Sales memantau read-only.</p>
                </div>
                {requestable && !hasOpenSurvey && (
                    <button onClick={() => setOpen((v) => !v)} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white">
                        {open ? 'Tutup' : 'Minta Survey'}
                    </button>
                )}
            </div>

            {errors.survey && <div className="mb-4 rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{errors.survey}</div>}
            {errors.lead && <div className="mb-4 rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{errors.lead}</div>}

            {surveys.length > 0 ? (
                <div className="mb-5 space-y-3">
                    {surveys.map((s) => (
                        <div key={s.id} className="rounded-lg border border-border p-4">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div className="font-medium text-text">{s.code} · {s.site_region}</div>
                                    <div className="text-xs text-text-muted">{s.delivery_mode} · {s.billable ? 'Ditagih ke customer' : 'Tidak ditagih'}</div>
                                </div>
                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${badge(s.status)}`}>{s.status_label}</span>
                            </div>
                            <div className="mt-2 whitespace-pre-line text-sm text-text">{s.site_address}</div>
                            <div className="mt-2 grid gap-1 text-xs text-text-muted sm:grid-cols-3">
                                <span>Tim Surveyor: {s.surveyor || '—'}</span>
                                <span>Vendor: {s.vendor || '—'}</span>
                                <span>Biaya: {s.cost > 0 ? money(s.cost) : '—'}</span>
                            </div>
                            {s.invoice_number && (
                                <div className="mt-2 text-xs text-text-muted">
                                    Invoice survey: <span className="font-medium text-text">{s.invoice_number}</span> · {s.invoice_status === 'paid' ? 'Sudah dibayar' : 'Belum dibayar'}
                                </div>
                            )}
                            {s.notes && <div className="mt-2 text-xs text-text-muted">Catatan: {s.notes}</div>}
                            {s.status === 'cancelled' && s.cancel_reason && <div className="mt-2 text-xs text-danger">Alasan pembatalan: {s.cancel_reason}</div>}

                            {s.can_cancel && (
                                <div className="mt-3">
                                    <button onClick={() => cancelSurvey(s.id)} className="rounded-lg border border-danger/30 px-4 py-2 text-sm text-danger">Batalkan Survey</button>
                                </div>
                            )}

                            {s.report && (
                                <div className="mt-3 rounded-lg border border-border bg-bg/50 p-3">
                                    <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">Laporan Survey (rev.{s.report.revision})</div>
                                    <p className="mt-1 whitespace-pre-line text-sm text-text">{s.report.summary || '—'}</p>
                                    {s.report.items.length > 0 && (
                                        <ul className="mt-2 space-y-1 text-xs text-text-muted">
                                            {s.report.items.map((it, i) => <li key={i}>• {it.item_name} — {it.qty} {it.unit} {it.notes ? `(${it.notes})` : ''}</li>)}
                                        </ul>
                                    )}
                                    {s.report.attachments.length > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {s.report.attachments.map((a) => <a key={a.id} href={a.url} target="_blank" rel="noreferrer" className="rounded border border-info/30 px-2 py-1 text-xs font-semibold text-info">{a.name}</a>)}
                                        </div>
                                    )}
                                </div>
                            )}

                            {s.can_finalize && (
                                <div className="mt-3 flex flex-wrap items-center gap-2">
                                    {s.report?.items?.length > 0 && (
                                        <button
                                            onClick={() => finalize(s.id, true)}
                                            disabled={s.requirements_locked}
                                            title={s.requirements_locked ? 'Requirement terkunci (sudah dikirim ke Procurement)' : ''}
                                            className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-40"
                                        >
                                            Salin {s.report.items.length} item ke Requirement &amp; Tutup
                                        </button>
                                    )}
                                    <button onClick={() => finalize(s.id, false)} className="rounded-lg border border-border px-4 py-2 text-sm">
                                        Tutup tanpa menyalin
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            ) : (
                <div className="mb-5 rounded-lg bg-bg p-6 text-center text-sm text-text-muted">Belum ada survey untuk opportunity ini.</div>
            )}

            {requestable && open && !hasOpenSurvey && (
                <form onSubmit={submit} className="space-y-4 rounded-lg border border-border bg-bg/50 p-4">
                    <h3 className="font-medium text-text">Permintaan Survey Baru</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-text">Kota / Wilayah Lokasi *
                            <input value={data.site_region} onChange={(e) => setData('site_region', e.target.value)} className="input" />
                            {errors.site_region && <span className="mt-1 block text-xs text-danger">{errors.site_region}</span>}
                        </label>
                        <label className="block text-sm font-medium text-text">Pelaksana *
                            <select value={data.delivery_mode} onChange={(e) => setData('delivery_mode', e.target.value)} className="input">
                                {deliveryOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                            </select>
                            {errors.delivery_mode && <span className="mt-1 block text-xs text-danger">{errors.delivery_mode}</span>}
                        </label>
                    </div>
                    <label className="block text-sm font-medium text-text">Alamat Lokasi Survey *
                        <textarea rows="2" value={data.site_address} onChange={(e) => setData('site_address', e.target.value)} className="input" />
                        {errors.site_address && <span className="mt-1 block text-xs text-danger">{errors.site_address}</span>}
                    </label>
                    <label className="flex items-center gap-2 text-sm font-medium text-text">
                        <input type="checkbox" checked={data.billable} onChange={(e) => setData('billable', e.target.checked)} />
                        Biaya survey ditagihkan ke customer
                    </label>
                    <label className="block text-sm font-medium text-text">Catatan
                        <textarea rows="2" value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="input" />
                        {errors.notes && <span className="mt-1 block text-xs text-danger">{errors.notes}</span>}
                    </label>
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setOpen(false)} className="rounded-lg border border-border px-4 py-2 text-sm">Batal</button>
                        <button disabled={processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            {processing ? 'Mengirim...' : 'Kirim ke Procurement'}
                        </button>
                    </div>
                </form>
            )}
        </section>
    );
}

function badge(status) {
    if (['verified', 'closed'].includes(status)) return 'bg-success/10 text-success';
    if (status === 'cancelled') return 'bg-danger/10 text-danger';
    return 'bg-warning/10 text-warning';
}
