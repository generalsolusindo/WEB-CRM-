import { Head, Link, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { FiPlus, FiTrash2 } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { Button, PageHeader } from '../../../Components/ui';

function lineData(line, key) {
    return {
        _key: key,
        procurement_request_line_id: line?.id ?? null,
        item_name: line?.item_name ?? '',
        description: line?.description ?? '',
        qty: line?.qty != null ? String(line.qty) : '1',
        unit: line?.unit ?? '',
        category: line?.category ?? 'material',
    };
}

export default function ScopeRevision({ quotation, unitOptions = [] }) {
    const counter = useRef(0);
    const { data, setData, put, processing, errors } = useForm({
        lines: quotation.procurement_request.lines.map((line) => lineData(line, `line-${line.id}`)),
    });

    function setLine(index, patch) {
        setData('lines', data.lines.map((line, position) => position === index ? { ...line, ...patch } : line));
    }

    function addLine() {
        counter.current += 1;
        setData('lines', [...data.lines, lineData(null, `new-${counter.current}`)]);
    }

    function removeLine(index) {
        if (data.lines.length === 1) return;
        setData('lines', data.lines.filter((_, position) => position !== index));
    }

    function submit(event) {
        event.preventDefault();
        put(`/sales/quotations/${quotation.id}/scope-revision`);
    }

    return (
        <AppLayout>
            <Head title={`Revisi Kebutuhan ${quotation.number}`} />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title="Revisi Kebutuhan"
                    subtitle={`${quotation.number} · ${quotation.contact.name} · ${quotation.contact.company_name || 'Tanpa perusahaan'}`}
                    back={{ href: `/sales/quotations/${quotation.id}`, label: 'Kembali ke quotation' }}
                />

                <div className="rounded-xl border border-warning/25 bg-warning-soft px-4 py-3 text-sm text-warning">
                    Isi kebutuhan customer saja. Procurement akan menentukan vendor atau stok, harga beli, pajak, dan ketersediaannya. Quotation tetap menggunakan data yang sama setelah costing selesai.
                </div>

                {errors.quotation && <Alert text={errors.quotation} />}
                {errors.lines && <Alert text={errors.lines} />}

                <form onSubmit={submit} className="space-y-4">
                    {data.lines.map((line, index) => (
                        <section key={line._key} className="card p-5">
                            <div className="mb-4 flex items-center justify-between gap-3">
                                <h2 className="font-semibold text-text">Kebutuhan {index + 1}</h2>
                                <button
                                    type="button"
                                    onClick={() => removeLine(index)}
                                    disabled={data.lines.length === 1}
                                    className="rounded-lg p-2 text-danger hover:bg-danger-soft disabled:cursor-not-allowed disabled:text-text-faint"
                                    aria-label={`Hapus kebutuhan ${index + 1}`}
                                >
                                    <FiTrash2 className="h-4 w-4" />
                                </button>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Nama item atau jasa" error={errors[`lines.${index}.item_name`]} className="sm:col-span-2">
                                    <input value={line.item_name} onChange={(event) => setLine(index, { item_name: event.target.value })} className="input" />
                                </Field>
                                <Field label="Deskripsi" error={errors[`lines.${index}.description`]} className="sm:col-span-2">
                                    <textarea rows="3" value={line.description} onChange={(event) => setLine(index, { description: event.target.value })} className="input" />
                                </Field>
                                <Field label="Jumlah" error={errors[`lines.${index}.qty`]}>
                                    <input type="number" min="0.01" step="0.01" value={line.qty} onChange={(event) => setLine(index, { qty: event.target.value })} className="input" />
                                </Field>
                                <Field label="Unit" error={errors[`lines.${index}.unit`]}>
                                    <select value={line.unit} onChange={(event) => setLine(index, { unit: event.target.value })} className="input">
                                        <option value="">Pilih unit</option>
                                        {unitOptions.map((unit) => <option key={unit} value={unit}>{unit}</option>)}
                                        {line.unit && !unitOptions.includes(line.unit) && <option value={line.unit}>{line.unit} (lama)</option>}
                                    </select>
                                </Field>
                                <Field label="Kategori" error={errors[`lines.${index}.category`]} className="sm:col-span-2">
                                    <select value={line.category} onChange={(event) => setLine(index, { category: event.target.value })} className="input">
                                        <option value="material">Material</option>
                                        <option value="service">Jasa</option>
                                        <option value="reimburse">Biaya reimburse</option>
                                    </select>
                                </Field>
                            </div>
                        </section>
                    ))}

                    <Button type="button" variant="outline" icon={FiPlus} onClick={addLine}>Tambah Kebutuhan</Button>

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <Link href={`/sales/quotations/${quotation.id}`} className="btn btn-outline">Batal</Link>
                        <Button type="submit" loading={processing}>Kirim ke Procurement</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, className = '', children }) {
    return (
        <label className={`text-sm font-medium text-text ${className}`}>
            {label}
            <div className="mt-1">{children}</div>
            {error && <span className="mt-1 block text-xs text-danger">{error}</span>}
        </label>
    );
}

function Alert({ text }) {
    return <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{text}</div>;
}
