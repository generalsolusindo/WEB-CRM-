import { Head, Link, router, useForm } from '@inertiajs/react';
import { FiAward } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { Totals } from '../Quotations/Show';
import CategoryBadge from '../../../Components/CategoryBadge';
import { pickFile } from '../../../utils/fileValidation';
import { PageHeader, Button, StatusBadge } from '../../../Components/ui';

export default function Show({ salesOrder, totals, approvalDocs = [], canManageDocs = false, orderTypeLabel, paymentRuleLabel, requiredSettlementPhase, canCloseAsWon }) {
    const docForm = useForm({ signed_quotation: null, purchase_order: null, po_number: salesOrder.po_number ?? '', po_date: salesOrder.po_date ?? '' });
    function saveDocs(e) {
        e.preventDefault();
        docForm.post(`/sales/sales-orders/${salesOrder.id}/documents`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => docForm.setData({ signed_quotation: null, purchase_order: null, po_number: docForm.data.po_number, po_date: docForm.data.po_date }),
        });
    }
    const number = salesOrder.number ?? `SO-${String(salesOrder.id).padStart(6, '0')}`;
    const completed = salesOrder.status === 'completed';

    function closeAsWon() {
        if (confirm('Tutup transaksi ini sebagai Won? Status Lead akan berubah menjadi Won.')) {
            router.post(`/sales/sales-orders/${salesOrder.id}/close-won`);
        }
    }

    return <AppLayout><Head title={number} /><div className="mx-auto max-w-6xl space-y-5">
        <PageHeader
            title={<span className="flex items-center gap-3">{number} <StatusBadge status={salesOrder.status} label={label(salesOrder.status)} /></span>}
            subtitle={`${salesOrder.contact.name} · ${salesOrder.contact.company_name || 'Tanpa perusahaan'}`}
            back={{ href: '/sales/sales-orders', label: 'Kembali ke Sales Orders' }}
            actions={canCloseAsWon && <Button onClick={closeAsWon} icon={FiAward}>Tandai Won</Button>}
        />

        <section className={`rounded-xl border p-5 ${completed ? 'border-success/30 bg-success/5' : canCloseAsWon ? 'border-success/30 bg-success/5' : 'border-warning/30 bg-warning/5'}`}><h2 className="font-semibold text-text">Status Penyelesaian Deal</h2><p className="mt-1 text-sm text-text-muted">{completed ? 'Deal sudah ditutup sebagai Won dan status Lead sudah diperbarui.' : canCloseAsWon ? 'Pembayaran wajib dan bukti pembayaran sudah lengkap. Deal siap ditutup sebagai Won.' : `Menunggu invoice fase ${phaseLabel(requiredSettlementPhase)} berstatus Paid dan memiliki bukti pembayaran.`}</p></section>

        <section className="card grid gap-5 p-6 sm:grid-cols-3"><Info label="Customer" value={salesOrder.contact.name} /><Info label="Perusahaan" value={salesOrder.contact.company_name} /><Info label="Email / Telepon" value={[salesOrder.contact.email, salesOrder.contact.phone].filter(Boolean).join(' · ')} /><Info label="Tipe Order" value={orderTypeLabel} /><Info label="Aturan Pembayaran" value={paymentRuleLabel} /><Info label="Dikonfirmasi" value={dateTime(salesOrder.confirmed_at)} /><Info label="Quotation" value={salesOrder.quotation.number ?? `QT-${String(salesOrder.quotation_id).padStart(6, '0')} / R${salesOrder.quotation.revision_number}`} /><Info label="Lead Stage" value={label(salesOrder.quotation.lead.stage)} /><Info label="NPWP" value={salesOrder.contact.npwp} /></section>

        <section className="card p-6">
            <h2 className="font-semibold text-text">Dokumen Persetujuan Customer</h2>
            <div className="mt-3 grid gap-4 sm:grid-cols-2">
                <Info label="Nomor PO" value={salesOrder.po_number} />
                <Info label="Tanggal PO" value={salesOrder.po_date} />
                <div>
                    <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">Lampiran</div>
                    <div className="mt-1 flex flex-wrap gap-2">
                        {approvalDocs.length === 0 && <span className="text-sm text-text">—</span>}
                        {approvalDocs.map((doc) => <a key={doc.category} href={doc.url} target="_blank" rel="noreferrer" className="rounded-lg border border-info/30 px-3 py-1.5 text-xs font-semibold text-info">{doc.label}</a>)}
                    </div>
                </div>
            </div>

            {canManageDocs && (
                <form onSubmit={saveDocs} className="mt-5 space-y-4 rounded-xl border border-border bg-surface-2 p-4">
                    <h3 className="text-sm font-medium text-text">Tambah / Ganti Dokumen</h3>
                    <p className="text-xs text-text-muted">Upload file baru untuk mengganti yang lama pada kategori yang sama. Kosongkan bila tidak diubah.</p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-text">Dokumen Quotation (TTD &amp; stempel)
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFile(docForm, 'signed_quotation', e.target.files[0], 1)} className="mt-1 block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong" />
                            <span className="mt-1 block text-xs text-text-muted">maks 1 MB</span>
                            {docForm.errors.signed_quotation && <span className="mt-1 block text-xs text-danger">{docForm.errors.signed_quotation}</span>}
                        </label>
                        <label className="block text-sm font-medium text-text">Purchase Order Customer
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFile(docForm, 'purchase_order', e.target.files[0], 1)} className="mt-1 block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong" />
                            <span className="mt-1 block text-xs text-text-muted">maks 1 MB</span>
                            {docForm.errors.purchase_order && <span className="mt-1 block text-xs text-danger">{docForm.errors.purchase_order}</span>}
                        </label>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-text">Nomor PO
                            <input value={docForm.data.po_number} onChange={(e) => docForm.setData('po_number', e.target.value)} className="input" placeholder="mis. PO/2026/00123" />
                            {docForm.errors.po_number && <span className="mt-1 block text-xs text-danger">{docForm.errors.po_number}</span>}
                        </label>
                        <label className="block text-sm font-medium text-text">Tanggal PO
                            <input type="date" value={docForm.data.po_date} onChange={(e) => docForm.setData('po_date', e.target.value)} className="input" />
                            {docForm.errors.po_date && <span className="mt-1 block text-xs text-danger">{docForm.errors.po_date}</span>}
                        </label>
                    </div>
                    <div className="flex justify-end">
                        <button disabled={docForm.processing} className="btn btn-primary">{docForm.processing ? 'Menyimpan...' : 'Simpan Dokumen'}</button>
                    </div>
                </form>
            )}
        </section>

        <section className="card overflow-hidden p-0"><div className="border-b border-border px-5 py-4"><h2 className="font-semibold text-text">Item Sales Order</h2></div><div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint"><th className="px-4 py-3">Item</th><th className="px-4 py-3">Qty</th><th className="px-4 py-3 text-right">Harga Jual</th><th className="px-4 py-3 text-right">Diskon</th><th className="px-4 py-3">Pajak</th><th className="px-4 py-3 text-right">DPP</th></tr></thead><tbody className="divide-y divide-border">{salesOrder.lines.map((line) => <tr key={line.id}><td className="px-4 py-3"><div className="font-medium text-text">{line.item_name}</div><div className="whitespace-pre-line text-xs text-text-muted">{line.description || '—'}<CategoryBadge category={line.category} /></div>{line.sourcing_note && <div className="mt-0.5 text-[11px] italic text-text-muted">Opsi: {line.sourcing_note}</div>}</td><td className="px-4 py-3 text-text-muted">{line.qty} {line.unit}</td><td className="px-4 py-3 text-right">{money(line.selling_price)}</td><td className="px-4 py-3 text-right text-text-muted">{Number(line.discount_amount) > 0 ? `${money(line.discount_amount)} (${line.discount_percent ?? 0}%)` : '—'}</td><td className="px-4 py-3 text-text-muted">{line.tax ? line.tax.name : (Number(line.tax_rate) > 0 ? `${line.tax_rate}%` : '—')}</td><td className="px-4 py-3 text-right font-medium">{money(line.subtotal)}</td></tr>)}</tbody><Totals totals={totals} span={5} /></table></div></section>

        <section className="space-y-4"><div><h2 className="text-lg font-semibold text-text">Monitoring Finance</h2><p className="text-sm text-text-muted">Informasi invoice, pembayaran, dan bukti pembayaran bersifat read-only untuk Sales.</p></div>{salesOrder.invoices.length === 0 ? <div className="card p-8 text-center text-sm text-text-muted">Finance belum membuat invoice untuk Sales Order ini.</div> : (() => { const billed = salesOrder.invoices.filter((i) => i.status !== 'cancelled').reduce((s, i) => s + Number(i.amount) + Number(i.tax_amount), 0); const paid = salesOrder.invoices.reduce((s, i) => s + Number(i.total_paid || 0), 0); return <div className="card grid gap-4 p-5 sm:grid-cols-3"><Info label="Total Tertagih" value={money(billed)} /><Info label="Total Terbayar" value={money(paid)} /><Info label="Sisa" value={money(billed - paid)} /></div>; })()}{salesOrder.invoices.map((invoice) => <article key={invoice.id} className="card overflow-hidden p-0"><div className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-surface-2 px-5 py-4"><div><div className="font-semibold text-text">{invoice.number ?? `INV-${String(invoice.id).padStart(6, '0')}`} · {phaseLabel(invoice.invoice_phase)}</div><div className="text-xs text-text-muted">Jatuh tempo: {date(invoice.due_date)}</div></div><StatusBadge status={invoice.status} /></div><div className="grid gap-4 border-b border-border p-5 sm:grid-cols-3"><Info label="Nilai Invoice" value={money(invoice.amount)} /><Info label="Pajak" value={money(invoice.tax_amount)} /><Info label="Total Dibayar" value={money(invoice.total_paid)} /></div><div className="p-5"><h3 className="mb-3 text-sm font-semibold text-text">Riwayat Pembayaran</h3>{invoice.payments.length === 0 ? <p className="text-sm text-text-muted">Belum ada pembayaran tercatat.</p> : <div className="space-y-3">{invoice.payments.map((payment) => { const proofs = payment.attachments.filter((attachment) => attachment.category === 'payment_proof'); return <div key={payment.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-3"><div><div className="font-medium text-text">{money(payment.amount_paid)}</div><div className="text-xs text-text-muted">{dateTime(payment.paid_at)}{payment.notes ? ` · ${payment.notes}` : ''}</div></div><div className="flex flex-wrap gap-2">{proofs.length === 0 ? <span className="badge badge-warning">Bukti belum ada</span> : proofs.map((proof, index) => <a key={proof.id} href={proof.url} target="_blank" rel="noreferrer" className="rounded-lg border border-primary/30 px-3 py-1.5 text-xs font-semibold text-primary">Lihat Bukti {proofs.length > 1 ? index + 1 : ''}</a>)}</div></div>; })}</div>}</div></article>)}</section>
    </div></AppLayout>;
}

function Info({ label: title, value }) { return <div><div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">{title}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>; }
function label(value) { return String(value || '—').replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase()); }
function phaseLabel(value) { return value === 'dp' ? 'DP' : value === 'full' ? 'Pelunasan 100%' : value === 'final' ? 'Pelunasan Akhir' : label(value); }
function date(value) { if (!value) return '—'; const d = new Date(String(value).length <= 10 ? `${value}T00:00:00` : value); return Number.isNaN(d.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(d); }
function dateTime(value) { return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—'; }
function money(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0)); }
