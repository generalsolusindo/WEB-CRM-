import { Head, router } from '@inertiajs/react';
import { FiArrowUpRight, FiEdit2, FiTrash2 } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Button, Info, InfoGrid, StatusBadge } from '../../../Components/ui';
import MeetingsPanel from './MeetingsPanel';
import ProcurementStatusPanel from './ProcurementStatusPanel';
import RequirementsPanel from './RequirementsPanel';
import SurveyPanel from './SurveyPanel';

export default function Show({
    lead, stageOptions, sourceOptions = [], procurementRequest, requirementsEditable, leadEditable, canDelete,
    convertBlockReason, meetings = [], meetingsEditable = false, surveys = [],
    surveyRequestable = false, surveyDeliveryOptions = [], unitOptions = [],
    canSubmitAddendum = false, hasActiveSalesOrderForAddendum = false,
}) {
    const stageLabel = stageOptions.find((s) => s.value === lead.stage)?.label ?? lead.stage;
    const sourceLabel = sourceOptions.find((s) => s.value === lead.source)?.label ?? lead.source;
    function convert() { if (confirm('Konversi lead ini menjadi opportunity?')) router.post(`/sales/leads/${lead.id}/convert`); }
    function destroy() { if (confirm('Hapus lead ini?')) router.delete(`/sales/leads/${lead.id}`); }

    return (
        <AppLayout>
            <Head title={`${lead.type} #${lead.id}`} />
            <div className="mx-auto max-w-5xl space-y-5">
                <PageHeader
                    title={<span className="flex items-center gap-3 capitalize">{lead.type} #{lead.id} <StatusBadge status={lead.type} /></span>}
                    subtitle={`${lead.contact.name} · ${lead.contact.company_name || 'Tanpa perusahaan'}`}
                    back={{ href: '/sales/leads', label: 'Kembali ke Leads' }}
                    actions={
                        <>
                            {lead.type === 'lead' && (
                                convertBlockReason
                                    ? <Button variant="primary" disabled title={convertBlockReason} icon={FiArrowUpRight}>Jadikan Opportunity</Button>
                                    : <Button onClick={convert} icon={FiArrowUpRight}>Jadikan Opportunity</Button>
                            )}
                            {leadEditable && <Button href={`/sales/leads/${lead.id}/edit`} variant="outline" icon={FiEdit2}>Edit</Button>}
                            {canDelete && <Button onClick={destroy} variant="ghost" icon={FiTrash2} className="text-danger hover:bg-danger-soft hover:text-danger">Hapus</Button>}
                        </>
                    }
                />

                {lead.type === 'lead' && convertBlockReason && (
                    <div className="rounded-xl border border-warning/25 bg-warning-soft px-4 py-3 text-sm font-medium text-warning">{convertBlockReason}</div>
                )}

                <Card>
                    <InfoGrid>
                        <Info label="Stage" value={stageLabel} />
                        <Info label="Source" value={sourceLabel} />
                        <Info label="Email" value={lead.contact.email} />
                        <Info label="Telepon" value={lead.contact.phone} />
                        <Info label="Alamat" value={lead.contact.address} />
                        <Info label="PIC Customer" value={[lead.pic_name, lead.pic_position, lead.pic_phone].filter(Boolean).join(' · ')} />
                        <Info label="Catatan" value={lead.notes} className="sm:col-span-2" />
                    </InfoGrid>
                </Card>

                {lead.type === 'opportunity' && <SurveyPanel leadId={lead.id} surveys={surveys} requestable={surveyRequestable} deliveryOptions={surveyDeliveryOptions} />}
                <RequirementsPanel
                    leadId={lead.id}
                    requirements={lead.requirements}
                    editable={requirementsEditable}
                    unitOptions={unitOptions}
                    canSubmitAddendum={canSubmitAddendum}
                    isAddendumMode={hasActiveSalesOrderForAddendum}
                />
                <MeetingsPanel leadId={lead.id} meetings={meetings} editable={meetingsEditable} />
                <ProcurementStatusPanel request={procurementRequest} />
            </div>
        </AppLayout>
    );
}
