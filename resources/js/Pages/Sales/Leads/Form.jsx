import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Field, Input, Select, Textarea, FormActions } from '../../../Components/ui';

export default function Form({ lead = null, contacts, stageOptions, selectedContactId = null }) {
    const editing = Boolean(lead);
    const { data, setData, post, put, processing, errors } = useForm({
        contact_id: lead?.contact_id ?? selectedContactId ?? '',
        stage: lead?.stage ?? 'new', source: lead?.source ?? '', notes: lead?.notes ?? '',
        pic_name: lead?.pic_name ?? '', pic_position: lead?.pic_position ?? '', pic_phone: lead?.pic_phone ?? '',
    });

    function submit(e) {
        e.preventDefault();
        editing ? put(`/sales/leads/${lead.id}`) : post('/sales/leads');
    }

    return (
        <AppLayout>
            <Head title={editing ? 'Edit Lead' : 'Tambah Lead'} />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title={editing ? 'Edit Lead' : 'Tambah Lead'}
                    subtitle="Hubungkan lead dengan contact yang sudah tersedia."
                    back={{ href: editing ? `/sales/leads/${lead.id}` : '/sales/leads' }}
                />

                <form onSubmit={submit}>
                    <Card className="space-y-5">
                        {contacts.length === 0 && (
                            <div className="rounded-xl border border-warning/20 bg-warning-soft p-4 text-sm font-medium text-warning">
                                Anda belum memiliki contact. <Link href="/sales/contacts/create" className="font-semibold underline">Buat contact terlebih dahulu.</Link>
                            </div>
                        )}

                        <Field label="Contact" required error={errors.contact_id}>
                            <Select value={data.contact_id} onChange={(e) => setData('contact_id', e.target.value)}>
                                <option value="">Pilih contact</option>
                                {contacts.map((c) => <option key={c.id} value={c.id}>{c.name}{c.company_name ? ` — ${c.company_name}` : ''}</option>)}
                            </Select>
                        </Field>

                        <Field label="Stage" required error={errors.stage}>
                            <Select value={data.stage} onChange={(e) => setData('stage', e.target.value)}>
                                {stageOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                            </Select>
                        </Field>

                        <Field label="Source" error={errors.source}>
                            <Input value={data.source} onChange={(e) => setData('source', e.target.value)} placeholder="Referral, Website, Telepon, dll." />
                        </Field>

                        <div>
                            <div className="grid gap-5 sm:grid-cols-3">
                                <Field label="Nama PIC Customer" error={errors.pic_name}>
                                    <Input value={data.pic_name} onChange={(e) => setData('pic_name', e.target.value)} placeholder="PJ di lapangan" />
                                </Field>
                                <Field label="Jabatan PIC" error={errors.pic_position}>
                                    <Input value={data.pic_position} onChange={(e) => setData('pic_position', e.target.value)} placeholder="Mis. Manager Operasional" />
                                </Field>
                                <Field label="Telepon PIC" error={errors.pic_phone}>
                                    <Input value={data.pic_phone} onChange={(e) => setData('pic_phone', e.target.value)} placeholder="08xxxxxxxxxx" />
                                </Field>
                            </div>
                            <p className="mt-2 text-xs text-text-muted">PIC ini nanti dipakai otomatis untuk pihak yang tanda tangan BAST.</p>
                        </div>

                        <Field label="Catatan" error={errors.notes}>
                            <Textarea rows={4} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </Field>

                        <FormActions
                            cancelHref={editing ? `/sales/leads/${lead.id}` : '/sales/leads'}
                            processing={processing}
                            disabled={contacts.length === 0}
                        />
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
