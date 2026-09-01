import { Link } from '@inertiajs/react';

const statusLabels = { draft: 'Draft', submitted: 'Baru Masuk', searching: 'Sedang Dicari', ready: 'Ready', rejected: 'Ditolak' };
const availabilityLabels = { available: 'Tersedia', unavailable: 'Tidak Tersedia', searching: 'Masih Dicari' };

export default function ProcurementStatusPanel({ request }) {
    if (!request) return null;
    const ready = request.status === 'ready';
    const rejected = request.status === 'rejected';

    return (
        <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div><h2 className="font-semibold text-text">Pre-Sales Procurement</h2><p className="text-sm text-text-muted">Request #{request.id} · hasil Procurement bersifat read-only untuk Sales.</p></div>
                <span className={`rounded-full px-3 py-1 text-sm font-semibold ${ready ? 'bg-success/10 text-success' : rejected ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-warning'}`}>{statusLabels[request.status] ?? request.status}</span>
            </div>
            {rejected && (
                <div className="mb-4 rounded-lg bg-danger/10 p-4 text-sm text-danger">
                    <strong>Ditolak Procurement:</strong> {request.rejection_reason}
                    <div className="mt-1 text-danger/80">Perbaiki requirement di atas, lalu kirim ulang ke Procurement.</div>
                </div>
            )}
            <div className="overflow-x-auto rounded-lg border border-border"><table className="w-full text-left text-sm"><thead className="bg-bg text-text-muted"><tr><th className="px-3 py-2">Item</th><th className="px-3 py-2">Qty</th><th className="px-3 py-2">Availability</th><th className="px-3 py-2 text-right">Cost Price</th></tr></thead><tbody className="divide-y divide-border">{request.lines.map((line) => <tr key={line.id}><td className="px-3 py-3 font-medium text-text">{line.item_name}</td><td className="px-3 py-3 text-text-muted">{line.qty} {line.unit}</td><td className="px-3 py-3 text-text-muted">{availabilityLabels[line.availability_status] ?? line.availability_status}</td><td className="px-3 py-3 text-right text-text-muted">{ready ? formatCurrency(line.cost_price) : 'Menunggu Procurement'}</td></tr>)}</tbody></table></div>
            <div className={`mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg p-4 text-sm ${ready ? 'bg-success/10 text-success' : 'bg-info/10 text-info'}`}>
                <span>{ready ? 'Procurement sudah Ready. Data dapat dilanjutkan menjadi Quotation.' : 'Procurement sedang memproses sourcing, availability, dan cost price.'}</span>
                {ready && (request.quotation
                    ? <Link href={`/sales/quotations/${request.quotation.id}`} className="rounded-lg bg-surface px-4 py-2 font-semibold text-text shadow-sm">Lihat Quotation R{request.quotation.revision_number}</Link>
                    : <Link href={`/sales/procurement-requests/${request.id}/quotations/create`} className="rounded-lg bg-navy px-4 py-2 font-semibold text-white">Buat Quotation</Link>)}
            </div>
        </section>
    );
}
function formatCurrency(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(Number(value)); }
