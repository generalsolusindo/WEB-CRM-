import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { PageHeader, Card, CardHeader, Field, Input, Button } from '../../Components/ui';

function DocumentNumberForm({ documentType, label, middle, year, summary }) {
    const { data, setData, post, processing, errors } = useForm({
        document_type: documentType,
        next_sequence: summary.nextSequence,
    });

    function submit(e) {
        e.preventDefault();
        post('/admin/document-numbering', { preserveScroll: true });
    }

    const month = String(new Date().getMonth() + 1).padStart(2, '0');
    const preview = `${data.next_sequence || '...'}/${middle}/${month}/${year}`;

    return (
        <Card padded={false}>
            <CardHeader title={label} />
            <form onSubmit={submit} className="space-y-5 px-5 py-5">
                <div className="rounded-lg border border-border bg-surface-muted px-4 py-3 text-sm text-text-muted">
                    Nomor {label.toLowerCase()} terakhir tahun {year}: <strong className="text-text">{summary.lastSequence}</strong>
                    {summary.hasCustomSetting && <span className="ml-1">(sudah ada setting kustom aktif)</span>}
                </div>
                <Field label={`${label} berikutnya dimulai dari nomor urut`} required error={errors.next_sequence}>
                    <Input
                        type="number"
                        min={summary.lastSequence + 1}
                        value={data.next_sequence}
                        onChange={(e) => setData('next_sequence', e.target.value)}
                    />
                </Field>
                <div className="rounded-lg border border-border bg-primary-soft px-4 py-3 text-sm text-primary-strong">
                    Contoh nomor berikutnya: <strong>{preview}</strong>
                </div>
                <div className="flex justify-end">
                    <Button type="submit" loading={processing}>Simpan</Button>
                </div>
            </form>
        </Card>
    );
}

export default function DocumentNumbering({ year, invoice, quotation }) {
    return (
        <AppLayout>
            <Head title="Penomoran Dokumen" />
            <div className="mx-auto max-w-xl space-y-5">
                <PageHeader
                    title="Penomoran Dokumen"
                    subtitle="Atur nomor urut Invoice dan Quotation berikutnya untuk tahun berjalan — berguna kalau perlu menyambung dari nomor manual/sistem lama. Setelah dokumen baru dibuat, nomor akan otomatis lanjut naik seperti biasa."
                />
                <DocumentNumberForm documentType="invoice" label="Invoice" middle="GS-INV" year={year} summary={invoice} />
                <DocumentNumberForm documentType="quotation" label="Quotation" middle="GS-PN" year={year} summary={quotation} />
            </div>
        </AppLayout>
    );
}
