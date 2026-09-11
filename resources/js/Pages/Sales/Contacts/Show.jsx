import { Head, Link, router } from '@inertiajs/react';
import { FiUserPlus, FiEdit2, FiTrash2 } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, CardHeader, Button, Info, InfoGrid, StatusBadge, EmptyState } from '../../../Components/ui';

export default function Show({ contact, leads }) {
    function destroy() {
        if (confirm('Hapus contact ini?')) router.delete(`/sales/contacts/${contact.id}`);
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
                            <Button href={`/sales/leads/create?contact_id=${contact.id}`} icon={FiUserPlus}>Buat Lead</Button>
                            <Button href={`/sales/contacts/${contact.id}/edit`} variant="outline" icon={FiEdit2}>Edit</Button>
                            <Button onClick={destroy} variant="ghost" icon={FiTrash2} className="text-danger hover:bg-danger-soft hover:text-danger">Hapus</Button>
                        </>
                    }
                />

                <Card>
                    <InfoGrid>
                        <Info label="Email" value={contact.email} />
                        <Info label="Telepon" value={contact.phone} />
                        <Info label="NPWP" value={contact.npwp} />
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
