import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import SignaturePad from '../../../Components/SignaturePad';
import { PageHeader } from '../../../Components/ui';

export default function Show({ sow, canSign, signUrl, roleLabel, backHref, autoSign = false }) {
    const [signature, setSignature] = useState(null);
    const [processing, setProcessing] = useState(false);

    function submit() {
        if (!autoSign && !signature) return;
        setProcessing(true);
        router.post(signUrl, autoSign ? {} : { signature }, {
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <AppLayout>
            <Head title={sow.number || `SOW #${sow.id}`} />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title={<span className="flex flex-wrap items-center gap-3">{sow.number} — {sow.project_name} <span className="badge badge-primary">{sow.status_label}</span></span>}
                    subtitle={sow.company || sow.customer}
                    back={{ href: backHref, label: 'Kembali' }}
                />

                <Section title="Informasi Umum">
                    <Info label="Lokasi" value={sow.site_location} />
                    <Info label="Client" value={sow.client_name} />
                    <Info label="Tanggal Pelaksanaan" value={sow.execution_date} />
                </Section>

                <Section title="Latar Belakang"><Body value={sow.background} /></Section>
                <Section title="Ruang Lingkup Pekerjaan">
                    {(sow.scope_sections || []).length === 0 ? (
                        <p className="text-sm text-text-muted">Belum ada sub-bab.</p>
                    ) : (
                        <div className="space-y-4">
                            {sow.scope_sections.map((s, i) => (
                                <div key={s.id}>
                                    <h3 className="text-sm font-semibold text-text">{String.fromCharCode(65 + i)}. {s.title}</h3>
                                    <Body value={s.content} />
                                    {s.images?.length > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {s.images.map((img) => <img key={img.id} src={img.url} className="h-20 w-20 rounded-lg border border-border object-cover" />)}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </Section>
                <Section title="Tanggung Jawab"><Body value={sow.responsibilities} /></Section>
                <Section title="Waktu Pelaksanaan & Jadwal">
                    <Info label="Estimasi Durasi Pekerjaan" value={sow.schedule_duration} />
                    <Info label="Waktu Mulai" value={sow.schedule_start_date} />
                    <Info label="Target Selesai" value={sow.schedule_end_date} />
                </Section>
                <Section title="Keselamatan Kerja (K3)"><Body value={sow.safety} /></Section>
                <Section title="Pembayaran"><Body value={sow.payment_terms} /></Section>
                <Section title="Output Pekerjaan"><Body value={sow.output} /></Section>
                <Section title="Garansi"><Body value={sow.warranty} /></Section>
                <Section title="Catatan"><Body value={sow.notes} /></Section>

                <Section title="PIC & Kontak">
                    <Info label="PIC Vendor" value={sow.vendor ? `${sow.vendor.contact_person || '—'} (${sow.vendor.phone || '—'})` : '—'} />
                    <Info label="Teknisi Pelaksana" value={sow.technician ? `${sow.technician.name} (${sow.technician.phone || '—'})` : '—'} />
                    <Info label="PIC Client" value={`${sow.client_pic_name || '—'} (${sow.client_pic_phone || '—'})`} />
                </Section>

                <Section title="Penutup"><Body value={sow.closing} /></Section>

                <Section title="Tanda Tangan">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SignaturePreview label="Teknisi" name={sow.technician?.name} image={sow.signatures.technician} at={sow.signatures.technician_signed_at} />
                        <SignaturePreview label="PIC Vendor" name={sow.signatures.vendor_signed_by} image={sow.signatures.vendor} at={sow.signatures.vendor_signed_at} />
                        <SignaturePreview label="Operasional" name={sow.signatures.admin_signed_by} image={sow.signatures.admin} at={sow.signatures.admin_signed_at} />
                        <SignaturePreview label="Project Manager" name={sow.signatures.director_signed_by} image={sow.signatures.director} at={sow.signatures.director_signed_at} />
                    </div>
                </Section>

                {canSign ? (
                    <div className="space-y-3 rounded-xl border-2 border-navy/40 bg-navy/5 p-6 shadow-sm">
                        <h2 className="font-semibold text-text">Perlu Tanda Tangan Anda — {roleLabel}</h2>
                        {autoSign ? (
                            <>
                                <p className="text-sm text-text-muted">Tanda tangan akan diambil otomatis dari tanda tangan resmi Administrator.</p>
                                <div className="flex justify-end">
                                    <button onClick={submit} disabled={processing} className="btn btn-primary">
                                        {processing ? 'Menyimpan…' : 'Tanda Tangani Otomatis & Kirim'}
                                    </button>
                                </div>
                            </>
                        ) : (
                            <>
                                <SignaturePad onChange={setSignature} />
                                <div className="flex justify-end">
                                    <button onClick={submit} disabled={!signature || processing} className="btn btn-primary">
                                        {processing ? 'Menyimpan…' : 'Tanda Tangani & Kirim'}
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                ) : (
                    <div className="card p-4 text-sm text-text-muted">
                        {sow.status === 'completed'
                            ? 'SOW ini sudah selesai — semua pihak sudah tanda tangan.'
                            : `Belum giliran Anda. Status saat ini: ${sow.status_label}.`}
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

function SignaturePreview({ label, name, image, at }) {
    return (
        <div>
            <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">{label}</div>
            {image ? (
                <>
                    <img src={image} className="mt-1 h-16 border-b border-border object-contain" />
                    <div className="text-xs text-text-muted">{name} · {at ? new Date(at).toLocaleString('id-ID') : ''}</div>
                </>
            ) : <div className="mt-1 text-sm text-text-muted">Belum tanda tangan</div>}
        </div>
    );
}
