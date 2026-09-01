import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';

const statusBadge = {
    draft: 'bg-warning/10 text-warning',
    planning: 'bg-info/10 text-info',
    waiting_resource: 'bg-warning/10 text-warning',
    ready: 'bg-info/10 text-info',
    in_progress: 'bg-info/10 text-info',
    verification: 'bg-warning/10 text-warning',
    completed: 'bg-success/10 text-success',
};

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Show({ project, bastRecords, taskPhotos, procurementProgress, statusOptions, availabilityOptions, technicianOptions, changeRequestTypes, permissions }) {
    const number = `PRJ-${String(project.id).padStart(6, '0')}`;
    const so = project.sales_order;

    return (
        <AppLayout>
            <Head title={number} />
            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link href="/operational/projects" className="text-sm text-info">← Kembali</Link>
                        <div className="mt-2 flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-text">{number}</h1>
                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusBadge[project.status]}`}>
                                {statusOptions.find((s) => s.value === project.status)?.label ?? project.status}
                            </span>
                        </div>
                        <p className="text-sm text-text-muted">{so.number} · {so.contact.name}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.markReady && (
                            <button onClick={() => router.post(`/operational/projects/${project.id}/ready`)} className="rounded-lg bg-success px-4 py-2 text-sm font-semibold text-white">Tandai Siap</button>
                        )}
                        {permissions.start && (
                            <button onClick={() => router.post(`/operational/projects/${project.id}/start`)} className="rounded-lg bg-info px-4 py-2 text-sm font-semibold text-white">Mulai Project</button>
                        )}
                        {permissions.completeDirect && (
                            <button onClick={() => { if (confirm('Selesaikan project ini? (Material Only, tanpa BAST)')) router.post(`/operational/projects/${project.id}/complete`); }} className="rounded-lg bg-success px-4 py-2 text-sm font-semibold text-white">Selesaikan Project</button>
                        )}
                    </div>
                </div>

                <Planning project={project} canPlan={permissions.plan} />
                <ActualProcurement project={project} availabilityOptions={availabilityOptions} progress={procurementProgress} editable={permissions.manageResources} />
                <TechnicianTeam project={project} options={technicianOptions} editable={permissions.manageResources} />
                <Tasks project={project} photos={taskPhotos} editable={permissions.manageTasks} />
                <BastSection project={project} records={bastRecords} canVerify={permissions.verifyBast} />
                <ChangeRequests project={project} types={changeRequestTypes} editable={permissions.manageChangeRequests} />
            </div>
        </AppLayout>
    );
}

function BastSection({ project, records, canVerify }) {
    const [rejecting, setRejecting] = useState(null);
    const form = useForm({ decision: 'reject', notes: '' });

    function approve(bastId) {
        if (confirm('Verifikasi BAST ini? Project akan ditandai selesai.')) {
            router.put(`/operational/projects/${project.id}/bast/${bastId}`, { decision: 'approve' }, { preserveScroll: true });
        }
    }
    function submitReject(e) {
        e.preventDefault();
        form.transform(() => ({ decision: 'reject', notes: form.data.notes }));
        form.put(`/operational/projects/${project.id}/bast/${rejecting}`, { preserveScroll: true, onSuccess: () => { setRejecting(null); form.reset(); } });
    }

    return (
        <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
            <h2 className="mb-4 font-semibold text-text">BAST (Berita Acara Serah Terima)</h2>
            {records.length === 0 ? (
                <p className="text-sm text-text-muted">Belum ada BAST dari technician.</p>
            ) : (
                <div className="space-y-3">
                    {records.map((b) => (
                        <div key={b.id} className="rounded-lg border border-border p-3 text-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${b.status === 'verified' ? 'bg-success/10 text-success' : b.status === 'rejected' ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-warning'}`}>{b.status}</span>
                                    <span className="ml-2 text-text-muted">oleh {b.submitter?.name} · {b.submitted_at?.slice(0, 16).replace('T', ' ')}</span>
                                </div>
                                {canVerify && b.status === 'submitted' && (
                                    <div className="flex gap-2">
                                        <button onClick={() => approve(b.id)} className="rounded-lg bg-success px-3 py-1.5 text-xs font-semibold text-white">Verifikasi</button>
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
                        <button type="button" onClick={() => setRejecting(null)} className="rounded-lg border border-border px-4 py-2 text-sm">Batal</button>
                        <button disabled={form.processing} className="rounded-lg bg-danger px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Tolak & Rework</button>
                    </div>
                </form>
            )}
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
        <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
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
                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${c.status === 'approved' ? 'bg-success/10 text-success' : c.status === 'rejected' ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-warning'}`}>{c.status}</span>
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
                <form onSubmit={submit} className="space-y-3 rounded-lg border border-border bg-bg/50 p-4">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className="input">
                            {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                        </select>
                    </div>
                    <textarea rows="2" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Deskripsi perubahan" className="input" />
                    {form.errors.description && <span className="text-xs text-danger">{form.errors.description}</span>}
                    <div className="flex justify-end"><button disabled={form.processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Catat Change Request</button></div>
                </form>
            )}
        </section>
    );
}

