import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import { feedback } from '../../../Components/feedback';
import { PageHeader } from '../../../Components/ui';

export default function Show({ sow, technicianKtp, canReview, canVerifySignatures }) {
    const form = useForm({ approved: true, notes: '' });
    const [action, setAction] = useState(null);
    const reviewUrl = canVerifySignatures ? `/hr/sows/${sow.id}/verify-signatures` : `/hr/sows/${sow.id}/review`;

    async function submit(approved) {
        const verifying = canVerifySignatures;
        const subject = verifying ? 'tanda tangan SOW' : 'isi SOW';
        const ok = await feedback.confirm(approved
            ? { tone: 'question', title: `Setujui ${subject}?`, text: `${sow.number} akan diteruskan ke tahap berikutnya.`, confirmLabel: 'Ya, setujui' }
            : { tone: 'danger', title: `Tolak ${subject}?`, text: `${sow.number} akan dikembalikan ke Operasional. Pastikan catatan penolakan sudah diisi.`, confirmLabel: 'Ya, tolak' });
        if (!ok) return;

        setAction(approved ? 'approve' : 'reject');
        form.transform((data) => ({ ...data, approved }));
        feedback.expect({
            success: { title: approved ? 'Disetujui' : 'Dikembalikan ke Operasional', style: 'popup' },
            error: { title: 'Gagal memproses SOW' },
        });
        form.post(reviewUrl, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={sow.number || `SOW #${sow.id}`} />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title={`${sow.number} — ${sow.project_name}`}
                    subtitle={`${sow.company || sow.customer} · dibuat oleh ${sow.created_by}`}
                    back={{ href: '/hr/sows', label: 'Kembali' }}
                />

                {sow.hr_content_reviewed_by && (
                    <div className="card p-4 text-sm text-text-muted">
                        Direview oleh {sow.hr_content_reviewed_by} · {new Date(sow.hr_content_reviewed_at).toLocaleString('id-ID')}
                        {sow.hr_content_review_notes && <div className="mt-1 text-text">Catatan: {sow.hr_content_review_notes}</div>}
                    </div>
                )}

                <Section title="Informasi Umum">
                    <Info label="Lokasi" value={sow.site_location} />
                    <Info label="Client" value={sow.client_name} />
                    <Info label="Tanggal Pelaksanaan" value={sow.execution_date} />
                </Section>

                <Section title="Latar Belakang">
                    <p className="whitespace-pre-line text-sm text-text">{sow.background || '—'}</p>
                    {sow.images?.length > 0 && (
                        <div className="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4">
                            {sow.images.map((img) => (
                                <a key={img.id} href={img.url} target="_blank" rel="noreferrer"><img src={img.url} className="h-24 w-full rounded-lg border border-border object-cover" /></a>
                            ))}
                        </div>
                    )}
                </Section>

                <Section title="Pengadaan Material">
                    {sow.materials?.length > 0 ? (
                        <ul className="list-inside list-disc text-sm text-text">
                            {sow.materials.map((m, i) => <li key={i}>{m.item_name} — {m.qty} {m.unit}</li>)}
                        </ul>
                    ) : <p className="text-sm text-text-muted">—</p>}
                </Section>

                <Section title="Persiapan & Pra-Kerja"><Body value={sow.scope_pre_work} /></Section>
                <Section title="Ruang Lingkup Lainnya"><Body value={sow.scope_other} /></Section>
                <Section title="Tanggung Jawab"><Body value={sow.responsibilities} /></Section>
                <Section title="Waktu Pelaksanaan & Jadwal"><Body value={sow.schedule} /></Section>
                <Section title="Keselamatan Kerja (K3)"><Body value={sow.safety} /></Section>
                <Section title="Pembayaran"><Body value={sow.payment_terms} /></Section>
                <Section title="Output Pekerjaan"><Body value={sow.output} /></Section>
                <Section title="Garansi"><Body value={sow.warranty} /></Section>
                <Section title="Catatan"><Body value={sow.notes} /></Section>

                <Section title="PIC & Kontak">
                    <Info label="PIC Vendor" value={sow.vendor ? `${sow.vendor.contact_person || '—'} (${sow.vendor.phone || '—'})` : '—'} />
                    <Info
                        label="Teknisi Pelaksana"
                        value={sow.technician ? (
                            <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span>{sow.technician.name} ({sow.technician.phone || '—'})</span>
                                {technicianKtp?.url
                                    ? <a href={technicianKtp.url} target="_blank" rel="noreferrer" className="rounded-lg border border-primary/30 bg-primary-soft px-2.5 py-1 text-xs font-semibold text-primary-strong hover:bg-primary hover:text-white">Lihat KTP</a>
                                    : <span className="badge badge-warning">KTP belum diunggah</span>}
                            </span>
                        ) : '—'}
                    />
                    {technicianKtp?.nik && <Info label="NIK Teknisi" value={technicianKtp.nik} />}
                    {sow.technician_team_note && <Info label="Anggota Tim Lainnya" value={sow.technician_team_note} />}
                    <Info label="PIC Client" value={`${sow.client_pic_name || '—'} (${sow.client_pic_phone || '—'})`} />
                </Section>

                <Section title="Penutup"><Body value={sow.closing} /></Section>

                {(sow.signatures?.technician || sow.signatures?.vendor) && (
                    <Section title="Tanda Tangan">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SignaturePreview label="Teknisi" image={sow.signatures.technician} />
                            <SignaturePreview label="PIC Vendor" image={sow.signatures.vendor} name={sow.signatures.vendor_signed_by} />
                        </div>
                    </Section>
                )}

                {(canReview || canVerifySignatures) && (
                    <div className="space-y-3 card p-6">
                        <h2 className="font-semibold text-text">{canVerifySignatures ? 'Verifikasi Tanda Tangan' : 'Review SOW'}</h2>
                        <textarea
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            placeholder="Catatan (wajib diisi jika menolak)"
                            className="input min-h-[80px] w-full"
                        />
                        {form.errors.notes && <span className="block text-xs text-danger">{form.errors.notes}</span>}
                        <div className="flex justify-end gap-2">
                            <button disabled={form.processing} onClick={() => submit(false)} className="btn btn-outline border-danger/40 text-danger">
                                {form.processing && action === 'reject' ? 'Memproses…' : 'Tolak'}
                            </button>
                            <button disabled={form.processing} onClick={() => submit(true)} className="btn btn-primary bg-success">
                                {form.processing && action === 'approve' ? 'Memproses…' : 'Setujui'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function Section({ title, children }) {
    return (
        <section className="space-y-2 card p-6">
            <h2 className="font-semibold text-text">{title}</h2>
            {children}
        </section>
    );
}

function Body({ value }) {
    return <p className="whitespace-pre-line text-sm text-text">{value || '—'}</p>;
}

function Info({ label, value }) {
    return <div><div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">{label}</div><div className="mt-1 text-sm text-text">{value || '—'}</div></div>;
}

function SignaturePreview({ label, image, name }) {
    return (
        <div>
            <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">{label}</div>
            {image ? (
                <>
                    <img src={image} className="mt-1 h-16 border-b border-border object-contain" />
                    {name && <div className="text-xs text-text-muted">{name}</div>}
                </>
            ) : <div className="mt-1 text-sm text-text-muted">Belum tanda tangan</div>}
        </div>
    );
}
