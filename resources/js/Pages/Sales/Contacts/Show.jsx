import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FiUserPlus, FiEdit2, FiTrash2, FiMessageCircle, FiGitMerge } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, CardHeader, Button, ConfirmDialog, Info, InfoGrid, StatusBadge, EmptyState, Modal, Select } from '../../../Components/ui';
import { contactWhatsappLink } from '../../../Utils/whatsapp';

export default function Show({ contact, leads, npwpDocumentUrl = null, otherContacts = [] }) {
    const salesName = usePage().props.auth?.user?.name;
    const waLink = contactWhatsappLink(contact, salesName);
    const [mergeOpen, setMergeOpen] = useState(false);
    const [duplicateId, setDuplicateId] = useState('');
    const [merging, setMerging] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    function destroy() {
        setDeleting(true);
        router.delete(`/sales/contacts/${contact.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleteOpen(false),
            onFinish: () => setDeleting(false),
        });
    }

    function submitMerge() {
        if (!duplicateId) return;
        setMerging(true);
        router.post(`/sales/contacts/${contact.id}/merge`, { duplicate_contact_id: duplicateId }, {
            onFinish: () => setMerging(false),
            onSuccess: () => setMergeOpen(false),
        });
    }

    return (
        <AppLayout>
            <Head title={contact.name} />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title={contact.name}
                    subtitle={contact.company_name || 'Tanpa perusahaan'}
                    back={{ href: '/sales/contacts', label: 'Kembali ke Contacts' }}
                    actions={
                        <>
                            {waLink && (
                                <Button
                                    href={waLink}
                                    external
                                    icon={FiMessageCircle}
                                    className="bg-success text-white hover:bg-success"
                                >
                                    Follow Up via WhatsApp
                                </Button>
                            )}
                            <Button href={`/sales/leads/create?contact_id=${contact.id}`} icon={FiUserPlus}>Buat Lead</Button>
                            <Button href={`/sales/contacts/${contact.id}/edit`} variant="outline" icon={FiEdit2}>Edit</Button>
                            {otherContacts.length > 0 && (
                                <Button onClick={() => setMergeOpen(true)} variant="outline" icon={FiGitMerge}>Gabungkan Duplikat</Button>
                            )}
                            <Button onClick={() => setDeleteOpen(true)} variant="ghost" icon={FiTrash2} className="text-danger hover:bg-danger-soft hover:text-danger">Hapus</Button>
                        </>
                    }
                />

                <Modal open={mergeOpen} onClose={() => setMergeOpen(false)} title="Gabungkan Contact Duplikat" size="sm" busy={merging}
                    footer={(
                        <>
                            <Button variant="outline" onClick={() => setMergeOpen(false)}>Batal</Button>
                            <Button onClick={submitMerge} disabled={!duplicateId || merging} className="bg-danger hover:bg-danger">
                                {merging ? 'Menggabungkan…' : 'Gabungkan & Hapus Duplikat'}
                            </Button>
                        </>
                    )}
                >
                    <p className="mb-3 text-sm text-text-muted">
                        Pilih contact lain yang ternyata duplikat dari <strong className="text-text">{contact.name}</strong> ini.
                        Semua lead, quotation, sales order, dan dokumen milik duplikat itu akan dipindah ke sini, lalu
                        contact duplikatnya akan dihapus permanen.
                    </p>
                    <Select value={duplicateId} onChange={(e) => setDuplicateId(e.target.value)}>
                        <option value="">— pilih contact duplikat —</option>
                        {otherContacts.map((c) => (
                            <option key={c.id} value={c.id}>{c.name}{c.company_name ? ` · ${c.company_name}` : ''}</option>
                        ))}
                    </Select>
                    {duplicateId && (
                        <p className="mt-3 rounded-xl border border-danger/20 bg-danger-soft px-3 py-2 text-xs leading-relaxed text-danger">
                            Contact yang dipilih akan dihapus permanen setelah seluruh data terkait dipindahkan ke {contact.name}.
                        </p>
                    )}
                </Modal>

                <ConfirmDialog
                    open={deleteOpen}
                    onClose={() => setDeleteOpen(false)}
                    onConfirm={destroy}
                    title="Hapus contact?"
                    description={`Contact ${contact.name} akan dihapus. Tindakan ini tidak bisa dibatalkan.`}
                    tone="danger"
                    confirmLabel="Hapus Contact"
                    processing={deleting}
                />

                <Card>
                    <InfoGrid>
                        <Info label="Email" value={contact.email} />
                        <Info label="Telepon" value={contact.phone} />
                        <Info label="NPWP">
                            {contact.npwp || '—'}
                            {npwpDocumentUrl && (
                                <a href={npwpDocumentUrl} target="_blank" rel="noreferrer" className="ml-2 text-xs font-medium text-primary hover:underline">
                                    (Lihat dokumen)
                                </a>
                            )}
                        </Info>
                        <Info label="Alamat" value={contact.address} />
                        <Info label="Catatan" value={contact.notes} className="sm:col-span-2" />
                    </InfoGrid>
                </Card>

                <Card padded={false}>
                    <CardHeader title="Lead Terkait" />
                    {leads.length === 0 ? (
                        <EmptyState title="Belum ada lead" description="Contact ini belum punya lead — buat satu untuk mulai." />
                    ) : (
                        <div className="divide-y divide-border">
                            {leads.map((lead) => (
                                <Link key={lead.id} href={`/sales/leads/${lead.id}`} className="flex items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-bg">
                                    <span className="font-medium capitalize text-text">{lead.type} #{lead.id}</span>
                                    <StatusBadge status={lead.stage} />
                                </Link>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
