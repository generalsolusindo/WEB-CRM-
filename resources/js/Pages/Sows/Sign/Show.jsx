import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import SignaturePad from '../../../Components/SignaturePad';

export default function Show({ sow, canSign, signUrl, roleLabel, backHref }) {
    const [signature, setSignature] = useState(null);
    const [processing, setProcessing] = useState(false);

    function submit() {
        if (!signature) return;
        setProcessing(true);
        router.post(signUrl, { signature }, {
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <AppLayout>
            <Head title={sow.number || `SOW #${sow.id}`} />
            <div className="mx-auto max-w-3xl space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link href={backHref} className="text-sm text-info">← Kembali</Link>
                        <h1 className="mt-2 text-2xl font-bold text-text">{sow.number} — {sow.project_name}</h1>
                        <p className="text-sm text-text-muted">{sow.company || sow.customer}</p>
                    </div>
                    <span className="rounded-full bg-info/10 px-3 py-1 text-xs font-semibold text-info">{sow.status_label}</span>
                </div>

                <Section title="Informasi Umum">
                    <Info label="Lokasi" value={sow.site_location} />
                    <Info label="Client" value={sow.client_name} />
                    <Info label="Tanggal Pelaksanaan" value={sow.execution_date} />
                </Section>

                <Section title="Latar Belakang"><Body value={sow.background} /></Section>
                <Section title="Ruang Lingkup Pekerjaan">
                    <Body value={sow.scope_pre_work} />
                    <Body value={sow.scope_other} />
                </Section>
                <Section title="Tanggung Jawab"><Body value={sow.responsibilities} /></Section>
                <Section title="Waktu Pelaksanaan"><Body value={sow.schedule} /></Section>
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
                        <SignaturePreview label="Admin Project" name={sow.signatures.admin_signed_by} image={sow.signatures.admin} at={sow.signatures.admin_signed_at} />
                        <SignaturePreview label="Direktur" name={sow.signatures.director_signed_by} image={sow.signatures.director} at={sow.signatures.director_signed_at} />
                    </div>
                </Section>

                {canSign ? (
                    <div className="space-y-3 rounded-xl border-2 border-navy/40 bg-navy/5 p-6 shadow-sm">
                        <h2 className="font-semibold text-text">Perlu Tanda Tangan Anda — {roleLabel}</h2>
                        <SignaturePad onChange={setSignature} />
                        <div className="flex justify-end">
                            <button onClick={submit} disabled={!signature || processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                                {processing ? 'Menyimpan…' : 'Tanda Tangani & Kirim'}
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-xl border border-border bg-surface p-4 text-sm text-text-muted">
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

function SignaturePreview({ label, name, image, at }) {
    return (
        <div>
            <div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div>
            {image ? (
                <>
                    <img src={image} className="mt-1 h-16 border-b border-border object-contain" />
                    <div className="text-xs text-text-muted">{name} · {at ? new Date(at).toLocaleString('id-ID') : ''}</div>
                </>
            ) : <div className="mt-1 text-sm text-text-muted">Belum tanda tangan</div>}
        </div>
    );
}
