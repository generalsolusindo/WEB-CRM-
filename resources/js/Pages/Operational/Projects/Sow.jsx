import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader } from '../../../Components/ui';

export default function Sow({ project, vendor, technicianOptions = [], sow, signatures, canEdit, canSignOperational, canRestartSignatures }) {
    const [signing, setSigning] = useState(false);

    function signOperational() {
        setSigning(true);
        router.post(`/operational/sows/${sow.id}/sign-operational`, {}, { onFinish: () => setSigning(false) });
    }

    function restartSignatures() {
        if (confirm('Ulangi proses tanda tangan Teknisi & PIC Vendor?')) {
            router.post(`/operational/sows/${sow.id}/restart-signatures`);
        }
    }
    const fileInput = useRef(null);
    const form = useForm({
        number: sow.number ?? '',
        project_name: sow.project_name ?? '',
        site_location: sow.site_location ?? '',
        client_name: sow.client_name ?? '',
        execution_date: sow.execution_date ?? '',
        background: sow.background ?? '',
        responsibilities: sow.responsibilities ?? '',
        schedule_duration: sow.schedule_duration ?? '',
        schedule_start_date: sow.schedule_start_date ?? '',
        schedule_end_date: sow.schedule_end_date ?? '',
        safety: sow.safety ?? '',
        payment_terms: sow.payment_terms ?? '',
        output: sow.output ?? '',
        warranty: sow.warranty ?? '',
        notes: sow.notes ?? '',
        closing: sow.closing ?? '',
        technician_id: sow.technician_id ?? '',
        technician_team_note: sow.technician_team_note ?? '',
        client_pic_name: sow.client_pic_name ?? '',
        client_pic_phone: sow.client_pic_phone ?? '',
    });
    const imageForm = useForm({ images: [] });

    useEffect(() => {
        if (sow.number && !form.data.number) {
            form.setData('number', sow.number);
        }
    }, [sow.number]);

    function submit(e) {
        e.preventDefault();
        form.put(`/operational/projects/${project.id}/sow`, { preserveScroll: true });
    }

    function uploadImages(e) {
        const files = Array.from(e.target.files || []);
        if (files.length === 0) return;
        imageForm.transform(() => ({ images: files }));
        imageForm.post(`/operational/projects/${project.id}/sow/images`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => { if (fileInput.current) fileInput.current.value = ''; },
        });
    }

    function deleteImage(imageId) {
        if (confirm('Hapus gambar ini?')) router.delete(`/operational/projects/${project.id}/sow/images/${imageId}`, { preserveScroll: true });
    }

    function submitToHr() {
        if (confirm('Kirim SOW ini ke HR untuk direview?')) router.post(`/operational/projects/${project.id}/sow/submit`);
    }

    const isDraftLike = !sow.status || sow.status === 'draft' || sow.status === 'rejected_by_hr';

    return (
        <AppLayout>
            <Head title={`Generate SOW — ${project.number}`} />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title={<span className="flex flex-wrap items-center gap-3">Generate SOW — {project.number} {sow.status && <span className="badge badge-primary">{sow.status_label}</span>}</span>}
                    subtitle={`${project.company || project.customer}${vendor ? ` · Vendor: ${vendor.name}` : ''}`}
                    back={{ href: `/operational/projects/${project.id}`, label: 'Kembali ke Project' }}
                />

                {sow.status === 'rejected_by_hr' && (
                    <div className="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">
                        SOW ini dikembalikan oleh HR{sow.hr_content_reviewed_by ? ` (${sow.hr_content_reviewed_by})` : ''} — silakan perbaiki lalu kirim ulang.
                        {sow.hr_content_review_notes && <div className="mt-1 font-medium">Catatan: {sow.hr_content_review_notes}</div>}
                    </div>
                )}

                {canRestartSignatures && (
                    <section className="rounded-xl border-2 border-danger/40 bg-danger/5 p-6 shadow-sm">
                        <h2 className="mb-1 font-semibold text-danger">Tanda Tangan Ditolak HR</h2>
                        <p className="mb-3 text-sm text-danger">HR menolak tanda tangan Teknisi/PIC Vendor. Ulangi proses tanda tangan dari awal.</p>
                        <button onClick={restartSignatures} className="btn btn-primary">Ulangi Proses Tanda Tangan</button>
                    </section>
                )}

                {canSignOperational && (
                    <section className="rounded-xl border-2 border-navy/40 bg-navy/5 p-6 shadow-sm">
                        <h2 className="mb-1 font-semibold text-text">Perlu Tanda Tangan Anda — Operasional</h2>
                        <p className="mb-3 text-sm text-text-muted">Teknisi & PIC Vendor sudah tanda tangan dan diverifikasi HR. Tanda tangan diambil otomatis dari tanda tangan resmi Administrator, lalu diteruskan ke Project Manager.</p>
                        <div className="flex justify-end">
                            <button onClick={signOperational} disabled={signing} className="btn btn-primary">
                                {signing ? 'Menyimpan…' : 'Tanda Tangani Otomatis & Kirim ke Project Manager'}
                            </button>
                        </div>
                    </section>
                )}

                {signatures && (signatures.technician || signatures.vendor || signatures.admin || signatures.director) && (
                    <Section title="Tanda Tangan">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SignaturePreview label="Teknisi" image={signatures.technician} />
                            <SignaturePreview label="PIC Vendor" image={signatures.vendor} />
                            <SignaturePreview label="Operasional" image={signatures.admin} />
                            <SignaturePreview label="Project Manager" image={signatures.director} />
                        </div>
                    </Section>
                )}

                <form onSubmit={submit} className="space-y-6">
                    <Section title="1. Informasi Umum">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Nomor SOW *" value={form.data.number} onChange={(v) => form.setData('number', v)} error={form.errors.number} placeholder="Terisi otomatis setelah disimpan" />
                            <Field label="Nama Proyek *" value={form.data.project_name} onChange={(v) => form.setData('project_name', v)} error={form.errors.project_name} />
                            <Field label="Lokasi" value={form.data.site_location} onChange={(v) => form.setData('site_location', v)} error={form.errors.site_location} />
                            <Field label="Client" value={form.data.client_name} onChange={(v) => form.setData('client_name', v)} error={form.errors.client_name} />
                            <Field label="Tanggal Pelaksanaan" value={form.data.execution_date} onChange={(v) => form.setData('execution_date', v)} error={form.errors.execution_date} placeholder="Mis. 23 Agustus 2026 s.d. Selesai" />
                            <div className="block text-sm font-medium text-text">Vendor/Implementor<div className="mt-1 rounded-lg border border-border bg-bg px-3 py-2 text-text-muted">CV. General Solusindo</div></div>
                        </div>
                    </Section>

                    <Section title="2. Latar Belakang">
                        <TextArea value={form.data.background} onChange={(v) => form.setData('background', v)} error={form.errors.background} placeholder="Narasi latar belakang pekerjaan..." />
                        <div className="mt-3">
                            {canEdit && (
                                <label className="block text-sm font-medium text-text">Gambar/Diagram Pendukung
                                    <input ref={fileInput} type="file" accept="image/*" multiple onChange={uploadImages} className="mt-1 block text-sm" disabled={!sow.id} />
                                </label>
                            )}
                            {canEdit && !sow.id && <p className="mt-1 text-xs text-warning">Simpan draft dulu sebelum upload gambar.</p>}
                            {sow.images?.length > 0 && (
                                <div className="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4">
                                    {sow.images.map((img) => (
                                        <div key={img.id} className="group relative">
                                            <a href={img.url} target="_blank" rel="noreferrer"><img src={img.url} className="h-24 w-full rounded-lg border border-border object-cover" /></a>
                                            {canEdit && (
                                                <button type="button" onClick={() => deleteImage(img.id)} className="absolute right-1 top-1 rounded-full bg-danger px-2 py-0.5 text-xs text-white opacity-0 group-hover:opacity-100">✕</button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </Section>

                    <Section title="3. Ruang Lingkup Pekerjaan">
                        {sow.id ? (
                            <ScopeSections project={project} sections={sow.scope_sections || []} canEdit={canEdit} />
                        ) : (
                            <p className="text-sm text-warning">Simpan draft dulu (Informasi Umum + Latar Belakang) sebelum menambah sub-bab ruang lingkup.</p>
                        )}
                    </Section>

                    <Section title="4. Tanggung Jawab">
                        <TextArea value={form.data.responsibilities} onChange={(v) => form.setData('responsibilities', v)} error={form.errors.responsibilities} placeholder="Tanggung jawab Vendor vs Client..." />
                    </Section>

                    <Section title="5. Waktu Pelaksanaan & Jadwal">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Estimasi Durasi Pekerjaan" value={form.data.schedule_duration} onChange={(v) => form.setData('schedule_duration', v)} error={form.errors.schedule_duration} placeholder="Mis. 3 – 5 hari" />
                            <label className="block text-sm font-medium text-text">Waktu Mulai
                                <input type="date" value={form.data.schedule_start_date} onChange={(e) => form.setData('schedule_start_date', e.target.value)} className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />
                                {form.errors.schedule_start_date && <span className="mt-1 block text-sm text-danger">{form.errors.schedule_start_date}</span>}
                            </label>
                            <label className="block text-sm font-medium text-text">Target Selesai
                                <input type="date" value={form.data.schedule_end_date} onChange={(e) => form.setData('schedule_end_date', e.target.value)} className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />
                                {form.errors.schedule_end_date && <span className="mt-1 block text-sm text-danger">{form.errors.schedule_end_date}</span>}
                            </label>
                        </div>
                    </Section>

                    <Section title="6. Keselamatan Kerja (K3)">
                        <TextArea value={form.data.safety} onChange={(v) => form.setData('safety', v)} error={form.errors.safety} />
                    </Section>

                    <Section title="7. Pembayaran">
                        <TextArea value={form.data.payment_terms} onChange={(v) => form.setData('payment_terms', v)} error={form.errors.payment_terms} />
                    </Section>

                    <Section title="8. Output Pekerjaan">
                        <TextArea value={form.data.output} onChange={(v) => form.setData('output', v)} error={form.errors.output} />
                    </Section>

                    <Section title="9. Garansi Layanan Teknisi">
                        <TextArea value={form.data.warranty} onChange={(v) => form.setData('warranty', v)} error={form.errors.warranty} />
                    </Section>

                    <Section title="10. Catatan">
                        <TextArea value={form.data.notes} onChange={(v) => form.setData('notes', v)} error={form.errors.notes} />
                    </Section>

                    <Section title="11. PIC & Kontak">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="block text-sm font-medium text-text">PIC Vendor
                                <div className="mt-1 rounded-lg border border-border bg-bg px-3 py-2 text-text-muted">
                                    {vendor ? `${vendor.contact_person || '—'} (${vendor.phone || '—'})` : 'Project belum ditandai pakai vendor.'}
                                </div>
                            </div>
                            <label className="block text-sm font-medium text-text">Teknisi Pelaksana (Team Teknisi Site) *
                                <select value={form.data.technician_id} onChange={(e) => form.setData('technician_id', e.target.value)} className="input">
                                    <option value="">Pilih teknisi</option>
                                    {technicianOptions.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                </select>
                                {technicianOptions.length === 0 && <span className="mt-1 block text-xs text-warning">Vendor ini belum punya akun teknisi.</span>}
                                {form.errors.technician_id && <span className="mt-1 block text-xs text-danger">{form.errors.technician_id}</span>}
                            </label>
                            <div className="sm:col-span-2">
                                <Field label="Anggota Tim Lainnya (opsional)" value={form.data.technician_team_note} onChange={(v) => form.setData('technician_team_note', v)} error={form.errors.technician_team_note} placeholder="Nama anggota tim tambahan yang tidak perlu tanda tangan" />
                            </div>
                            <Field label="PIC Client" value={form.data.client_pic_name} onChange={(v) => form.setData('client_pic_name', v)} error={form.errors.client_pic_name} />
                            <Field label="Telepon PIC Client" value={form.data.client_pic_phone} onChange={(v) => form.setData('client_pic_phone', v)} error={form.errors.client_pic_phone} />
                        </div>
                    </Section>

                    <Section title="12. Penutup">
                        <TextArea value={form.data.closing} onChange={(v) => form.setData('closing', v)} error={form.errors.closing} />
                    </Section>

                    {canEdit && isDraftLike && (
                        <div className="flex flex-wrap justify-end gap-2">
                            {sow.id && (
                                <a href={`/operational/projects/${project.id}/sow/print`} target="_blank" rel="noreferrer" className="btn btn-outline">
                                    Lihat / Cetak PDF
                                </a>
                            )}
                            <button disabled={form.processing} className="btn btn-outline">
                                {form.processing ? 'Menyimpan…' : 'Simpan Draft'}
                            </button>
                            {sow.id && (
                                <button type="button" onClick={submitToHr} className="btn btn-primary">
                                    Kirim ke HR
                                </button>
                            )}
                        </div>
                    )}
                    {!isDraftLike && sow.id && (
                        <div className="flex justify-end">
                            <a href={`/operational/projects/${project.id}/sow/print`} target="_blank" rel="noreferrer" className="btn btn-outline">
                                Lihat / Cetak PDF
                            </a>
                        </div>
                    )}
                </form>
            </div>
        </AppLayout>
    );
}

function Section({ title, children }) {
    return (
        <section className="card p-6">
            <h2 className="mb-3 font-semibold text-text">{title}</h2>
            {children}
        </section>
    );
}

function Field({ label, value, onChange, error, placeholder }) {
    return (
        <label className="block text-sm font-medium text-text">
            {label}
            <input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </label>
    );
}

function TextArea({ value, onChange, error, placeholder }) {
    return (
        <div>
            <textarea rows="4" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </div>
    );
}

function ScopeSections({ project, sections, canEdit }) {
    const [adding, setAdding] = useState(false);

    function addSection() {
        setAdding(true);
        router.post(`/operational/projects/${project.id}/sow/scope-sections`, {
            title: 'Sub Bab Baru',
            content: '',
        }, { preserveScroll: true, onFinish: () => setAdding(false) });
    }

    return (
        <div className="space-y-4">
            {sections.length === 0 && <p className="text-sm text-text-muted">Belum ada sub-bab.</p>}
            {sections.map((s, i) => (
                <ScopeSectionCard key={s.id} project={project} section={s} letter={String.fromCharCode(65 + i)} isFirst={i === 0} isLast={i === sections.length - 1} canEdit={canEdit} />
            ))}
            {canEdit && (
                <button type="button" onClick={addSection} disabled={adding} className="btn btn-outline">
                    {adding ? 'Menambah…' : '+ Tambah Sub Bab'}
                </button>
            )}
        </div>
    );
}

function ScopeSectionCard({ project, section, letter, isFirst, isLast, canEdit }) {
    const [title, setTitle] = useState(section.title);
    const [content, setContent] = useState(section.content || '');
    const [saving, setSaving] = useState(false);
    const fileRef = useRef(null);

    function save() {
        setSaving(true);
        router.put(`/operational/projects/${project.id}/sow/scope-sections/${section.id}`, { title, content }, {
            preserveScroll: true, onFinish: () => setSaving(false),
        });
    }

    function remove() {
        if (confirm(`Hapus sub-bab "${section.title}"?`)) {
            router.delete(`/operational/projects/${project.id}/sow/scope-sections/${section.id}`, { preserveScroll: true });
        }
    }

    function move(direction) {
        router.post(`/operational/projects/${project.id}/sow/scope-sections/${section.id}/move`, { direction }, { preserveScroll: true });
    }

    function uploadImages(e) {
        const files = Array.from(e.target.files || []);
        if (files.length === 0) return;
        router.post(`/operational/projects/${project.id}/sow/scope-sections/${section.id}/images`, { images: files }, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => { if (fileRef.current) fileRef.current.value = ''; },
        });
    }

    function deleteImage(imageId) {
        if (confirm('Hapus gambar ini?')) {
            router.delete(`/operational/projects/${project.id}/sow/scope-sections/${section.id}/images/${imageId}`, { preserveScroll: true });
        }
    }

    if (!canEdit) {
        return (
            <div className="rounded-xl border border-border p-4">
                <h3 className="text-sm font-semibold text-text">{letter}. {section.title}</h3>
                <p className="mt-2 whitespace-pre-line text-sm text-text">{section.content || '—'}</p>
                {section.images?.length > 0 && (
                    <div className="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4">
                        {section.images.map((img) => (
                            <a key={img.id} href={img.url} target="_blank" rel="noreferrer"><img src={img.url} className="h-24 w-full rounded-lg border border-border object-cover" /></a>
                        ))}
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className="rounded-xl border border-border p-4">
            <div className="flex items-start justify-between gap-2">
                <label className="block flex-1 text-sm font-medium text-text">
                    {letter}. Judul Sub Bab
                    <input value={title} onChange={(e) => setTitle(e.target.value)} className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />
                </label>
                <div className="flex shrink-0 gap-1 pt-6">
                    <button type="button" onClick={() => move('up')} disabled={isFirst} className="rounded-lg border border-border px-2 py-1.5 text-xs font-semibold text-text-muted disabled:opacity-30" title="Pindah naik">↑</button>
                    <button type="button" onClick={() => move('down')} disabled={isLast} className="rounded-lg border border-border px-2 py-1.5 text-xs font-semibold text-text-muted disabled:opacity-30" title="Pindah turun">↓</button>
                    <button type="button" onClick={remove} className="rounded-lg border border-danger/30 px-2 py-1.5 text-xs font-semibold text-danger" title="Hapus sub-bab">Hapus</button>
                </div>
            </div>
            <div className="mt-3">
                <TextArea value={content} onChange={setContent} placeholder="Isi sub-bab ini..." />
            </div>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                <label className="text-sm font-medium text-text">
                    Gambar pendukung
                    <input ref={fileRef} type="file" accept="image/*" multiple onChange={uploadImages} className="mt-1 block text-sm" />
                </label>
                <button type="button" onClick={save} disabled={saving} className="btn btn-primary">
                    {saving ? 'Menyimpan…' : 'Simpan Sub Bab'}
                </button>
            </div>
            {section.images?.length > 0 && (
                <div className="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4">
                    {section.images.map((img) => (
                        <div key={img.id} className="group relative">
                            <a href={img.url} target="_blank" rel="noreferrer"><img src={img.url} className="h-24 w-full rounded-lg border border-border object-cover" /></a>
                            <button type="button" onClick={() => deleteImage(img.id)} className="absolute right-1 top-1 rounded-full bg-danger px-2 py-0.5 text-xs text-white opacity-0 group-hover:opacity-100">✕</button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function SignaturePreview({ label, image }) {
    return (
        <div>
            <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div>
            {image ? <img src={image} className="mt-1 h-16 border-b border-border object-contain" /> : <div className="mt-1 text-sm text-text-muted">Belum tanda tangan</div>}
        </div>
    );
}
