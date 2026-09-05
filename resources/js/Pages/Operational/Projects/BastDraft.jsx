import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function BastDraft({ project, draft, hasDraft }) {
    const form = useForm({
        number: draft.number ?? '',
        event_date: draft.event_date ?? '',
        job_title: draft.job_title ?? '',
        work_description: draft.work_description ?? '',
        pic_name: draft.pic_name ?? '',
        pic_position: draft.pic_position ?? '',
        pic_address: draft.pic_address ?? '',
        leader_name: draft.leader_name ?? '',
        leader_position: draft.leader_position ?? '',
    });

    function submit(e) {
        e.preventDefault();
        form.put(`/operational/projects/${project.id}/bast-draft`, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={`Generate BAST — ${project.number}`} />
            <div className="mx-auto max-w-3xl space-y-5">
                <div>
                    <Link href={`/operational/projects/${project.id}`} className="text-sm text-info">← Kembali ke Project</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">Generate BAST — {project.number}</h1>
                    <p className="text-sm text-text-muted">{project.company || project.customer}{project.po_number ? ` · PO ${project.po_number}` : ''}</p>
                </div>

                <form onSubmit={submit} className="space-y-5 rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label="Nomor BAST" value={form.data.number} onChange={(v) => form.setData('number', v)} error={form.errors.number} placeholder="Diisi manual" />
                        <Field label="Tanggal Pelaksanaan" type="date" value={form.data.event_date} onChange={(v) => form.setData('event_date', v)} error={form.errors.event_date} />
                    </div>

                    <Field label="Pekerjaan" value={form.data.job_title} onChange={(v) => form.setData('job_title', v)} error={form.errors.job_title} placeholder="Mis. Instalasi PLTS On-Grid 10 kWp" />

                    <label className="block text-sm font-medium text-text">
                        Deskripsi Pelaksanaan Pekerjaan
                        <textarea rows="4" value={form.data.work_description} onChange={(e) => form.setData('work_description', e.target.value)} placeholder="Uraikan pekerjaan yang telah dilaksanakan..." className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />
                        {form.errors.work_description && <span className="mt-1 block text-sm text-danger">{form.errors.work_description}</span>}
                    </label>

                    <div>
                        <h2 className="mb-2 text-sm font-semibold text-text">Pihak Kesatu (Customer)</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Nama PIC" value={form.data.pic_name} onChange={(v) => form.setData('pic_name', v)} error={form.errors.pic_name} />
                            <Field label="Jabatan PIC" value={form.data.pic_position} onChange={(v) => form.setData('pic_position', v)} error={form.errors.pic_position} />
                            <div className="sm:col-span-2"><Field label="Alamat" value={form.data.pic_address} onChange={(v) => form.setData('pic_address', v)} error={form.errors.pic_address} /></div>
                        </div>
                        <p className="mt-1 text-xs text-text-muted">Terisi otomatis dari data PIC di Lead — silakan sesuaikan dengan kondisi di lapangan.</p>
                    </div>

                    <div>
                        <h2 className="mb-2 text-sm font-semibold text-text">Pihak Kedua (Teknisi)</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Nama Leader" value={form.data.leader_name} onChange={(v) => form.setData('leader_name', v)} error={form.errors.leader_name} />
                            <Field label="Jabatan" value={form.data.leader_position} onChange={(v) => form.setData('leader_position', v)} error={form.errors.leader_position} placeholder="Teknisi" />
                        </div>
                        <p className="mt-1 text-xs text-text-muted">Terisi otomatis dari leader tim teknisi project ini — silakan sesuaikan bila perlu.</p>
                    </div>

                    <div className="flex flex-wrap justify-end gap-2">
                        {hasDraft && (
                            <a href={`/operational/projects/${project.id}/bast-draft/print`} target="_blank" rel="noreferrer" className="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-text">
                                Lihat / Cetak PDF
                            </a>
                        )}
                        <button disabled={form.processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </button>
                    </div>
                    {!hasDraft && <p className="text-right text-xs text-text-muted">Simpan dulu untuk bisa melihat/cetak PDF-nya.</p>}
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, value, onChange, error, type = 'text', placeholder }) {
    return (
        <label className="block text-sm font-medium text-text">
            {label}
            <input type={type} value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </label>
    );
}
