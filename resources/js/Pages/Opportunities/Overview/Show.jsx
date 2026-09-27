import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FiTrash2 } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Button, PromptDialog } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';

export default function Show({ opportunity, canDelegate, canForceDelete = false, projectManagerOptions = [] }) {
    const delegateForm = useForm({ project_manager_id: opportunity.delegated_to?.id ?? '' });
    const backHref = canDelegate ? '/management/opportunities' : '/project-manager/opportunities';
    const [forceDeleteOpen, setForceDeleteOpen] = useState(false);
    const [forceDeleting, setForceDeleting] = useState(false);
    const [forceDeleteError, setForceDeleteError] = useState('');

    function submitDelegate(e) {
        e.preventDefault();
        feedback.expect({ success: { title: 'Delegasi opportunity diperbarui', style: 'popup' } });
        delegateForm.put(`/management/opportunities/${opportunity.id}/delegate`, { preserveScroll: true });
    }

    function confirmForceDelete(value) {
        if (value !== opportunity.code) {
            setForceDeleteError(`Ketik "${opportunity.code}" persis untuk konfirmasi.`);
            return;
        }
        setForceDeleteError('');
        setForceDeleting(true);
        feedback.expect({ success: { title: 'Data dihapus total', style: 'popup' } });
        router.delete(`/management/opportunities/${opportunity.id}`, {
            onError: () => setForceDeleting(false),
            onFinish: () => setForceDeleting(false),
        });
    }

    return (
        <AppLayout>
            <Head title={opportunity.code} />
            <div className="mx-auto max-w-2xl space-y-5">
                <PageHeader
                    title={opportunity.code}
                    subtitle={opportunity.company || opportunity.customer}
                    back={{ href: backHref, label: 'Kembali' }}
                    actions={canForceDelete && (
                        <Button
                            onClick={() => { setForceDeleteError(''); setForceDeleteOpen(true); }}
                            variant="ghost"
                            icon={FiTrash2}
                            className="text-danger hover:bg-danger-soft hover:text-danger"
                        >
                            Hapus Total
                        </Button>
                    )}
                />

                <PromptDialog
                    open={forceDeleteOpen}
                    onClose={() => setForceDeleteOpen(false)}
                    onConfirm={confirmForceDelete}
                    title="Hapus total data ini?"
                    description={`Seluruh Requirement, Meeting, Survey, Procurement Request, Quotation, Sales Order, Invoice, Project, SOW, BAST, pembayaran vendor, dokumen, dan notifikasi yang terhubung akan ikut terhapus PERMANEN — termasuk yang sudah ada transaksi/pembayaran nyata. Tindakan ini tidak bisa dibatalkan. Ketik ${opportunity.code} untuk konfirmasi.`}
                    label="Kode Opportunity"
                    placeholder={opportunity.code}
                    required
                    error={forceDeleteError}
                    processing={forceDeleting}
                    confirmLabel="Hapus Total"
                    confirmVariant="danger"
                />

                <section className="grid gap-x-6 gap-y-4 card p-6 sm:grid-cols-2">
                    <Info label="Customer" value={opportunity.customer} />
                    <Info label="Perusahaan" value={opportunity.company} />
                    <Info label="Email / Telepon" value={[opportunity.email, opportunity.phone].filter(Boolean).join(' · ')} />
                    <Info label="Sales" value={opportunity.sales} />
                    <Info label="Tahap" value={opportunity.stage_label} />
                    <Info
                        label="Project Manager"
                        value={opportunity.delegated_to ? `${opportunity.delegated_to.name} (oleh ${opportunity.delegated_by}, ${new Date(opportunity.delegated_at).toLocaleDateString('id-ID')})` : 'Belum ditunjuk'}
                    />
                    {opportunity.notes && <div className="sm:col-span-2"><Info label="Catatan" value={opportunity.notes} /></div>}
                </section>

                {canDelegate && (
                    <form onSubmit={submitDelegate} className="space-y-3 card p-6">
                        <h2 className="font-semibold text-text">Tunjuk Project Manager</h2>
                        <p className="text-sm text-text-muted">Wajib ditunjuk — quotation dari opportunity ini baru bisa dikirim ke customer setelah diverifikasi PM di sini dan Manager.</p>
                        <select value={delegateForm.data.project_manager_id} onChange={(e) => delegateForm.setData('project_manager_id', e.target.value)} className="input">
                            <option value="">— Belum ditunjuk —</option>
                            {projectManagerOptions.map((pm) => <option key={pm.id} value={pm.id}>{pm.name}</option>)}
                        </select>
                        {delegateForm.errors.project_manager_id && <span className="block text-xs text-danger">{delegateForm.errors.project_manager_id}</span>}
                        <div className="flex justify-end">
                            <button disabled={delegateForm.processing} className="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}

function Info({ label, value }) {
    return <div><div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>;
}
