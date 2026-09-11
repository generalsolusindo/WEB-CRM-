import { Head, useForm } from '@inertiajs/react';
import { FiFileText } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Field, Input, Textarea, Button } from '../../../Components/ui';

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
                <PageHeader
                    title={`Generate BAST — ${project.number}`}
                    subtitle={`${project.company || project.customer}${project.po_number ? ` · PO ${project.po_number}` : ''}`}
                    back={{ href: `/operational/projects/${project.id}`, label: 'Kembali ke Project' }}
                />

                <form onSubmit={submit}>
                    <Card className="space-y-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Nomor BAST" error={form.errors.number}>
                                <Input value={form.data.number} onChange={(e) => form.setData('number', e.target.value)} placeholder="Diisi manual" />
                            </Field>
                            <Field label="Tanggal Pelaksanaan" error={form.errors.event_date}>
                                <Input type="date" value={form.data.event_date} onChange={(e) => form.setData('event_date', e.target.value)} />
                            </Field>
                        </div>

                        <Field label="Pekerjaan" error={form.errors.job_title}>
                            <Input value={form.data.job_title} onChange={(e) => form.setData('job_title', e.target.value)} placeholder="Mis. Instalasi PLTS On-Grid 10 kWp" />
                        </Field>

                        <Field label="Deskripsi Pelaksanaan Pekerjaan" error={form.errors.work_description}>
                            <Textarea rows={4} value={form.data.work_description} onChange={(e) => form.setData('work_description', e.target.value)} placeholder="Uraikan pekerjaan yang telah dilaksanakan..." />
                        </Field>

                        <div>
                            <h2 className="mb-2 text-sm font-semibold text-text">Pihak Kesatu (Customer)</h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Nama PIC" error={form.errors.pic_name}>
                                    <Input value={form.data.pic_name} onChange={(e) => form.setData('pic_name', e.target.value)} />
                                </Field>
                                <Field label="Jabatan PIC" error={form.errors.pic_position}>
                                    <Input value={form.data.pic_position} onChange={(e) => form.setData('pic_position', e.target.value)} />
                                </Field>
                                <div className="sm:col-span-2">
                                    <Field label="Alamat" error={form.errors.pic_address}>
                                        <Input value={form.data.pic_address} onChange={(e) => form.setData('pic_address', e.target.value)} />
                                    </Field>
                                </div>
                            </div>
                            <p className="mt-1 text-xs text-text-muted">Terisi otomatis dari data PIC di Lead — silakan sesuaikan dengan kondisi di lapangan.</p>
                        </div>

                        <div>
                            <h2 className="mb-2 text-sm font-semibold text-text">Pihak Kedua (Teknisi)</h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Nama Leader" error={form.errors.leader_name}>
                                    <Input value={form.data.leader_name} onChange={(e) => form.setData('leader_name', e.target.value)} />
                                </Field>
                                <Field label="Jabatan" error={form.errors.leader_position}>
                                    <Input value={form.data.leader_position} onChange={(e) => form.setData('leader_position', e.target.value)} placeholder="Teknisi" />
                                </Field>
                            </div>
                            <p className="mt-1 text-xs text-text-muted">Terisi otomatis dari leader tim teknisi project ini — silakan sesuaikan bila perlu.</p>
                        </div>

                        <div className="flex flex-wrap justify-end gap-2">
                            {hasDraft && (
                                <Button href={`/operational/projects/${project.id}/bast-draft/print`} external variant="outline" icon={FiFileText}>Lihat / Cetak PDF</Button>
                            )}
                            <Button type="submit" loading={form.processing}>Simpan</Button>
                        </div>
                        {!hasDraft && <p className="text-right text-xs text-text-muted">Simpan dulu untuk bisa melihat/cetak PDF-nya.</p>}
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
