import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import SearchableSelect from '../../../Components/SearchableSelect';
import { PageHeader, Card, Button, Field, Input, Info, InfoGrid, StatusBadge, CurrencyInput } from '../../../Components/ui';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Show({ survey, canSource, vendors }) {
    const isVendorMode = survey.delivery_mode === 'vendor';
    const { data, setData, post, processing, errors } = useForm({
        vendor_id: survey.vendor_id ?? '',
        cost: survey.cost ?? '',
    });

    function submit(e) {
        e.preventDefault();
        post(`/procurement/surveys/${survey.id}/source`);
    }

    return (
        <AppLayout>
            <Head title={survey.code} />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title={<span className="flex items-center gap-3">{survey.code} <StatusBadge status={survey.status} label={survey.status_label} tone="warning" /></span>}
                    subtitle={`${survey.lead?.contact?.name ?? ''} · ${survey.lead?.contact?.company_name || 'Tanpa perusahaan'}`}
                    back={{ href: '/procurement/surveys', label: 'Kembali ke Survey' }}
                />

                <Card>
                    <InfoGrid cols={2}>
                        <Info label="Kota / Wilayah" value={survey.site_region} />
                        <Info label="Pelaksana" value={survey.delivery_mode_label} />
                        <Info label="Alamat Lokasi" value={survey.site_address} />
                        <Info label="Tagih ke Customer" value={survey.billable ? 'Ya' : 'Tidak'} />
                        <Info label="Diminta oleh" value={survey.requested_by?.name} />
                        <Info label="Rute Berikutnya" value={survey.needs_finance ? 'Finance (invoice / catat biaya)' : 'Langsung Operasional'} />
                        {survey.notes && <div className="sm:col-span-2"><Info label="Catatan Sales" value={survey.notes} /></div>}
                    </InfoGrid>
                </Card>

                {canSource ? (
                    <Card>
                        <form onSubmit={submit} className="space-y-4">
                            <h2 className="font-semibold text-text">{survey.sourced_by ? 'Ubah Vendor & Biaya' : 'Tetapkan Vendor & Biaya'}</h2>
                            <p className="text-sm text-text-muted">Penugasan tim surveyor dilakukan Operasional saat memberi arahan. {survey.sourced_by && 'Masih bisa diubah selama Operasional belum menjadwalkan dan Finance belum menerbitkan invoice.'}</p>
                            {errors.survey && <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{errors.survey}</div>}

                            {isVendorMode && (
                                <div className="text-sm font-medium text-text">Vendor (menyediakan jasa survey) *
                                    <SearchableSelect
                                        value={data.vendor_id}
                                        onChange={(v) => setData('vendor_id', v)}
                                        options={vendors.map((v) => ({ value: v.id, label: `${v.name}${v.city ? ` · ${v.city}` : ''}` }))}
                                        placeholder="— pilih vendor —"
                                        emptyText="Belum ada vendor yang ditandai bisa jasa survey. Aktifkan di Vendor & Katalog Produk."
                                    />
                                    {vendors.length === 0 && <span className="mt-1 block text-xs text-warning">Belum ada vendor dengan opsi "bisa melakukan survey lapangan". Buka Vendor &amp; Katalog Produk → Edit vendor → centang opsinya.</span>}
                                    {errors.vendor_id && <span className="mt-1 block text-xs font-medium text-danger">{errors.vendor_id}</span>}
                                </div>
                            )}

                            <Field label="Biaya Survey (pass-through, tanpa markup)" required error={errors.cost}>
                                <CurrencyInput value={data.cost} onChange={(e) => setData('cost', e.target.value)} className="input" />
                            </Field>

                            <div className="flex justify-end">
                                <Button type="submit" loading={processing}>{survey.sourced_by ? 'Simpan Perubahan' : 'Konfirmasi Vendor & Biaya'}</Button>
                            </div>
                        </form>
                    </Card>
                ) : (
                    <Card>
                        <h2 className="mb-3 font-semibold text-text">Hasil Sourcing</h2>
                        <InfoGrid cols={2}>
                            <Info label="Vendor" value={survey.vendor?.name} />
                            <Info label="Biaya" value={money(survey.cost)} />
                            <Info label="Ditetapkan oleh" value={survey.sourced_by?.name} />
                            <Info
                                label="Tim Surveyor"
                                value={(survey.team && survey.team.length)
                                    ? survey.team.map((t) => `${t.name}${t.is_leader ? ' (leader)' : ''}`).join('\n')
                                    : 'Belum ditugaskan Operasional'}
                            />
                        </InfoGrid>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
