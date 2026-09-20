import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Button, StatusBadge, PromptDialog } from '../../../Components/ui';

export default function Show({ survey, report, canBrief, canVerify, canCancel, canManageTeam, surveyorOptions = [], checkIns = [] }) {
    const currentTeam = survey.team ?? [];
    const currentLeader = currentTeam.find((t) => t.is_leader)?.id ?? currentTeam[0]?.id ?? null;

    const briefForm = useForm({
        briefing: survey.briefing ?? '',
        surveyor_ids: currentTeam.map((t) => t.id),
        leader_id: currentLeader,
    });
    const teamForm = useForm({
        surveyor_ids: currentTeam.map((t) => t.id),
        leader_id: currentLeader,
    });
    const verifyForm = useForm({ decision: 'approve', notes: '' });
    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelling, setCancelling] = useState(false);

    function submitBrief(e) { e.preventDefault(); briefForm.post(`/operational/surveys/${survey.id}/brief`); }
    function submitTeam(e) { e.preventDefault(); teamForm.patch(`/operational/surveys/${survey.id}/team`, { preserveScroll: true }); }
    function submitVerify(e) { e.preventDefault(); verifyForm.post(`/operational/surveys/${survey.id}/verify`); }
    function cancelSurvey(reason) {
        setCancelling(true);
        router.post(`/operational/surveys/${survey.id}/cancel`, { reason }, {
            onSuccess: () => setCancelOpen(false),
            onFinish: () => setCancelling(false),
        });
    }

    return (
        <AppLayout>
            <Head title={survey.code} />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title={<span className="flex items-center gap-3">{survey.code} <StatusBadge status={survey.status} label={survey.status_label} tone="warning" /></span>}
                    subtitle={`${survey.customer} · ${survey.company || 'Tanpa perusahaan'}`}
                    back={{ href: '/operational/surveys', label: 'Kembali' }}
                    actions={canCancel && <Button onClick={() => setCancelOpen(true)} variant="ghost" className="text-danger hover:bg-danger-soft hover:text-danger">Batalkan Survey</Button>}
                />

                <section className="grid gap-4 card p-6 sm:grid-cols-2">
                    <Info label="Lokasi" value={`${survey.site_region} — ${survey.site_address}`} />
                    <Info label="Pelaksana" value={survey.delivery_mode} />
                    <Info label="Vendor" value={survey.vendor} />
                    <Info
                        label="Tim Surveyor"
                        value={currentTeam.length
                            ? currentTeam.map((t) => `${t.name}${t.is_leader ? ' (leader)' : ''}`).join('\n')
                            : 'Belum ditugaskan'}
                    />
                    {survey.notes && <div className="sm:col-span-2"><Info label="Catatan Sales" value={survey.notes} /></div>}
                </section>

                <section className="card p-6">
                    <h2 className="font-semibold text-text">Absensi Kehadiran</h2>
                    {checkIns.length === 0 ? (
                        <p className="mt-2 text-sm text-text-muted">Belum ada surveyor yang absen.</p>
                    ) : (
                        <div className="mt-3 flex flex-wrap gap-4">
                            {checkIns.map((c) => (
                                <a key={c.id} href={c.url} target="_blank" rel="noreferrer" className="flex items-center gap-3 rounded-lg border border-border p-2 text-sm">
                                    <img src={c.url} alt={c.surveyor} className="h-12 w-12 rounded-lg object-cover" />
                                    <div>
                                        <div className="font-medium text-text">{c.surveyor}</div>
                                        <div className="text-xs text-text-muted">{new Date(c.at).toLocaleString('id-ID')}</div>
                                    </div>
                                </a>
                            ))}
                        </div>
                    )}
                </section>

                {canBrief ? (
                    <form onSubmit={submitBrief} className="space-y-4 card p-6">
                        <h2 className="font-semibold text-text">Tugaskan Tim & Beri Arahan</h2>
                        <TeamPicker
                            options={surveyorOptions}
                            vendorId={survey.vendor_id}
                            selected={briefForm.data.surveyor_ids}
                            leaderId={briefForm.data.leader_id}
                            onChange={(ids, leader) => briefForm.setData({ ...briefForm.data, surveyor_ids: ids, leader_id: leader })}
                            error={briefForm.errors.surveyor_ids || briefForm.errors.leader_id}
                        />
                        <div>
                            <label className="text-sm font-medium text-text">Arahan untuk tim</label>
                            <textarea rows="4" value={briefForm.data.briefing} onChange={(e) => briefForm.setData('briefing', e.target.value)} className="input" placeholder="Titik yang harus disurvey, data yang harus diambil, kontak lokasi, dsb." />
                            {briefForm.errors.briefing && <span className="text-xs text-danger">{briefForm.errors.briefing}</span>}
                        </div>
                        <div className="flex justify-end">
                            <button disabled={briefForm.processing} className="btn btn-primary">Tugaskan & Kirim Arahan</button>
                        </div>
                    </form>
                ) : (
                    <>
                        {survey.briefing && (
                            <section className="card p-6">
                                <h2 className="font-semibold text-text">Arahan</h2>
                                <p className="mt-2 whitespace-pre-line text-sm text-text">{survey.briefing}</p>
                            </section>
                        )}
                        {canManageTeam && (
                            <form onSubmit={submitTeam} className="space-y-4 card p-6">
                                <h2 className="font-semibold text-text">Ubah Komposisi Tim</h2>
                                <p className="text-sm text-text-muted">Masih bisa diubah selama survey berjalan.</p>
                                <TeamPicker
                                    options={surveyorOptions}
                                    vendorId={survey.vendor_id}
                                    selected={teamForm.data.surveyor_ids}
                                    leaderId={teamForm.data.leader_id}
                                    onChange={(ids, leader) => teamForm.setData({ surveyor_ids: ids, leader_id: leader })}
                                    error={teamForm.errors.surveyor_ids || teamForm.errors.leader_id}
                                />
                                <div className="flex justify-end">
                                    <button disabled={teamForm.processing} className="btn btn-primary">Simpan Tim</button>
                                </div>
                            </form>
                        )}
                    </>
                )}

                {report && ['submitted', 'verified', 'rejected'].includes(report.status) && (
                    <section className="space-y-4 card p-6">
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
                                    <thead className="bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint"><tr><th className="px-3 py-2">Item Rekomendasi</th><th className="px-3 py-2">Qty</th><th className="px-3 py-2">Catatan</th></tr></thead>
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
                            <form onSubmit={submitVerify} className="space-y-3 rounded-lg border border-border bg-surface-2 p-4">
                                <h3 className="font-medium text-text">Verifikasi</h3>
                                <div className="flex gap-4 text-sm">
                                    <label className="flex items-center gap-2"><input type="radio" name="decision" checked={verifyForm.data.decision === 'approve'} onChange={() => verifyForm.setData('decision', 'approve')} /> Setujui</label>
                                    <label className="flex items-center gap-2"><input type="radio" name="decision" checked={verifyForm.data.decision === 'reject'} onChange={() => verifyForm.setData('decision', 'reject')} /> Tolak (minta revisi)</label>
                                </div>
                                <textarea rows="2" value={verifyForm.data.notes} onChange={(e) => verifyForm.setData('notes', e.target.value)} className="input" placeholder={verifyForm.data.decision === 'reject' ? 'Wajib: yang harus diperbaiki surveyor' : 'Catatan (opsional)'} />
                                {verifyForm.errors.notes && <span className="text-xs text-danger">{verifyForm.errors.notes}</span>}
                                <div className="flex justify-end">
                                    <button disabled={verifyForm.processing} className="btn btn-primary">Simpan Verifikasi</button>
                                </div>
                            </form>
                        )}
                        {report.status === 'verified' && <p className="rounded-lg bg-success/10 px-4 py-3 text-sm text-success">Laporan terverifikasi. Sudah dikembalikan ke Sales.</p>}
                    </section>
                )}
            </div>
            <PromptDialog
                open={cancelOpen}
                onClose={() => setCancelOpen(false)}
                onConfirm={cancelSurvey}
                title="Batalkan survey?"
                description="Survey akan dibatalkan dan tidak dapat dilanjutkan dalam alur aktif."
                label="Alasan pembatalan (opsional)"
                placeholder="Tuliskan alasan pembatalan"
                multiline
                processing={cancelling}
                confirmLabel="Batalkan Survey"
                confirmVariant="danger"
            />
        </AppLayout>
    );
}

