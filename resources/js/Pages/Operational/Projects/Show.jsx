import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Button, StatusBadge, CurrencyInput, ConfirmDialog } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Show({ project, approvalDocs = [], bastRecords, taskPhotos, checkIns = [], materialStatus, procurementProgress, statusOptions, availabilityOptions, technicianOptions, changeRequestTypes, permissions }) {
    const number = `PRJ-${String(project.id).padStart(6, '0')}`;
    const so = project.sales_order;
    const isMaterialOnly = so.order_type === 'material_only';
    const [completeOpen, setCompleteOpen] = useState(false);
    const [completing, setCompleting] = useState(false);

    function completeProject() {
        setCompleting(true);
        router.post(`/operational/projects/${project.id}/complete`, {}, {
            onSuccess: () => setCompleteOpen(false),
            onFinish: () => setCompleting(false),
        });
    }

    return (
        <AppLayout>
            <Head title={number} />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title={<span className="flex items-center gap-3">{number} <StatusBadge status={project.status} label={statusOptions.find((s) => s.value === project.status)?.label} /></span>}
                    subtitle={`${so.number} · ${so.contact.name}`}
                    back={{ href: '/operational/projects', label: 'Kembali' }}
                    actions={(
                        <>
                            {permissions.markReady && <Button onClick={() => router.post(`/operational/projects/${project.id}/ready`)} className="bg-success text-white hover:bg-success">Tandai Siap</Button>}
                            {permissions.start && <Button onClick={() => router.post(`/operational/projects/${project.id}/start`)}>Mulai Project</Button>}
                            {permissions.completeDirect && <Button onClick={() => setCompleteOpen(true)} className="bg-success text-white hover:bg-success">Selesaikan Project</Button>}
                        </>
                    )}
                />

                {(so.po_number || approvalDocs.length > 0) && (
                    <section className="card p-6">
                        <h2 className="mb-2 font-semibold text-text">Dokumen Persetujuan Customer</h2>
                        <div className="flex flex-wrap items-center gap-3 text-sm text-text-muted">
                            {so.po_number && <span>Nomor PO: <span className="font-medium text-text">{so.po_number}</span></span>}
                            {approvalDocs.map((doc) => <a key={doc.category} href={doc.url} target="_blank" rel="noreferrer" className="rounded-lg border border-info/30 px-3 py-1 text-xs font-semibold text-info">{doc.label}</a>)}
                        </div>
                    </section>
                )}

                <section className="flex items-center justify-between card p-6">
                    <div>
                        <h2 className="font-semibold text-text">Delivery Note</h2>
                        <p className="text-sm text-text-muted">
                            {isMaterialOnly
                                ? 'Project Material Only — cukup kirim semua barang lewat Delivery Note, tanpa teknisi/task/BAST.'
                                : 'Pengiriman material ke lokasi project ini.'}
                        </p>
                        {materialStatus && materialStatus.total > 0 && (
                            <span className={`badge mt-2 inline-block ${materialStatus.is_complete ? 'badge-success' : 'badge-warning'}`}>
                                Status Material: {materialStatus.complete} dari {materialStatus.total} item lengkap terkirim
                            </span>
                        )}
                    </div>
                    <Link href={`/operational/sales-orders/${so.id}/delivery-notes`} className="btn btn-outline">
                        Lihat Delivery Note
                    </Link>
                </section>

                <Planning project={project} canPlan={permissions.plan} />
                <VendorAssignment project={project} canViewSow={permissions.viewSow} />
                <ActualProcurement project={project} availabilityOptions={availabilityOptions} progress={procurementProgress} editable={permissions.manageExtraProcurement} />
                {!isMaterialOnly && (
                    <>
                        <TechnicianTeam project={project} options={technicianOptions} editable={permissions.manageTechnicianTeam} />
                        {permissions.manageBastDraft && (
                            <section className="flex items-center justify-between card p-6">
                                <div>
                                    <h2 className="font-semibold text-text">Generate BAST</h2>
                                    <p className="text-sm text-text-muted">Siapkan draft cetakan BAST untuk dibawa teknisi ke lapangan.</p>
                                </div>
                                <Link href={`/operational/projects/${project.id}/bast-draft`} className="btn btn-outline">
                                    Buka Form BAST
                                </Link>
                            </section>
                        )}
                        <CheckIns items={checkIns} />
                        <Tasks project={project} photos={taskPhotos} editable={permissions.manageTasks} />
                        <BastSection project={project} records={bastRecords} canVerify={permissions.verifyBast} />
                    </>
                )}
                <ChangeRequests project={project} types={changeRequestTypes} editable={permissions.manageChangeRequests} />
            </div>
            <ConfirmDialog
                open={completeOpen}
                onClose={() => setCompleteOpen(false)}
                onConfirm={completeProject}
                title="Selesaikan project?"
                description="Pastikan semua barang telah dikonfirmasi terkirim penuh. Project Material Only akan selesai tanpa BAST."
                tone="success"
                confirmLabel="Selesaikan Project"
                processing={completing}
            />
        </AppLayout>
    );
}

