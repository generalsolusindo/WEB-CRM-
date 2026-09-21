import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiFileText } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, PillTabs, Toolbar, FilterSelect, DataTable, StatusBadge, Pagination, EmptyState, Button, ConfirmDialog } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Index({ needsInvoice, readyForFinal = [], invoices, filters, phaseOptions, statusOptions }) {
    const needsCount = needsInvoice.length + readyForFinal.length;
    const [tab, setTab] = useState(needsCount > 0 ? 'needs' : 'all');
    const [form, setForm] = useState(filters);
    const [finalTarget, setFinalTarget] = useState(null);
    const [creatingFinal, setCreatingFinal] = useState(false);

    function applyFilter(next) {
        const merged = { ...form, ...next };
        setForm(merged);
        router.get('/finance/invoices', merged, { preserveState: true, replace: true });
    }
    function createFinal() {
        if (!finalTarget) return;
        setCreatingFinal(true);
        feedback.expect({ success: { title: 'Invoice pelunasan dibuat', style: 'popup' } });
        router.post(`/finance/sales-orders/${finalTarget.id}/final-invoice`, {}, {
            onSuccess: () => setFinalTarget(null),
            onFinish: () => setCreatingFinal(false),
        });
    }

    const finalCols = [
        { key: 'number', label: 'Sales Order', render: (so) => <span className="font-semibold text-text">{so.number}</span> },
        { key: 'customer', label: 'Customer', render: (so) => <span className="text-text-muted">{so.customer}</span> },
        { key: 'act', label: '', align: 'right', render: (so) => <Button size="sm" onClick={() => setFinalTarget(so)}>Buat Final Invoice</Button> },
    ];
    const needsCols = [
        { key: 'number', label: 'Sales Order', render: (so) => <span className="font-semibold text-text">{so.number}</span> },
        { key: 'customer', label: 'Customer', render: (so) => <span className="text-text-muted">{so.customer}</span> },
        { key: 'order_type', hideBelow: 'md', label: 'Tipe', render: (so) => <span className="text-text-muted capitalize">{String(so.order_type).replace('_', ' ')}</span> },
        { key: 'total', label: 'Nilai SO', align: 'right', render: (so) => <span className="tabular-nums text-text-muted">{money(so.total)}</span> },
        { key: 'act', label: '', align: 'right', render: (so) => <Button size="sm" variant="outline" href={`/finance/sales-orders/${so.id}/invoices/create`}>Buat Invoice</Button> },
    ];
    const allCols = [
        {
            key: 'number', label: 'Nomor',
            render: (inv) => (
                <div>
                    <div className="font-semibold text-text">{inv.number}</div>
                    <div className="text-xs text-text-muted">{inv.sales_order.number}</div>
                </div>
            ),
        },
        { key: 'customer', label: 'Customer', render: (inv) => <span className="text-text-muted">{inv.sales_order.contact?.name ?? '—'}</span> },
        { key: 'phase', hideBelow: 'md', label: 'Fase', render: (inv) => <span className="uppercase text-text-muted">{inv.invoice_phase}</span> },
        {
            key: 'status', label: 'Status',
            render: (inv) => <StatusBadge status={inv.status} label={statusOptions.find((s) => s.value === inv.status)?.label} />,
        },
        { key: 'total', label: 'Total', align: 'right', render: (inv) => <span className="tabular-nums text-text-muted">{money(Number(inv.amount) + Number(inv.tax_amount))}</span> },
        { key: 'paid', label: 'Terbayar', align: 'right', render: (inv) => <span className="tabular-nums text-text-muted">{money(inv.total_paid)}</span> },
    ];

    return (
        <AppLayout>
            <Head title="Invoice" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader title="Invoice" subtitle="Tagihan Sales Order — DP, pelunasan, dan pembayaran penuh." />

                <PillTabs
                    value={tab}
                    onChange={setTab}
                    tabs={[
                        { value: 'needs', label: 'Perlu Invoice', count: needsCount },
                        { value: 'all', label: 'Semua Invoice' },
                    ]}
                />

                {tab === 'needs' ? (
                    <div className="space-y-5">
                        {readyForFinal.length > 0 && (
                            <DataTable title="SO Siap Invoice Pelunasan (Final)" columns={finalCols} rows={readyForFinal} rowKey="id" />
                        )}
                        <DataTable
                            title="Sales Order Perlu Invoice Muka"
                            columns={needsCols}
                            rows={needsInvoice}
                            rowKey="id"
                            empty={<EmptyState icon={FiFileText} title="Semua Sales Order sudah punya invoice muka" />}
                        />
                    </div>
                ) : (
                    <div className="space-y-4">
                        <Toolbar>
                            <FilterSelect value={form.phase} onChange={(v) => applyFilter({ phase: v })} className="min-w-36">
                                <option value="">Semua fase</option>
                                {phaseOptions.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
                            </FilterSelect>
                            <FilterSelect value={form.status} onChange={(v) => applyFilter({ status: v })} className="min-w-36">
                                <option value="">Semua status</option>
                                {statusOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                            </FilterSelect>
                        </Toolbar>
                        <DataTable
                            columns={allCols}
                            rows={invoices.data}
                            rowHref={(inv) => `/finance/invoices/${inv.id}`}
                            footer={<Pagination links={invoices.links} />}
                            empty={<EmptyState icon={FiFileText} title="Belum ada invoice" />}
                        />
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={finalTarget !== null}
                onClose={() => setFinalTarget(null)}
                onConfirm={createFinal}
                title="Buat invoice pelunasan?"
                description={`Invoice pelunasan final akan dibuat untuk Sales Order ${finalTarget?.number ?? ''}.`}
                tone="warning"
                confirmLabel="Buat Final Invoice"
                processing={creatingFinal}
            />
        </AppLayout>
    );
}
