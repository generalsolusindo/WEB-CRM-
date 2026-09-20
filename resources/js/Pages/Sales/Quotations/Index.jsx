import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiFileText } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, FilterSelect, Button, DataTable, StatusBadge, Pagination, EmptyState } from '../../../Components/ui';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0));
}

export default function Index({ quotations, filters, statusOptions, temperatureOptions = [] }) {
    const [form, setForm] = useState(filters);
    const [updatingLeadId, setUpdatingLeadId] = useState(null);

    function submit(e) {
        e?.preventDefault();
        router.get('/sales/quotations', form, { preserveState: true, replace: true });
    }
    function reset() {
        setForm({ search: '', status: '', temperature: '' });
        router.get('/sales/quotations');
    }

    function updateTemperature(event, quotation) {
        event.stopPropagation();
        const temperature = event.target.value;
        setUpdatingLeadId(quotation.lead.id);
        router.patch(`/sales/leads/${quotation.lead.id}/temperature`, { temperature }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setUpdatingLeadId(null),
        });
    }

    const columns = [
        {
            key: 'number', label: 'Nomor',
            render: (q) => (
                <span className="flex items-center gap-2">
                    <span className="font-semibold text-text">{q.number ?? `QT-${String(q.id).padStart(6, '0')} / R${q.revision_number}`}</span>
                    {q.is_addendum && <span className="rounded-full bg-info-soft px-2 py-0.5 text-[10px] font-semibold text-info">Tambahan</span>}
                </span>
            ),
        },
        {
            key: 'customer', label: 'Customer',
            render: (q) => (
                <div>
                    <div className="font-medium text-text">{q.contact.name}</div>
                    <div className="text-xs text-text-muted">{q.contact.company_name || '—'}</div>
                </div>
            ),
        },
        {
            key: 'status', label: 'Status',
            render: (q) => {
                const review = reviewState(q);
                return (
                    <div className="flex flex-col items-start gap-1.5">
                        <StatusBadge status={q.status} />
                        {q.status === 'draft' && <span className={`badge ${review.className}`}>{review.label}</span>}
                    </div>
                );
            },
        },
        {
            key: 'lead_temperature', label: 'Status Lead',
            render: (q) => (
                <select
                    value={q.lead.temperature}
                    onClick={(event) => event.stopPropagation()}
                    onChange={(event) => updateTemperature(event, q)}
                    disabled={updatingLeadId === q.lead.id}
                    aria-label={`Status lead ${q.contact.name}`}
                    className={`min-w-24 rounded-full border px-3 py-1.5 text-xs font-semibold outline-none transition focus:ring-2 focus:ring-primary/20 ${temperatureClass(q.lead.temperature)}`}
                >
                    {temperatureOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
            ),
        },
        {
            key: 'pipeline_stage', label: 'Pipeline Stage',
            render: (q) => (
                <span className={`inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${pipelineClass(q.lead.pipeline_stage)}`}>
                    {q.lead.pipeline_stage_label}
                </span>
            ),
        },
        { key: 'valid_until', label: 'Valid Until', render: (q) => <span className="text-text-muted">{q.valid_until || '—'}</span> },
        { key: 'total', label: 'Total', align: 'right', render: (q) => <span className="font-semibold tabular-nums text-text">{money(q.total_amount)}</span> },
    ];

    return (
        <AppLayout>
            <Head title="Quotations" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader title="Quotations" subtitle="Quotation dibuat dari Procurement Request yang sudah Ready." />

                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={form.search} onChange={(v) => setForm({ ...form, search: v })} placeholder="Cari nomor, customer, perusahaan" />
                        <FilterSelect value={form.status} onChange={(v) => setForm({ ...form, status: v })} className="min-w-36">
                            <option value="">Semua status</option>
                            {statusOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </FilterSelect>
                        <FilterSelect value={form.temperature} onChange={(v) => setForm({ ...form, temperature: v })} className="min-w-36">
                            <option value="">Semua status lead</option>
                            {temperatureOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </FilterSelect>
                        <Button type="submit">Filter</Button>
                        <Button type="button" variant="outline" onClick={reset}>Reset</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={quotations.data}
                    rowHref={(q) => `/sales/quotations/${q.id}`}
                    footer={<Pagination links={quotations.links} />}
                    empty={<EmptyState icon={FiFileText} title="Belum ada quotation" description="Selesaikan Procurement Request hingga status Ready terlebih dahulu." />}
                />
            </div>
        </AppLayout>
    );
}

function temperatureClass(value) {
    if (value === 'hot') return 'border-danger/25 bg-danger-soft text-danger';
    if (value === 'warm') return 'border-warning/25 bg-warning-soft text-warning';
    return 'border-info/25 bg-info-soft text-info';
}

function pipelineClass(value) {
    if (value === 'failed') return 'bg-danger-soft text-danger';
    if (value === 'executed') return 'bg-success-soft text-success';
    if (value === 'deal') return 'bg-primary-soft text-primary-strong';
    return 'bg-warning-soft text-warning';
}

function reviewState(quotation) {
    if (quotation.manager_review_status === 'approved') return { label: 'Disetujui', className: 'badge-success' };
    if (quotation.manager_review_status === 'rejected') return { label: 'Perlu Revisi Manager', className: 'badge-danger' };
    if (quotation.pm_review_status === 'approved') return { label: 'Menunggu Manager', className: 'badge-warning' };
    if (quotation.pm_review_status === 'rejected') return { label: 'Perlu Revisi PM', className: 'badge-danger' };
    return { label: 'Menunggu PM', className: 'badge-warning' };
}
