import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import SearchableSelect from '../../../Components/SearchableSelect';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Show({ survey, canSource, vendors, surveyors }) {
    const isVendorMode = survey.delivery_mode === 'vendor';
    const { data, setData, post, processing, errors } = useForm({
        vendor_id: survey.vendor_id ?? '',
        surveyor_id: survey.surveyor_id ?? '',
        cost: survey.cost ?? '',
    });

    const surveyorChoices = useMemo(() => {
        if (isVendorMode) return surveyors.filter((s) => String(s.vendor_id) === String(data.vendor_id));
        return surveyors.filter((s) => !s.vendor_id);
    }, [isVendorMode, surveyors, data.vendor_id]);

    function submit(e) {
        e.preventDefault();
        post(`/procurement/surveys/${survey.id}/source`);
    }

    return (
        <AppLayout>
            <Head title={survey.code} />
            <div className="mx-auto max-w-3xl space-y-5">
                <div>
                    <Link href="/procurement/surveys" className="text-sm text-info">← Kembali ke Survey</Link>
                    <div className="mt-2 flex items-center gap-3">
                        <h1 className="text-2xl font-bold text-text">{survey.code}</h1>
                        <span className="rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">{survey.status_label}</span>
                    </div>
                    <p className="text-sm text-text-muted">{survey.lead?.contact?.name} · {survey.lead?.contact?.company_name || 'Tanpa perusahaan'}</p>
                </div>

                <section className="grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-2">
                    <Info label="Kota / Wilayah" value={survey.site_region} />
                    <Info label="Pelaksana" value={survey.delivery_mode_label} />
                    <Info label="Alamat Lokasi" value={survey.site_address} />
                    <Info label="Tagih ke Customer" value={survey.billable ? 'Ya' : 'Tidak'} />
                    <Info label="Diminta oleh" value={survey.requested_by?.name} />
                    <Info label="Rute Berikutnya" value={survey.needs_finance ? 'Finance (invoice / catat biaya)' : 'Langsung Operasional'} />
                    {survey.notes && <div className="sm:col-span-2"><Info label="Catatan Sales" value={survey.notes} /></div>}
                </section>

                {canSource ? (
                    <form onSubmit={submit} className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <h2 className="font-semibold text-text">{survey.surveyor_id ? 'Ubah Surveyor & Biaya' : 'Tetapkan Surveyor & Biaya'}</h2>
                        {survey.surveyor_id && <p className="text-sm text-text-muted">Masih bisa diubah selama Operasional belum menjadwalkan dan Finance belum menerbitkan invoice.</p>}
                        {errors.survey && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{errors.survey}</div>}

                        {isVendorMode && (
                            <div className="text-sm font-medium text-text">Vendor (menyediakan jasa survey) *
                                <SearchableSelect
                                    value={data.vendor_id}
                                    onChange={(v) => { setData('vendor_id', v); setData('surveyor_id', ''); }}
                                    options={vendors.map((v) => ({ value: v.id, label: `${v.name}${v.city ? ` · ${v.city}` : ''}` }))}
                                    placeholder="— pilih vendor —"
                                    emptyText="Belum ada vendor yang ditandai bisa jasa survey. Aktifkan di Vendor & Katalog Produk."
                                />
                                {vendors.length === 0 && <span className="mt-1 block text-xs text-warning">Belum ada vendor dengan opsi "bisa melakukan survey lapangan". Buka Vendor &amp; Katalog Produk → Edit vendor → centang opsinya.</span>}
                                {errors.vendor_id && <span className="mt-1 block text-xs text-danger">{errors.vendor_id}</span>}
                            </div>
                        )}

                        <div className="text-sm font-medium text-text">Surveyor *
                            <SearchableSelect
                                value={data.surveyor_id}
                                onChange={(v) => setData('surveyor_id', v)}
                                options={surveyorChoices.map((s) => ({ value: s.id, label: `${s.name}${s.phone ? ` · ${s.phone}` : ''}` }))}
                                placeholder="— pilih surveyor —"
                                disabled={isVendorMode && !data.vendor_id}
                                emptyText="Belum ada akun surveyor untuk kategori ini."
                            />
                            {isVendorMode && !data.vendor_id && <span className="mt-1 block text-xs text-text-muted">Pilih vendor dulu.</span>}
                            {surveyorChoices.length === 0 && (!isVendorMode || data.vendor_id) && <span className="mt-1 block text-xs text-warning">Belum ada akun surveyor untuk kategori ini. Buat di menu Surveyor &amp; Teknisi.</span>}
                            {errors.surveyor_id && <span className="mt-1 block text-xs text-danger">{errors.surveyor_id}</span>}
                        </div>

                        <label className="block text-sm font-medium text-text">Biaya Survey (pass-through, tanpa markup) *
                            <input type="number" min="0" step="0.01" value={data.cost} onChange={(e) => setData('cost', e.target.value)} className="input" />
                            {errors.cost && <span className="mt-1 block text-xs text-danger">{errors.cost}</span>}
                        </label>

                        <div className="flex justify-end">
                            <button disabled={processing} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                                {processing ? 'Menyimpan...' : (survey.surveyor_id ? 'Simpan Perubahan' : 'Konfirmasi Surveyor')}
                            </button>
                        </div>
                    </form>
                ) : (
                    <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <h2 className="font-semibold text-text">Hasil Sourcing</h2>
                        <div className="mt-3 grid gap-4 sm:grid-cols-2">
                            <Info label="Surveyor" value={survey.surveyor?.name} />
                            <Info label="Vendor" value={survey.vendor?.name} />
                            <Info label="Biaya" value={money(survey.cost)} />
                            <Info label="Ditetapkan oleh" value={survey.sourced_by?.name} />
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

function Info({ label, value }) {
    return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>;
}