function TeamPicker({ options, vendorId, selected, leaderId, onChange, error }) {
    const [showAll, setShowAll] = useState(!vendorId);

    const visible = useMemo(() => {
        const sorted = [...options].sort((a, b) => Number(b.vendor_id === vendorId) - Number(a.vendor_id === vendorId));
        if (showAll || !vendorId) return sorted;
        return sorted.filter((o) => o.vendor_id === vendorId || selected.includes(o.id));
    }, [options, vendorId, showAll, selected]);

    function toggle(id) {
        const next = selected.includes(id) ? selected.filter((x) => x !== id) : [...selected, id];
        let leader = leaderId;
        if (!next.includes(leader)) leader = next[0] ?? null;
        if (next.length && !leader) leader = next[0];
        onChange(next, leader);
    }

    return (
        <div>
            <div className="flex items-center justify-between">
                <label className="text-sm font-medium text-text">Anggota Tim Surveyor *</label>
                {vendorId != null && (
                    <button type="button" onClick={() => setShowAll((v) => !v)} className="text-xs text-info">
                        {showAll ? 'Tampilkan surveyor vendor saja' : 'Tampilkan semua surveyor'}
                    </button>
                )}
            </div>
            <div className="mt-2 divide-y divide-border rounded-lg border border-border">
                {visible.length === 0 && <p className="px-3 py-4 text-sm text-text-muted">Belum ada akun surveyor aktif.</p>}
                {visible.map((o) => {
                    const checked = selected.includes(o.id);
                    return (
                        <div key={o.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                            <label className="flex flex-1 items-center gap-2">
                                <input type="checkbox" checked={checked} onChange={() => toggle(o.id)} />
                                <span className="font-medium text-text">{o.name}</span>
                                {o.phone && <span className="text-xs text-text-muted">· {o.phone}</span>}
                                {o.vendor_id != null && <span className="rounded bg-bg px-1.5 py-0.5 text-[10px] text-text-muted">vendor</span>}
                            </label>
                            <label className={`flex items-center gap-1 text-xs ${checked ? 'text-text-muted' : 'text-text-muted/40'}`}>
                                <input type="radio" name="team-leader" disabled={!checked} checked={checked && leaderId === o.id} onChange={() => onChange(selected, o.id)} />
                                leader
                            </label>
                        </div>
                    );
                })}
            </div>
            {error && <span className="mt-1 block text-xs text-danger">{error}</span>}
        </div>
    );
}

function Info({ label, value }) {
    return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>;
}
