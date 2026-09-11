import { Head } from '@inertiajs/react';
import { FiFileText } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, CardHeader, Button, Info, InfoGrid } from '../../../Components/ui';

export default function Show({ deliveryNote: dn }) {
    const complete = dn.lines.every((l) => l.qty_balance <= 0);

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
                        <Info label="Alamat Pengiriman" value={dn.delivery_address} />
                        <Info label="Nomor PO" value={dn.sales_order.po_number} />
                        <Info label="Invoice Terkait" value={dn.invoice_number} />
                        <Info label="Dibuat oleh" value={dn.created_by} />
                        <Info label="Diterima oleh (Teknisi)" value={dn.received_by ? `${dn.received_by} · ${new Date(dn.received_at).toLocaleString('id-ID')}` : 'Belum dikonfirmasi'} />
                    </InfoGrid>
                </Card>

                <Card padded={false}>
                    <CardHeader title="Barang" />
                    <div className="overflow-x-auto">
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
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