function BastSection({ project, records, canVerify }) {
    const [rejecting, setRejecting] = useState(null);
    const [approving, setApproving] = useState(null);
    const [approveProcessing, setApproveProcessing] = useState(false);
    const form = useForm({ decision: 'reject', notes: '' });

    function approve(bastId) {
        setApproveProcessing(true);
        router.put(`/operational/projects/${project.id}/bast/${bastId}`, { decision: 'approve' }, {
            preserveScroll: true,
            onSuccess: () => setApproving(null),
            onFinish: () => setApproveProcessing(false),
        });
    }
    function submitReject(e) {
        e.preventDefault();
        form.transform(() => ({ decision: 'reject', notes: form.data.notes }));
        form.put(`/operational/projects/${project.id}/bast/${rejecting}`, { preserveScroll: true, onSuccess: () => { setRejecting(null); form.reset(); } });
    }

    return (
        <section className="card p-6">
            <h2 className="mb-4 font-semibold text-text">BAST (Berita Acara Serah Terima)</h2>
            {records.length === 0 ? (
                <p className="text-sm text-text-muted">Belum ada BAST dari technician.</p>
            ) : (
                <div className="space-y-3">
                    {records.map((b) => (
                        <div key={b.id} className="rounded-lg border border-border p-3 text-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span className={`badge ${b.status === 'verified' ? 'badge-success' : b.status === 'rejected' ? 'badge-danger' : 'badge-warning'}`}>{b.status}</span>
                                    <span className="ml-2 text-text-muted">oleh {b.submitter?.name} · {b.submitted_at?.slice(0, 16).replace('T', ' ')}</span>
                                </div>
                                {canVerify && b.status === 'submitted' && (
                                    <div className="flex gap-2">
                                        <button onClick={() => setApproving(b.id)} className="rounded-lg bg-success px-3 py-1.5 text-xs font-semibold text-white">Verifikasi</button>
                                        <button onClick={() => setRejecting(b.id)} className="rounded-lg border border-danger/30 px-3 py-1.5 text-xs font-semibold text-danger">Tolak</button>
                                    </div>
                                )}
                            </div>
                            {b.notes && <p className="mt-1 text-text-muted">{b.notes}</p>}
                            <div className="mt-2 flex flex-wrap gap-2">
                                {b.documents.map((a) => <a key={a.id} href={a.url} target="_blank" rel="noreferrer" className="rounded border border-info/30 px-2 py-1 text-xs text-info">Dokumen</a>)}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {rejecting && (
                <form onSubmit={submitReject} className="mt-4 space-y-3 rounded-lg border border-danger/20 bg-danger/5 p-4">
                    <h3 className="text-sm font-medium text-text">Alasan penolakan BAST</h3>
                    <textarea rows="3" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} className="input" placeholder="Jelaskan apa yang perlu diperbaiki" />
                    {form.errors.notes && <span className="text-xs text-danger">{form.errors.notes}</span>}
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setRejecting(null)} className="btn btn-outline">Batal</button>
                        <button disabled={form.processing} className="rounded-lg bg-danger px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Tolak & Rework</button>
                    </div>
                </form>
            )}
            <ConfirmDialog
                open={approving !== null}
                onClose={() => setApproving(null)}
                onConfirm={() => approve(approving)}
                title="Verifikasi BAST?"
                description="BAST akan disetujui dan project akan ditandai selesai."
                tone="success"
                confirmLabel="Verifikasi BAST"
                processing={approveProcessing}
            />
        </section>
    );
}

function ChangeRequests({ project, types, editable }) {
    const form = useForm({ type: types[0]?.value ?? 'add', description: '' });

    function submit(e) {
        e.preventDefault();
        form.post(`/operational/projects/${project.id}/change-requests`, { preserveScroll: true, onSuccess: () => form.reset('description') });
    }
    function decide(id, status) {
        router.put(`/operational/projects/${project.id}/change-requests/${id}`, { status }, { preserveScroll: true });
    }

    return (
        <section className="card p-6">
            <h2 className="mb-1 font-semibold text-text">Change Request</h2>
            <p className="mb-4 text-xs text-text-muted">Pencatatan perubahan pesanan saat project berjalan. Tidak mengubah task/pengadaan — ditagih terpisah oleh Finance.</p>
            <div className="mb-4 space-y-2">
                {project.change_requests.map((c) => (
                    <div key={c.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3 text-sm">
                        <div>
                            <div className="font-medium text-text">{types.find((t) => t.value === c.type)?.label ?? c.type}</div>
                            <div className="text-xs text-text-muted">{c.description}</div>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className={`badge ${c.status === 'approved' ? 'badge-success' : c.status === 'rejected' ? 'badge-danger' : 'badge-warning'}`}>{c.status}</span>
                            {editable && c.status === 'pending' && (
                                <>
                                    <button onClick={() => decide(c.id, 'approved')} className="text-xs font-semibold text-success">Setujui</button>
                                    <button onClick={() => decide(c.id, 'rejected')} className="text-xs font-semibold text-danger">Tolak</button>
                                </>
                            )}
                        </div>
                    </div>
                ))}
                {project.change_requests.length === 0 && <p className="text-sm text-text-muted">Belum ada change request.</p>}
            </div>
            {editable && (
                <form onSubmit={submit} className="space-y-3 rounded-lg border border-border bg-surface-2 p-4">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className="input">
                            {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                        </select>
                    </div>
                    <textarea rows="2" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Deskripsi perubahan" className="input" />
                    {form.errors.description && <span className="text-xs text-danger">{form.errors.description}</span>}
                    <div className="flex justify-end"><button disabled={form.processing} className="btn btn-primary">Catat Change Request</button></div>
                </form>
            )}
        </section>
    );
}

function Planning({ project, canPlan }) {
    const form = useForm({ planned_start: project.planned_start ?? '', planned_end: project.planned_end ?? '' });
    return (
        <section className="card p-6">
            <h2 className="mb-4 font-semibold text-text">Perencanaan Jadwal</h2>
            {canPlan ? (
                <form onSubmit={(e) => { e.preventDefault(); form.put(`/operational/projects/${project.id}/planning`, { preserveScroll: true }); }} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium text-text">Mulai
                            <input type="date" value={form.data.planned_start ?? ''} onChange={(e) => form.setData('planned_start', e.target.value)} className="input" />
                        </label>
                        <label className="text-sm font-medium text-text">Selesai
                            <input type="date" value={form.data.planned_end ?? ''} onChange={(e) => form.setData('planned_end', e.target.value)} className="input" />
                            {form.errors.planned_end && <span className="text-xs text-danger">{form.errors.planned_end}</span>}
                        </label>
                    </div>
                    <div className="flex justify-end"><button disabled={form.processing} className="btn btn-primary">Simpan Planning</button></div>
                </form>
            ) : (
                <div className="grid gap-4 text-sm sm:grid-cols-2">
                    <div><span className="text-text-muted">Mulai:</span> {project.planned_start || '—'}</div>
                    <div><span className="text-text-muted">Selesai:</span> {project.planned_end || '—'}</div>
                </div>
            )}
        </section>
    );
}

function ActualProcurement({ project, availabilityOptions, progress, editable }) {
    const form = useForm({ item_name: '', qty: '1', unit: '', cost_price: '' });
    const [deleting, setDeleting] = useState(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    function add(e) {
        e.preventDefault();
        form.post(`/operational/projects/${project.id}/actual-procurements`, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    function removeItem() {
        setDeleteProcessing(true);
        router.delete(`/operational/projects/${project.id}/actual-procurements/${deleting}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onFinish: () => setDeleteProcessing(false),
        });
    }

    const done = progress.total > 0 && progress.received === progress.total;

    return (
        <section className="card overflow-hidden p-0">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-5">
                <div>
                    <h2 className="font-semibold text-text">Kebutuhan Barang (dikerjakan Procurement)</h2>
                    <p className="text-xs text-text-muted">Status pembelian diatur oleh tim Procurement. Operational hanya memantau.</p>
                </div>
                <span className={`badge ${done ? 'badge-success' : 'badge-warning'}`}>
                    Diterima {progress.received} / {progress.total}
                </span>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint"><tr><th className="px-4 py-3">Item</th><th className="px-4 py-3">Qty</th><th className="px-4 py-3 text-right">Cost</th><th className="px-4 py-3">Status</th>{editable && <th className="px-4 py-3 text-right">Aksi</th>}</tr></thead>
                    <tbody className="divide-y divide-border">
                        {project.actual_procurements.map((item) => (
                            <tr key={item.id}>
                                <td className="px-4 py-3"><div className="font-medium text-text">{item.item_name}</div><div className="text-xs text-text-muted">{item.vendor?.name || 'Belum ada vendor'}{item.requested_by ? ' · ekstra' : ''}</div></td>
                                <td className="px-4 py-3 text-text-muted">{item.qty} {item.unit}</td>
                                <td className="px-4 py-3 text-right text-text-muted">{money(item.cost_price)}</td>
                                <td className="px-4 py-3"><span className={`badge ${item.status === 'received' ? 'badge-success' : item.status === 'purchased' ? 'badge-primary' : 'badge-warning'}`}>{availabilityOptions.find((o) => o.value === item.status)?.label ?? item.status}</span></td>
                                {editable && <td className="px-4 py-3 text-right">{item.status === 'pending' && !item.procurement_payment_id && <button onClick={() => setDeleting(item.id)} className="text-danger">Hapus</button>}</td>}
                            </tr>
                        ))}
                        {project.actual_procurements.length === 0 && <tr><td colSpan={editable ? 5 : 4} className="px-4 py-6 text-center text-text-muted">Tidak ada kebutuhan barang (murni jasa).</td></tr>}
                    </tbody>
                </table>
            </div>
            {editable && (
                <form onSubmit={add} className="grid gap-3 border-t border-border p-4 md:grid-cols-5">
                    <input value={form.data.item_name} onChange={(e) => form.setData('item_name', e.target.value)} placeholder="Item ekstra tak terduga" className="rounded-lg border border-border px-2 py-2 text-sm md:col-span-2" />
                    <input type="number" min="0" step="0.01" value={form.data.qty} onChange={(e) => form.setData('qty', e.target.value)} placeholder="Qty" className="rounded-lg border border-border px-2 py-2 text-sm" />
                    <input value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} placeholder="Unit" className="rounded-lg border border-border px-2 py-2 text-sm" />
                    <CurrencyInput value={form.data.cost_price} onChange={(e) => form.setData('cost_price', e.target.value)} placeholder="Estimasi cost" className="rounded-lg border border-border px-2 py-2 text-sm" />
                    <button disabled={form.processing} className="btn btn-primary md:col-span-5">Tambah Item Ekstra</button>
                    {Object.keys(form.errors).length > 0 && <span className="text-xs text-danger md:col-span-5">{Object.values(form.errors)[0]}</span>}
                </form>
            )}
            <ConfirmDialog
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                onConfirm={removeItem}
                title="Hapus item pengadaan?"
                description="Item ekstra ini akan dihapus dari kebutuhan barang project."
                tone="danger"
                confirmLabel="Hapus Item"
                processing={deleteProcessing}
            />
        </section>
    );
}

function VendorAssignment({ project, canViewSow }) {
    const deal = project.vendor_service_payment;
    const dealNote = !deal ? null
        : deal.status === 'awaiting_dp' ? 'menunggu DP dibayar Finance sebelum project bisa dilanjutkan.'
            : deal.status === 'paid' ? 'sudah lunas.'
                : project.status === 'completed' ? 'BAST terverifikasi, pelunasan vendor menunggu dibayar Finance.'
                    : 'vendor berjalan, pelunasan dibayar Finance setelah BAST diverifikasi.';

    return (
        <section className="card p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="mb-1 font-semibold text-text">Vendor Teknisi Luar</h2>
                    <p className="text-sm text-text-muted">Vendor luar ditentukan Procurement (deal, fee, dan pembayaran ditangani Procurement & Finance). Setelah dilepas, SOW dibuat dari sini.</p>
                </div>
                {canViewSow && (
                    <Link href={`/operational/projects/${project.id}/sow`} className="whitespace-nowrap btn btn-outline">
                        Generate SOW
                    </Link>
                )}
            </div>
            {project.needs_outside_vendor && !deal && (
                <div className="mt-3 rounded-xl border border-warning/25 bg-warning-soft px-4 py-3 text-sm font-medium text-warning">
                    Project ini ditandai butuh vendor luar. Procurement sedang mencari vendor — tunggu sampai deal-nya dikonfirmasi sebelum merencanakan tim sendiri.
                </div>
            )}
            {deal && (
                <div className="mt-3 rounded-xl border border-border bg-surface-2 px-4 py-3 text-sm text-text-muted">
                    Deal vendor sudah diisi Procurement — {dealNote}
                </div>
            )}
            <p className="mt-4 text-sm text-text">{project.vendor?.name ?? 'Tidak pakai vendor luar.'}</p>
        </section>
    );
}

function TechnicianTeam({ project, options, editable }) {
    const current = project.technicians;
    const [selected, setSelected] = useState(current.map((t) => t.technician_id));
    const [leader, setLeader] = useState(current.find((t) => t.is_leader)?.technician_id ?? null);
    const form = useForm({});

    function toggle(id) {
        setSelected((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id]);
        if (leader === id && selected.includes(id)) setLeader(null);
    }
    function save() {
        form.transform(() => ({ technician_ids: selected, leader_id: leader }));
        form.put(`/operational/projects/${project.id}/technicians`, { preserveScroll: true });
    }

    return (
        <section className="card p-6">
            <h2 className="mb-4 font-semibold text-text">Tim Technician</h2>
            {!editable ? (
                <ul className="space-y-1 text-sm">
                    {current.map((t) => <li key={t.id} className="text-text">{t.technician.name}{t.is_leader ? ' · Leader' : ''}</li>)}
                    {current.length === 0 && <li className="text-text-muted">Belum ada tim.</li>}
                </ul>
            ) : (
                <div className="space-y-3">
                    <div className="space-y-2">
                        {options.map((tech) => (
                            <label key={tech.id} className="flex items-center gap-3 text-sm">
                                <input type="checkbox" checked={selected.includes(tech.id)} onChange={() => toggle(tech.id)} />
                                <span className="flex-1 text-text">{tech.name}</span>
                                {selected.includes(tech.id) && (
                                    <label className="flex items-center gap-1 text-xs text-text-muted">
                                        <input type="radio" name="leader" checked={leader === tech.id} onChange={() => setLeader(tech.id)} /> Leader
                                    </label>
                                )}
                            </label>
                        ))}
                    </div>
                    {Object.keys(form.errors).length > 0 && <p className="text-xs text-danger">{Object.values(form.errors)[0]}</p>}
                    <div className="flex justify-end"><button onClick={save} disabled={form.processing} className="btn btn-primary">Simpan Tim</button></div>
                </div>
            )}
        </section>
    );
}

function CheckIns({ items }) {
    return (
        <section className="card p-6">
            <h2 className="font-semibold text-text">Absensi Kehadiran</h2>
            {items.length === 0 ? (
                <p className="mt-2 text-sm text-text-muted">Belum ada teknisi yang absen.</p>
            ) : (
                <div className="mt-3 flex flex-wrap gap-4">
                    {items.map((c) => (
                        <a key={c.id} href={c.url} target="_blank" rel="noreferrer" className="flex items-center gap-3 rounded-lg border border-border p-2 text-sm">
                            <img src={c.url} alt={c.technician} className="h-12 w-12 rounded-lg object-cover" />
                            <div>
                                <div className="font-medium text-text">{c.technician}</div>
                                <div className="text-xs text-text-muted">{new Date(c.at).toLocaleString('id-ID')}</div>
                            </div>
                        </a>
                    ))}
                </div>
            )}
        </section>
    );
}

function Tasks({ project, photos = {}, editable }) {
    const [editingId, setEditingId] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);
    const form = useForm({ title: '', description: '', scheduled_date: '' });

    function beginEdit(t) {
        setEditingId(t.id);
        form.setData({ title: t.title, description: t.description ?? '', scheduled_date: t.scheduled_date ?? '' });
    }
    function cancel() { setEditingId(null); form.reset(); form.clearErrors(); }
    function submit(e) {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: cancel };
        editingId ? form.put(`/operational/projects/${project.id}/tasks/${editingId}`, opts) : form.post(`/operational/projects/${project.id}/tasks`, opts);
    }
    function removeTask() {
        setDeleteProcessing(true);
        router.delete(`/operational/projects/${project.id}/tasks/${deleting}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onFinish: () => setDeleteProcessing(false),
        });
    }

    return (
        <section className="card p-6">
            <h2 className="mb-4 font-semibold text-text">Task Project</h2>
            <div className="mb-4 space-y-2">
                {project.tasks.map((t) => (
                    <div key={t.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3 text-sm">
                        <div>
                            <div className="font-medium text-text">{t.title}</div>
                            <div className="text-xs text-text-muted">{t.scheduled_date || 'Tanpa jadwal'} · status: {t.status}</div>
                            {(photos[t.id] || []).length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-1">
                                    {photos[t.id].map((p) => <a key={p.id} href={p.url} target="_blank" rel="noreferrer" className="block h-12 w-12 overflow-hidden rounded border border-border"><img src={p.url} alt={p.category} className="h-full w-full object-cover" /></a>)}
                                </div>
                            )}
                        </div>
                        {editable && <div className="text-sm"><button onClick={() => beginEdit(t)} className="mr-3 text-info">Edit</button><button onClick={() => setDeleting(t.id)} className="text-danger">Hapus</button></div>}
                    </div>
                ))}
                {project.tasks.length === 0 && <p className="text-sm text-text-muted">Belum ada task.</p>}
            </div>
            {editable && (
                <form onSubmit={submit} className="space-y-3 rounded-lg border border-border bg-surface-2 p-4">
                    <h3 className="font-medium text-text">{editingId ? 'Edit Task' : 'Tambah Task'}</h3>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Judul task" className="input sm:col-span-2" />
                        <input type="date" value={form.data.scheduled_date ?? ''} onChange={(e) => form.setData('scheduled_date', e.target.value)} className="input" />
                    </div>
                    <textarea rows="2" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Deskripsi" className="input" />
                    {form.errors.title && <span className="text-xs text-danger">{form.errors.title}</span>}
                    <div className="flex justify-end gap-2">
                        {editingId && <button type="button" onClick={cancel} className="btn btn-outline">Batal</button>}
                        <button disabled={form.processing} className="btn btn-primary">{editingId ? 'Simpan' : 'Tambah'}</button>
                    </div>
                </form>
            )}
            <ConfirmDialog
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                onConfirm={removeTask}
                title="Hapus task?"
                description="Task ini akan dihapus dari project."
                tone="danger"
                confirmLabel="Hapus Task"
                processing={deleteProcessing}
            />
        </section>
    );
}
