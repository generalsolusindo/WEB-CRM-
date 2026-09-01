import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

export default function Index({ taxes }) {
    function destroy(tax) {
        if (confirm(`Hapus pajak "${tax.name}"?`)) {
            router.delete(`/admin/taxes/${tax.id}`);
        }
    }

    return (
        <AppLayout>
            <Head title="Master Pajak" />
            <div className="mx-auto max-w-4xl space-y-5">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-text">Master Pajak</h1>
                        <p className="text-sm text-text-muted">
                            Konfigurasi tarif pajak (PPN dll). Dipakai per baris di Quotation dan Sales Order.
                        </p>
                    </div>
                    <Link href="/admin/taxes/create" className="rounded-lg bg-navy px-4 py-2 text-center text-sm font-semibold text-white hover:bg-navy-light">
                        Tambah Pajak
                    </Link>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-bg text-text-muted">
                            <tr>
                                <th className="px-4 py-3">Nama</th>
                                <th className="px-4 py-3 text-right">Tarif</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {taxes.map((tax) => (
                                <tr key={tax.id} className="hover:bg-bg/70">
                                    <td className="px-4 py-3 font-medium text-text">{tax.name}</td>
                                    <td className="px-4 py-3 text-right text-text-muted">{Number(tax.rate)}%</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${tax.is_active ? 'bg-success/10 text-success' : 'bg-text-muted/10 text-text-muted'}`}>
                                            {tax.is_active ? 'Aktif' : 'Nonaktif'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={`/admin/taxes/${tax.id}/edit`} className="font-medium text-info hover:underline">Edit</Link>
                                        <button onClick={() => destroy(tax)} className="ml-4 font-medium text-danger hover:underline">Hapus</button>
                                    </td>
                                </tr>
                            ))}
                            {taxes.length === 0 && (
                                <tr><td colSpan="4" className="px-4 py-12 text-center text-text-muted">Belum ada pajak.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
