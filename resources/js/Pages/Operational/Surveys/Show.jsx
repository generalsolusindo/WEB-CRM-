import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Show({ survey, report, canBrief, canVerify, canCancel }) {
    const briefForm = useForm({ briefing: survey.briefing ?? '' });
    const verifyForm = useForm({ decision: 'approve', notes: '' });

    function submitBrief(e) { e.preventDefault(); briefForm.post(`/operational/surveys/${survey.id}/brief`); }
    function submitVerify(e) { e.preventDefault(); verifyForm.post(`/operational/surveys/${survey.id}/verify`); }
    function cancelSurvey() {
        const reason = prompt('Alasan pembatalan survey (opsional):');
        if (reason === null) return;
        router.post(`/operational/surveys/${survey.id}/cancel`, { reason });
    }

    return (
        <AppLayout>
            <Head title={survey.code} />
            <div className="mx-auto max-w-3xl space-y-5">
                <div>
                    <Link href="/operational/surveys" className="text-sm text-info">← Kembali</Link>
                    <div className="mt-2 flex items-center gap-3">
                        <h1 className="text-2xl font-bold text-text">{survey.code}</h1>
                        <span className="rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">{survey.status_label}</span>
                    </div>
                    <p className="text-sm text-text-muted">{survey.customer} · {survey.company || 'Tanpa perusahaan'}</p>
                    {canCancel && (
                        <button onClick={cancelSurvey} className="mt-3 rounded-lg border border-danger/30 px-4 py-2 text-sm text-danger">Batalkan Survey</button>
                    )}
                </div>

                <section className="grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-2">
                    <Info label="Lokasi" value={`${survey.site_region} — ${survey.site_address}`} />
                    <Info label="Pelaksana" value={survey.delivery_mode} />
                    <Info label="Surveyor" value={survey.surveyor} />
                    <Info label="Vendor" value={survey.vendor} />
                    {survey.notes && <div className="sm:col-span-2"><Info label="Catatan Sales" value={survey.notes} /></div>}
                </section>

                {canBrief ? (
                    <form onSubmit={submitBrief} className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <h2 className="font-semibold text-text">Arahan untuk Surveyor</h2>
                        <textarea rows="4" value={briefForm.data.briefing} onChange={(e) => briefForm.setData('briefing', e.target.value)} className="input" placeholder="Titik yang harus disurvey, data yang harus diambil, kontak lokasi, dsb." />
                        {briefForm.errors.briefing && <span className="text-xs text-danger">{briefForm.errors.briefing}</span>}
                        <div className="flex justify-end">
                            <button disabled={briefForm.processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">Kirim Arahan</button>
                        </div>
                    </form>
                ) : survey.briefing && (
                    <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <h2 className="font-semibold text-text">Arahan</h2>
                        <p className="mt-2 whitespace-pre-line text-sm text-text">{survey.briefing}</p>
                    </section>
                )}

                {report && ['submitted', 'verified', 'rejected'].includes(report.status) && (
                    <section className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <div className="flex items-center justify-between">
                            <h2 className="font-semibold text-text">Laporan Survey · rev.{report.revision}</h2>
                            <span className="text-xs text-text-muted">{report.submitted_by} · {report.submitted_at?.slice(0, 16).replace('T', ' ')}</span>
                        </div>
                        {report.review_notes && report.status === 'rejected' && (
                            <div className="rounded-lg bg-danger/10 p-3 text-sm text-danger">Catatan revisi terakhir: {report.review_notes}</div>
                        )}
                        <div>
                            <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">Ringkasan</div>
                            <p className="mt-1 whitespace-pre-line text-sm text-text">{report.summary || '—'}</p>
                        </div>
                        {report.items.length > 0 && (
                            <div className="overflow-x-auto rounded-lg border border-border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-bg text-text-muted"><tr><th className="px-3 py-2">Item Rekomendasi</th><th className="px-3 py-2">Qty</th><th className="px-3 py-2">Catatan</th></tr></thead>
                                    <tbody className="divide-y divide-border">
                                        {report.items.map((i) => (
                                            <tr key={i.id}><td className="px-3 py-2 font-medium text-text">{i.item_name}</td><td className="px-3 py-2 text-text-muted">{i.qty} {i.unit}</td><td className="px-3 py-2 text-text-muted">{i.notes || '—'}</td></tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {report.attachments.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {report.attachments.map((a) => <a key={a.id} href={a.url} target="_blank" rel="noreferrer" className="rounded-lg border border-info/30 px-3 py-1.5 text-xs font-semibold text-info">{a.name}</a>)}
                            </div>
                        )}

                        {canVerify && (
                            <form onSubmit={submitVerify} className="space-y-3 rounded-lg border border-border bg-bg/50 p-4">
                                <h3 className="font-medium text-text">Verifikasi</h3>
                                <div className="flex gap-4 text-sm">
                                    <label className="flex items-center gap-2"><input type="radio" name="decision" checked={verifyForm.data.decision === 'approve'} onChange={() => verifyForm.setData('decision', 'approve')} /> Setujui</label>
                                    <label className="flex items-center gap-2"><input type="radio" name="decision" checked={verifyForm.data.decision === 'reject'} onChange={() => verifyForm.setData('decision', 'reject')} /> Tolak (minta revisi)</label>
                                </div>
                                <textarea rows="2" value={verifyForm.data.notes} onChange={(e) => verifyForm.setData('notes', e.target.value)} className="input" placeholder={verifyForm.data.decision === 'reject' ? 'Wajib: yang harus diperbaiki surveyor' : 'Catatan (opsional)'} />
                                {verifyForm.errors.notes && <span className="text-xs text-danger">{verifyForm.errors.notes}</span>}
                                <div className="flex justify-end">
                                    <button disabled={verifyForm.processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">Simpan Verifikasi</button>
                                </div>
                            </form>
                        )}
                        {report.status === 'verified' && <p className="rounded-lg bg-success/10 px-4 py-3 text-sm text-success">Laporan terverifikasi. Sudah dikembalikan ke Sales.</p>}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

function Info({ label, value }) {
    return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>;
}
