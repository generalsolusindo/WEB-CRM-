import { Head, useForm } from '@inertiajs/react';
import { FiFileText } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, CardHeader, Button, Info, InfoGrid } from '../../../Components/ui';
import TableScroll from '../../../Components/ui/TableScroll';

export default function Show({ deliveryNote: dn, canUploadReceivedProof }) {
    const complete = dn.lines.every((l) => l.qty_balance <= 0);
    const isEkspedisi = dn.delivery_method === 'ekspedisi';

    return (
        <AppLayout>
            <Head title={dn.number} />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title={(
                        <span className="flex flex-wrap items-center gap-3">
                            {dn.number}
                            <span className={`badge ${dn.status === 'received' ? 'badge-success' : 'badge-warning'}`}>{dn.status === 'received' ? 'Diterima' : 'Terkirim'}</span>
                            <span className={`badge ${complete ? 'badge-success' : 'badge-primary'}`}>{complete ? 'Material Lengkap' : 'Sebagian'}</span>
                        </span>
                    )}
                    subtitle={`${dn.sales_order.number} · ${dn.sales_order.customer}`}
                    back={{ href: `/operational/sales-orders/${dn.sales_order.id}/delivery-notes`, label: 'Kembali' }}
                    actions={<Button href={`/operational/delivery-notes/${dn.id}/pdf`} external variant="outline" icon={FiFileText}>Lihat / Cetak PDF</Button>}
                />

                <Card>
                    <InfoGrid cols={2}>
                        <Info label="Metode Pengiriman" value={isEkspedisi ? 'Ekspedisi' : 'Diantar Sendiri'} />
                        <Info label="Alamat Pengiriman" value={dn.delivery_address} />
                        <Info label={isEkspedisi ? 'Nama Ekspedisi' : 'Nama Pengantar'} value={dn.shipper_name} />
                        {isEkspedisi && <Info label="Nomor Resi" value={dn.tracking_number} />}
                        <Info label="Nomor PO" value={dn.sales_order.po_number} />
                        <Info label="Invoice Terkait" value={dn.invoice_number} />
                        <Info label="Dibuat oleh" value={dn.created_by} />
                        <Info label="Diterima oleh (Teknisi)" value={dn.received_by ? `${dn.received_by} · ${new Date(dn.received_at).toLocaleString('id-ID')}` : 'Belum dikonfirmasi'} />
                    </InfoGrid>
                </Card>

                <Card>
                    <CardHeader title="Bukti Pengiriman" subtitle="Bukti serah wajib ada sejak dibuat; bukti diterima customer sifatnya tambahan, bisa menyusul kapan saja." />
                    <div className="mt-3 grid gap-4 sm:grid-cols-2">
                        <ProofBox
                            label={isEkspedisi ? 'Bukti Serah ke Kurir' : 'Bukti Barang Dibawa'}
                            url={dn.dispatch_proof_url}
                        />
                        <div>
                            <ProofBox label="Bukti Diterima Customer (opsional)" url={dn.received_proof_url} />
                            {canUploadReceivedProof && !dn.received_proof_url && <ReceivedProofUpload deliveryNoteId={dn.id} />}
                        </div>
                    </div>
                </Card>

                <Card padded={false}>
                    <CardHeader title="Barang" />
                    <TableScroll>
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border bg-surface-2 text-[11px] font-bold uppercase tracking-wider text-text-faint">
                                    <th className="px-4 py-3">Item</th><th className="px-4 py-3">Unit</th><th className="px-4 py-3 text-right">Ordered</th><th className="px-4 py-3 text-right">Previous Balance</th><th className="px-4 py-3 text-right">Delivered</th><th className="px-4 py-3 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {dn.lines.map((l, i) => (
                                    <tr key={i}>
                                        <td className="px-4 py-3.5 font-medium text-text">{l.item_name}</td>
                                        <td className="px-4 py-3.5 text-text-muted">{l.unit}</td>
                                        <td className="px-4 py-3.5 text-right tabular-nums text-text-muted">{l.qty_ordered}</td>
                                        <td className="px-4 py-3.5 text-right tabular-nums text-text-muted">{l.qty_previous_balance}</td>
                                        <td className="px-4 py-3.5 text-right font-medium tabular-nums text-text">{l.qty_delivered}</td>
                                        <td className={`px-4 py-3.5 text-right tabular-nums ${l.qty_balance > 0 ? 'text-warning' : 'text-success'}`}>{l.qty_balance}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </TableScroll>
                </Card>
            </div>
        </AppLayout>
    );
}

function ProofBox({ label, url }) {
    return (
        <div>
            <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">{label}</div>
            {url ? (
                <a href={url} target="_blank" rel="noreferrer" className="mt-2 block overflow-hidden rounded-lg border border-border">
                    <img src={url} alt={label} className="h-40 w-full object-cover" />
                </a>
            ) : (
                <div className="mt-2 flex h-40 items-center justify-center rounded-lg border border-dashed border-border text-xs text-text-muted">
                    Belum ada
                </div>
            )}
        </div>
    );
}

function ReceivedProofUpload({ deliveryNoteId }) {
    const { data, setData, post, processing, errors, reset } = useForm({ proof: null });

    function submit(e) {
        e.preventDefault();
        post(`/operational/delivery-notes/${deliveryNoteId}/received-proof`, {
            forceFormData: true, preserveScroll: true, onSuccess: () => reset(),
        });
    }

    return (
        <form onSubmit={submit} className="mt-2 space-y-2">
            <input
                type="file" accept=".jpg,.jpeg,.png"
                onChange={(e) => setData('proof', e.target.files[0] ?? null)}
                className="block w-full text-xs text-text-muted file:mr-2 file:rounded-lg file:border-0 file:bg-primary-soft file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-primary-strong"
            />
            {errors.proof && <span className="block text-xs text-danger">{errors.proof}</span>}
            <Button type="submit" variant="outline" size="sm" loading={processing} disabled={!data.proof}>Upload Bukti Diterima</Button>
        </form>
    );
}