function Planning({ project, canPlan }) {
    const form = useForm({ planned_start: project.planned_start ?? '', planned_end: project.planned_end ?? '' });
    return (
        <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
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
                    <div className="flex justify-end"><button disabled={form.processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Simpan Planning</button></div>
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

    function add(e) {
        e.preventDefault();
        form.post(`/operational/projects/${project.id}/actual-procurements`, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    const done = progress.total > 0 && progress.received === progress.total;

    return (
        <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-5">
                <div>
                    <h2 className="font-semibold text-text">Kebutuhan Barang (dikerjakan Procurement)</h2>
                    <p className="text-xs text-text-muted">Status pembelian diatur oleh tim Procurement. Operational hanya memantau.</p>
                </div>
                <span className={`rounded-full px-3 py-1 text-xs font-semibold ${done ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'}`}>
                    Diterima {progress.received} / {progress.total}
                </span>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="bg-bg text-text-muted"><tr><th className="px-4 py-3">Item</th><th className="px-4 py-3">Qty</th><th className="px-4 py-3 text-right">Cost</th><th className="px-4 py-3">Status</th>{editable && <th className="px-4 py-3 text-right">Aksi</th>}</tr></thead>
                    <tbody className="divide-y divide-border">
                        {project.actual_procurements.map((item) => (
                            <tr key={item.id}>
                                <td className="px-4 py-3"><div className="font-medium text-text">{item.item_name}</div><div className="text-xs text-text-muted">{item.vendor?.name || 'Belum ada vendor'}{item.requested_by ? ' · ekstra' : ''}</div></td>
                                <td className="px-4 py-3 text-text-muted">{item.qty} {item.unit}</td>
                                <td className="px-4 py-3 text-right text-text-muted">{money(item.cost_price)}</td>
                                <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${item.status === 'received' ? 'bg-success/10 text-success' : item.status === 'purchased' ? 'bg-info/10 text-info' : 'bg-warning/10 text-warning'}`}>{availabilityOptions.find((o) => o.value === item.status)?.label ?? item.status}</span></td>
                                {editable && <td className="px-4 py-3 text-right">{item.status === 'pending' && <button onClick={() => { if (confirm('Hapus item?')) router.delete(`/operational/projects/${project.id}/actual-procurements/${item.id}`, { preserveScroll: true }); }} className="text-danger">Hapus</button>}</td>}
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
                    <input type="number" min="0" step="0.01" value={form.data.cost_price} onChange={(e) => form.setData('cost_price', e.target.value)} placeholder="Estimasi cost" className="rounded-lg border border-border px-2 py-2 text-sm" />
                    <button disabled={form.processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white md:col-span-5">Tambah Item Ekstra</button>
                    {Object.keys(form.errors).length > 0 && <span className="text-xs text-danger md:col-span-5">{Object.values(form.errors)[0]}</span>}
                </form>
            )}
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
        <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
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
                    <div className="flex justify-end"><button onClick={save} disabled={form.processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Simpan Tim</button></div>
                </div>
            )}
        </section>
    );
}

function Tasks({ project, photos = {}, editable }) {
    const [editingId, setEditingId] = useState(null);
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

    return (
        <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
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
                        {editable && <div className="text-sm"><button onClick={() => beginEdit(t)} className="mr-3 text-info">Edit</button><button onClick={() => { if (confirm('Hapus task?')) router.delete(`/operational/projects/${project.id}/tasks/${t.id}`, { preserveScroll: true }); }} className="text-danger">Hapus</button></div>}
                    </div>
                ))}
                {project.tasks.length === 0 && <p className="text-sm text-text-muted">Belum ada task.</p>}
            </div>
            {editable && (
                <form onSubmit={submit} className="space-y-3 rounded-lg border border-border bg-bg/50 p-4">
                    <h3 className="font-medium text-text">{editingId ? 'Edit Task' : 'Tambah Task'}</h3>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Judul task" className="input sm:col-span-2" />
                        <input type="date" value={form.data.scheduled_date ?? ''} onChange={(e) => form.setData('scheduled_date', e.target.value)} className="input" />
                    </div>
                    <textarea rows="2" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Deskripsi" className="input" />
                    {form.errors.title && <span className="text-xs text-danger">{form.errors.title}</span>}
                    <div className="flex justify-end gap-2">
                        {editingId && <button type="button" onClick={cancel} className="rounded-lg border border-border px-4 py-2 text-sm">Batal</button>}
                        <button disabled={form.processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{editingId ? 'Simpan' : 'Tambah'}</button>
                    </div>
                </form>
            )}
        </section>
    );
}
