import { Head } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import ProcurementPaymentDetail from '../../../Components/ProcurementPaymentDetail';
import { PageHeader, StatusBadge } from '../../../Components/ui';

export default function Show({ payment }) {
    return (
        <AppLayout>
            <Head title={payment.number} />
            <div className="mx-auto max-w-4xl space-y-5">
                <PageHeader
                    title={<span className="flex items-center gap-3">{payment.number} <StatusBadge status={payment.status} label={payment.status_label} /></span>}
                    subtitle={`${payment.project.number} · ${payment.project.customer}`}
                    back={{ href: `/procurement/project-procurements/${payment.project.id}`, label: 'Kembali ke project' }}
                />
                <ProcurementPaymentDetail payment={payment} />
            </div>
        </AppLayout>
    );
}
