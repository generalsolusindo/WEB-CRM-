import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import Pagination from '../../../Components/Pagination';

function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v || 0));
}

export default function Index({ payments }) {
    return (
        <AppLayout>
            <Head title="Pembayaran" />
            <div className="mx-auto max-w-6xl space-y-5">
                <div>
                    <h1 className="text-2xl font-bold text-text">Pembayaran</h1>
                    <p className="text-sm text-text-muted">Riwayat seluruh pembayaran. Pencatatan dilakukan dari halaman Invoice.</p>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-bg text-text-muted">
                                <tr>
                                    <th className="px-4 py-3">Tanggal</th>
                                    <th className="px-4 py-3">Invoice</th>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3 text-right">Jumlah</th>
                                    <th className="px-4 py-3">Bukti</th>
                                    <th className="px-4 py-3">Dicatat oleh</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {payments.data.map((p) => (
                                    <tr key={p.id} className="hover:bg-bg/70">
                                        <td className="px-4 py-3 text-text-muted">{p.paid_at?.slice(0, 16).replace('T', ' ')}</td>
                                        <td className="px-4 py-3 font-medium text-text">
                                            {p.invoice.number}
                                            <div className="text-xs font-normal uppercase text-text-muted">{p.invoice.invoice_phase} · {p.invoice.sales_order.number}</div>
                                        </td>
                                        <td className="px-4 py-3 text-text-muted">{p.invoice.sales_order.contact.name}</td>
                                        <td className="px-4 py-3 text-right font-medium text-text">{money(p.amount_paid)}</td>
                                        <td className="px-4 py-3">
                                            {p.proof_count > 0
                                                ? <span className="rounded-full bg-success/10 px-2.5 py-1 text-xs font-semibold text-success">{p.proof_count} bukti</span>
                                                : <span className="rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning">Belum ada</span>}
                                        </td>
                                        <td className="px-4 py-3 text-text-muted">{p.recorder?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Link href={`/finance/invoices/${p.invoice.id}`} className="font-medium text-info hover:underline">Lihat Invoice</Link>
                                        </td>
                                    </tr>
                                ))}
                                {payments.data.length === 0 && (
                                    <tr><td colSpan="7" className="px-4 py-12 text-center text-text-muted">Belum ada pembayaran tercatat.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-border p-4"><Pagination links={payments.links} /></div>
                </div>
            </div>
        </AppLayout>
    );
}
