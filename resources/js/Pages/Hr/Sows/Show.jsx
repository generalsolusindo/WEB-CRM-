import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Show({ sow, canReview, canVerifySignatures }) {
    const form = useForm({ approved: true, notes: '' });
    const [action, setAction] = useState(null);
    const reviewUrl = canVerifySignatures ? `/hr/sows/${sow.id}/verify-signatures` : `/hr/sows/${sow.id}/review`;

    function submit(approved) {
        setAction(approved ? 'approve' : 'reject');
        form.transform((data) => ({ ...data, approved }));
        form.post(reviewUrl, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title={sow.number || `SOW #${sow.id}`} />
            <div className="mx-auto max-w-3xl space-y-5">
                <div>
                    <Link href="/hr/sows" className="text-sm text-info">← Kembali</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">{sow.number} — {sow.project_name}</h1>
                    <p className="text-sm text-text-muted">{sow.company || sow.customer} · dibuat oleh {sow.created_by}</p>
                </div>

                {sow.hr_content_reviewed_by && (
                    <div className="rounded-xl border border-border bg-surface p-4 text-sm text-text-muted">
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
                    <Info label="Teknisi Pelaksana" value={sow.technician ? `${sow.technician.name} (${sow.technician.phone || '—'})` : '—'} />
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
                    <div className="space-y-3 rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <h2 className="font-semibold text-text">{canVerifySignatures ? 'Verifikasi Tanda Tangan' : 'Review SOW'}</h2>
                        <textarea
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            placeholder="Catatan (wajib diisi jika menolak)"
                            className="input min-h-[80px] w-full"
                        />
                        {form.errors.notes && <span className="block text-xs text-danger">{form.errors.notes}</span>}
                        <div className="flex justify-end gap-2">
                            <button disabled={form.processing} onClick={() => submit(false)} className="rounded-lg border border-danger/30 px-5 py-2 text-sm font-semibold text-danger disabled:opacity-50">
                                {form.processing && action === 'reject' ? 'Memproses…' : 'Tolak'}
                            </button>
                            <button disabled={form.processing} onClick={() => submit(true)} className="rounded-lg bg-success px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
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
        <section className="space-y-2 rounded-xl border border-border bg-surface p-6 shadow-sm">
            <h2 className="font-semibold text-text">{title}</h2>
            {children}
        </section>
    );
}

function Body({ value }) {
    return <p className="whitespace-pre-line text-sm text-text">{value || '—'}</p>;
}

function Info({ label, value }) {
    return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 text-sm text-text">{value || '—'}</div></div>;
}

function SignaturePreview({ label, image, name }) {
    return (
        <div>
            <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div>
            {image ? (
                <>
                    <img src={image} className="mt-1 h-16 border-b border-border object-contain" />
                    {name && <div className="text-xs text-text-muted">{name}</div>}
                </>
            ) : <div className="mt-1 text-sm text-text-muted">Belum tanda tangan</div>}
        </div>
    );
}
