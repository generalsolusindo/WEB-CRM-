import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, ConfirmDialog } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';

export default function Sow({ project, vendor, technicianOptions = [], sow, signatures, canEdit, canSignOperational, canRestartSignatures }) {
    const [signing, setSigning] = useState(false);
    const [confirmation, setConfirmation] = useState(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    function signOperational() {
        feedback.act({
            url: `/operational/sows/${sow.id}/sign-operational`,
            confirm: {
                tone: 'question',
                title: 'Tanda tangani SOW ini?',
                text: `Tanda tangan Operasional akan dibubuhkan pada SOW ${sow.number} lalu diteruskan ke Project Manager.`,
                confirmLabel: 'Ya, tanda tangani',
            },
            success: { title: 'SOW ditandatangani', style: 'popup' },
            visit: { onStart: () => setSigning(true), onFinish: () => setSigning(false), preserveScroll: false },
        });
    }

    function restartSignatures() {
        setActionProcessing(true);
        feedback.expect({ success: { title: 'Tanda tangan diulang', style: 'popup' } });
        router.post(`/operational/sows/${sow.id}/restart-signatures`, {}, {
            onSuccess: () => setConfirmation(null),
            onFinish: () => setActionProcessing(false),
        });
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
        section_visibility: sow.section_visibility ?? {},
        custom_sections: sow.custom_sections ?? [],
        scope_sections: (sow.scope_sections ?? []).map((section) => ({
            id: section.id,
            title: section.title,
            content: section.content ?? '',
            images: section.images ?? [],
        })),
    });
    const imageForm = useForm({ images: [] });

    useEffect(() => {
        if (sow.number && !form.data.number) {
            form.setData('number', sow.number);
        }
    }, [sow.number]);

    function submit(e) {
        e.preventDefault();
        if (!isDraftLike) {
            setConfirmation({ type: 'reset-process' });
            return;
        }
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
        setConfirmation({ type: 'delete-main-image', id: imageId });
    }

    function submitToHr() {
        setConfirmation({ type: 'submit-hr' });
    }

    function runConfirmedAction() {
        if (confirmation?.type === 'reset-process') {
            feedback.expect({ success: { title: 'SOW direset ke Draft', style: 'popup' } });
            form.put(`/operational/projects/${project.id}/sow`, {
                preserveScroll: true,
                onSuccess: () => setConfirmation(null),
            });
            return;
        }
        if (confirmation?.type === 'restart-signatures') {
            restartSignatures();
            return;
        }

        setActionProcessing(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => setConfirmation(null),
            onFinish: () => setActionProcessing(false),
        };
        if (confirmation?.type === 'delete-main-image') {
            router.delete(`/operational/projects/${project.id}/sow/images/${confirmation.id}`, options);
        } else if (confirmation?.type === 'submit-hr') {
            feedback.expect({ success: { title: 'SOW dikirim ke HR', style: 'popup' } });
            router.post(`/operational/projects/${project.id}/sow/submit`, {}, options);
        }
    }

    const confirmationContent = {
        'restart-signatures': {
            title: 'Ulangi proses tanda tangan?',
            description: 'Tanda tangan Teknisi dan PIC Vendor akan dimulai ulang dari awal.',
            tone: 'warning',
            confirmLabel: 'Ulangi Proses',
        },
        'reset-process': {
            title: 'Simpan dan reset proses SOW?',
            description: 'Seluruh review dan tanda tangan yang sudah dikumpulkan akan direset. Status SOW kembali ke Draft dan harus dikirim ulang dari awal.',
            tone: 'danger',
            confirmLabel: 'Simpan & Reset',
        },
        'delete-main-image': {
            title: 'Hapus gambar?',
            description: 'Gambar pendukung ini akan dihapus dari SOW.',
            tone: 'danger',
            confirmLabel: 'Hapus Gambar',
        },
        'submit-hr': {
            title: 'Kirim SOW ke HR?',
            description: 'SOW akan dikirim ke HR untuk ditinjau sebelum proses tanda tangan dilanjutkan.',
            tone: 'info',
            confirmLabel: 'Kirim ke HR',
        },
    }[confirmation?.type];

    const isDraftLike = !sow.status || sow.status === 'draft' || sow.status === 'rejected_by_hr';

    function isSectionActive(key) {
        return form.data.section_visibility[key] !== false;
    }

    function setSectionActive(key, active) {
        form.setData('section_visibility', { ...form.data.section_visibility, [key]: active });
    }

    function addCustomSection(after) {
        form.setData('custom_sections', [
            ...form.data.custom_sections,
            { title: 'Bagian Baru', content: '', after, active: true },
        ]);
    }

    function updateCustomSection(index, patch) {
        form.setData('custom_sections', form.data.custom_sections.map((section, i) => i === index ? { ...section, ...patch } : section));
    }

    function removeCustomSection(index) {
        form.setData('custom_sections', form.data.custom_sections.filter((_, i) => i !== index));
    }

    function customSectionsAfter(after) {
        return (
            <div className="space-y-3">
                {form.data.custom_sections.map((section, index) => section.after === after && (
                    <CustomSection
                        key={index}
                        section={section}
                        canEdit={canEdit}
                        onChange={(patch) => updateCustomSection(index, patch)}
                        onRemove={() => removeCustomSection(index)}
                    />
                ))}
                {canEdit && (
                    <button type="button" onClick={() => addCustomSection(after)} className="btn btn-outline">
                        + Tambah Card di Bawah Bagian Ini
                    </button>
                )}
            </div>
        );
    }

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

                {canEdit && !isDraftLike && sow.status !== 'completed' && (
                    <div className="rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm text-warning">
                        <strong>Perhatian:</strong> SOW ini sudah dikirim dan sedang berjalan di proses review/tanda tangan
                        ({sow.status_label}). Anda tetap bisa mengubah isinya di bawah, tapi menyimpan perubahan akan
                        <strong> mereset seluruh review &amp; tanda tangan yang sudah dikumpulkan</strong> ke Draft — semua
                        pihak yang sudah terlibat akan diberi tahu, dan SOW harus dikirim ulang dari awal.
                    </div>
                )}

                {canRestartSignatures && (
                    <section className="rounded-xl border-2 border-danger/40 bg-danger/5 p-6 shadow-sm">
                        <h2 className="mb-1 font-semibold text-danger">Tanda Tangan Ditolak HR</h2>
                        <p className="mb-3 text-sm text-danger">HR menolak tanda tangan Teknisi/PIC Vendor. Ulangi proses tanda tangan dari awal.</p>
                        <button onClick={() => setConfirmation({ type: 'restart-signatures' })} className="btn btn-primary">Ulangi Proses Tanda Tangan</button>
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
                    <Section title="1. Informasi Umum" active={isSectionActive('general')} onActiveChange={(v) => setSectionActive('general', v)} canEdit={canEdit}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Nomor SOW *" value={form.data.number} onChange={(v) => form.setData('number', v)} error={form.errors.number} placeholder="Terisi otomatis setelah disimpan" />
                            <Field label="Nama Proyek *" value={form.data.project_name} onChange={(v) => form.setData('project_name', v)} error={form.errors.project_name} />
                            <Field label="Lokasi" value={form.data.site_location} onChange={(v) => form.setData('site_location', v)} error={form.errors.site_location} />
                            <Field label="Client" value={form.data.client_name} onChange={(v) => form.setData('client_name', v)} error={form.errors.client_name} />
                            <Field label="Tanggal Pelaksanaan" value={form.data.execution_date} onChange={(v) => form.setData('execution_date', v)} error={form.errors.execution_date} placeholder="Mis. 23 Agustus 2026 s.d. Selesai" />
                            <div className="block text-sm font-medium text-text">Vendor/Implementor<div className="mt-1 rounded-lg border border-border bg-bg px-3 py-2 text-text-muted">CV. General Solusindo</div></div>
                        </div>
                    </Section>
                    {customSectionsAfter('general')}

                    <Section title="2. Latar Belakang" active={isSectionActive('background')} onActiveChange={(v) => setSectionActive('background', v)} canEdit={canEdit}>
                        <TextArea value={form.data.background} onChange={(v) => form.setData('background', v)} error={form.errors.background} placeholder="Narasi latar belakang pekerjaan..." />
                        <div className="mt-3">
                            {canEdit && isDraftLike && (
                                <label className="block text-sm font-medium text-text">Gambar/Diagram Pendukung
                                    <input ref={fileInput} type="file" accept="image/*" multiple onChange={uploadImages} className="mt-1 block text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong" disabled={!sow.id} />
                                </label>
                            )}
                            {canEdit && !sow.id && <p className="mt-1 text-xs text-warning">Simpan draft dulu sebelum upload gambar.</p>}
                            {sow.images?.length > 0 && (
                                <div className="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4">
                                    {sow.images.map((img) => (
                                        <div key={img.id} className="group relative">
                                            <a href={img.url} target="_blank" rel="noreferrer"><img src={img.url} className="h-24 w-full rounded-lg border border-border object-cover" /></a>
                                            {canEdit && isDraftLike && (
                                                <button type="button" onClick={() => deleteImage(img.id)} className="absolute right-1 top-1 rounded-full bg-danger px-2 py-0.5 text-xs text-white opacity-0 group-hover:opacity-100">✕</button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </Section>
                    {customSectionsAfter('background')}

                    <Section title="3. Ruang Lingkup Pekerjaan" active={isSectionActive('scope')} onActiveChange={(v) => setSectionActive('scope', v)} canEdit={canEdit}>
                        {sow.id ? (
                            <ScopeSections project={project} sections={form.data.scope_sections} canEdit={canEdit} canEditImages={isDraftLike} onChange={(sections) => form.setData('scope_sections', sections)} />
                        ) : (
                            <p className="text-sm text-warning">Simpan draft dulu (Informasi Umum + Latar Belakang) sebelum menambah sub-bab ruang lingkup.</p>
                        )}
                    </Section>
                    {customSectionsAfter('scope')}

                    <Section title="4. Tanggung Jawab" active={isSectionActive('responsibilities')} onActiveChange={(v) => setSectionActive('responsibilities', v)} canEdit={canEdit}>
                        <TextArea value={form.data.responsibilities} onChange={(v) => form.setData('responsibilities', v)} error={form.errors.responsibilities} placeholder="Tanggung jawab Vendor vs Client..." />
                    </Section>
                    {customSectionsAfter('responsibilities')}

                    <Section title="5. Waktu Pelaksanaan & Jadwal" active={isSectionActive('schedule')} onActiveChange={(v) => setSectionActive('schedule', v)} canEdit={canEdit}>
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
                    {customSectionsAfter('schedule')}

                    <Section title="6. Keselamatan Kerja (K3)" active={isSectionActive('safety')} onActiveChange={(v) => setSectionActive('safety', v)} canEdit={canEdit}>
                        <TextArea value={form.data.safety} onChange={(v) => form.setData('safety', v)} error={form.errors.safety} />
                    </Section>
                    {customSectionsAfter('safety')}

                    <Section title="7. Pembayaran" active={isSectionActive('payment')} onActiveChange={(v) => setSectionActive('payment', v)} canEdit={canEdit}>
                        <TextArea value={form.data.payment_terms} onChange={(v) => form.setData('payment_terms', v)} error={form.errors.payment_terms} />
                    </Section>
                    {customSectionsAfter('payment')}

                    <Section title="8. Output Pekerjaan" active={isSectionActive('output')} onActiveChange={(v) => setSectionActive('output', v)} canEdit={canEdit}>
                        <TextArea value={form.data.output} onChange={(v) => form.setData('output', v)} error={form.errors.output} />
                    </Section>
                    {customSectionsAfter('output')}

                    <Section title="9. Garansi Layanan Teknisi" active={isSectionActive('warranty')} onActiveChange={(v) => setSectionActive('warranty', v)} canEdit={canEdit}>
                        <TextArea value={form.data.warranty} onChange={(v) => form.setData('warranty', v)} error={form.errors.warranty} />
                    </Section>
                    {customSectionsAfter('warranty')}

                    <Section title="10. Catatan" active={isSectionActive('notes')} onActiveChange={(v) => setSectionActive('notes', v)} canEdit={canEdit}>
                        <TextArea value={form.data.notes} onChange={(v) => form.setData('notes', v)} error={form.errors.notes} />
                    </Section>
                    {customSectionsAfter('notes')}

                    <Section title="11. PIC & Kontak" active={isSectionActive('contacts')} onActiveChange={(v) => setSectionActive('contacts', v)} canEdit={canEdit}>
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
                    {customSectionsAfter('contacts')}

                    <Section title="12. Penutup" active={isSectionActive('closing')} onActiveChange={(v) => setSectionActive('closing', v)} canEdit={canEdit}>
                        <TextArea value={form.data.closing} onChange={(v) => form.setData('closing', v)} error={form.errors.closing} />
                    </Section>
                    {customSectionsAfter('closing')}

                    {canEdit && (
                        <div className="flex flex-wrap justify-end gap-2">
                            {sow.id && (
                                <a href={`/operational/projects/${project.id}/sow/print`} target="_blank" rel="noreferrer" className="btn btn-outline">
                                    Lihat / Cetak PDF
                                </a>
                            )}
                            <button disabled={form.processing} className="btn btn-outline">
                                {form.processing ? 'Menyimpan…' : isDraftLike ? 'Simpan Draft' : 'Simpan Perubahan (Reset Proses)'}
                            </button>
                            {sow.id && isDraftLike && (
                                <button type="button" onClick={submitToHr} className="btn btn-primary">
                                    Kirim ke HR
                                </button>
                            )}
                        </div>
                    )}
                    {!canEdit && sow.id && (
                        <div className="flex justify-end">
                            <a href={`/operational/projects/${project.id}/sow/print`} target="_blank" rel="noreferrer" className="btn btn-outline">
                                Lihat / Cetak PDF
                            </a>
                        </div>
                    )}
                </form>
            </div>
            <ConfirmDialog
                open={Boolean(confirmationContent)}
                onClose={() => setConfirmation(null)}
                onConfirm={runConfirmedAction}
                title={confirmationContent?.title}
                description={confirmationContent?.description}
                tone={confirmationContent?.tone}
                confirmLabel={confirmationContent?.confirmLabel}
                processing={actionProcessing || (confirmation?.type === 'reset-process' && form.processing)}
            />
        </AppLayout>
    );
}

function Section({ title, children, active = true, onActiveChange, canEdit = false }) {
    return (
        <section className={`card p-4 sm:p-6 ${active ? '' : 'opacity-60'}`}>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 className="font-semibold text-text">{title}</h2>
                {onActiveChange && (
                    <label className="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-text-muted">
                        <input type="checkbox" checked={active} onChange={(e) => onActiveChange(e.target.checked)} disabled={!canEdit} className="h-5 w-5 accent-primary" />
                        Aktif di PDF
                    </label>
                )}
            </div>
            <div className={active ? '' : 'pointer-events-none'}>{children}</div>
        </section>
    );
}

function CustomSection({ section, canEdit, onChange, onRemove }) {
    return (
        <section className={`card border-l-4 border-l-primary p-4 sm:p-6 ${section.active !== false ? '' : 'opacity-60'}`}>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <span className="text-xs font-bold uppercase tracking-wide text-primary">Card Tambahan</span>
                <div className="flex items-center gap-3">
                    <label className="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-text-muted">
                        <input type="checkbox" checked={section.active !== false} onChange={(e) => onChange({ active: e.target.checked })} disabled={!canEdit} className="h-5 w-5 accent-primary" />
                        Aktif di PDF
                    </label>
                    {canEdit && <button type="button" onClick={onRemove} className="text-sm font-semibold text-danger">Hapus</button>}
                </div>
            </div>
            <div className={section.active === false ? 'pointer-events-none' : 'space-y-3'}>
                <Field label="Judul Card" value={section.title} onChange={(title) => onChange({ title })} />
                <label className="block text-sm font-medium text-text">Deskripsi
                    <TextArea value={section.content} onChange={(content) => onChange({ content })} placeholder="Isi deskripsi bagian tambahan..." />
                </label>
            </div>
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

function ScopeSections({ project, sections, canEdit, canEditImages, onChange }) {
    const [removeIndex, setRemoveIndex] = useState(null);
    function addSection() {
        onChange([...sections, { id: null, title: 'Sub Bab Baru', content: '', images: [] }]);
    }

    function updateSection(index, patch) {
        onChange(sections.map((section, i) => i === index ? { ...section, ...patch } : section));
    }

    function removeSection(index) {
        setRemoveIndex(index);
    }

    function confirmRemoveSection() {
        onChange(sections.filter((_, i) => i !== removeIndex));
        setRemoveIndex(null);
    }

    function moveSection(index, direction) {
        const target = direction === 'up' ? index - 1 : index + 1;
        if (target < 0 || target >= sections.length) return;
        const reordered = [...sections];
        [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
        onChange(reordered);
    }

    return (
        <div className="space-y-4">
            {sections.length === 0 && <p className="text-sm text-text-muted">Belum ada sub-bab.</p>}
            {sections.map((s, i) => (
                <ScopeSectionCard
                    key={s.id ?? `new-${i}`}
                    project={project}
                    section={s}
                    letter={String.fromCharCode(65 + i)}
                    isFirst={i === 0}
                    isLast={i === sections.length - 1}
                    canEdit={canEdit}
                    canEditImages={canEditImages}
                    onChange={(patch) => updateSection(i, patch)}
                    onRemove={() => removeSection(i)}
                    onMove={(direction) => moveSection(i, direction)}
                />
            ))}
            {canEdit && (
                <button type="button" onClick={addSection} className="btn btn-outline">
                    + Tambah Sub Bab
                </button>
            )}
            {canEdit && <p className="text-xs text-text-muted">Perubahan judul, isi, urutan, penambahan, dan penghapusan sub-bab disimpan bersama tombol Simpan Draft.</p>}
            <ConfirmDialog
                open={removeIndex !== null}
                onClose={() => setRemoveIndex(null)}
                onConfirm={confirmRemoveSection}
                title="Hapus sub-bab?"
                description={`Sub-bab “${removeIndex !== null ? sections[removeIndex]?.title : ''}” akan dihapus saat Simpan Draft.`}
                tone="danger"
                confirmLabel="Hapus Sub-bab"
            />
        </div>
    );
}

function ScopeSectionCard({ project, section, letter, isFirst, isLast, canEdit, canEditImages, onChange, onRemove, onMove }) {
    const fileRef = useRef(null);
    const [deleteImageId, setDeleteImageId] = useState(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    function uploadImages(e) {
        const files = Array.from(e.target.files || []);
        if (files.length === 0) return;
        if (!section.id) return;
        router.post(`/operational/projects/${project.id}/sow/scope-sections/${section.id}/images`, { images: files }, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => { if (fileRef.current) fileRef.current.value = ''; },
        });
    }

    function deleteImage(imageId) {
        setDeleteProcessing(true);
        router.delete(`/operational/projects/${project.id}/sow/scope-sections/${section.id}/images/${imageId}`, {
            preserveScroll: true,
            onSuccess: () => setDeleteImageId(null),
            onFinish: () => setDeleteProcessing(false),
        });
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
                    <input value={section.title} onChange={(e) => onChange({ title: e.target.value })} className="mt-1 w-full rounded-lg border border-border px-3 py-2 outline-none focus:border-navy" />
                </label>
                <div className="flex shrink-0 gap-1 pt-6">
                    <button type="button" onClick={() => onMove('up')} disabled={isFirst} className="rounded-lg border border-border px-2 py-1.5 text-xs font-semibold text-text-muted disabled:opacity-30" title="Pindah naik">↑</button>
                    <button type="button" onClick={() => onMove('down')} disabled={isLast} className="rounded-lg border border-border px-2 py-1.5 text-xs font-semibold text-text-muted disabled:opacity-30" title="Pindah turun">↓</button>
                    <button type="button" onClick={onRemove} className="rounded-lg border border-danger/30 px-2 py-1.5 text-xs font-semibold text-danger" title="Hapus sub-bab">Hapus</button>
                </div>
            </div>
            <div className="mt-3">
                <TextArea value={section.content} onChange={(content) => onChange({ content })} placeholder="Isi sub-bab ini..." />
            </div>
            {canEditImages && <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                <label className="text-sm font-medium text-text">
                    Gambar pendukung
                    <input ref={fileRef} type="file" accept="image/*" multiple onChange={uploadImages} disabled={!section.id} className="mt-1 block text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong disabled:opacity-50" />
                </label>
                {!section.id && <span className="text-xs text-warning">Simpan Draft dahulu sebelum menambah gambar.</span>}
            </div>}
            {section.images?.length > 0 && (
                <div className="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4">
                    {section.images.map((img) => (
                        <div key={img.id} className="group relative">
                            <a href={img.url} target="_blank" rel="noreferrer"><img src={img.url} className="h-24 w-full rounded-lg border border-border object-cover" /></a>
                            {canEditImages && <button type="button" onClick={() => setDeleteImageId(img.id)} className="absolute right-1 top-1 rounded-full bg-danger px-2 py-0.5 text-xs text-white opacity-0 group-hover:opacity-100">✕</button>}
                        </div>
                    ))}
                </div>
            )}
            <ConfirmDialog
                open={deleteImageId !== null}
                onClose={() => setDeleteImageId(null)}
                onConfirm={() => deleteImage(deleteImageId)}
                title="Hapus gambar sub-bab?"
                description="Gambar pendukung ini akan dihapus dari sub-bab SOW."
                tone="danger"
                confirmLabel="Hapus Gambar"
                processing={deleteProcessing}
            />
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
