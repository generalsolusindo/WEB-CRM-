import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiArrowUpRight, FiEdit2, FiTrash2, FiXCircle } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Card, Button, ConfirmDialog, Info, InfoGrid, StatusBadge } from '../../../Components/ui';
import MeetingsPanel from './MeetingsPanel';
import ProcurementStatusPanel from './ProcurementStatusPanel';
import RequirementsPanel from './RequirementsPanel';
import SurveyPanel from './SurveyPanel';

export default function Show({
    lead, stageOptions, sourceOptions = [], procurementRequest, requirementsEditable, leadEditable, canDelete, canMarkLost = false,
    temperatureOptions = [],
    convertBlockReason, meetings = [], meetingsEditable = false, surveys = [],
    surveyRequestable = false, surveyDeliveryOptions = [], unitOptions = [],
    canSubmitAddendum = false, hasActiveSalesOrderForAddendum = false,
}) {
    const stageLabel = stageOptions.find((s) => s.value === lead.stage)?.label ?? lead.stage;
    const sourceLabel = sourceOptions.find((s) => s.value === lead.source)?.label ?? lead.source;
    const temperatureLabel = temperatureOptions.find((option) => option.value === lead.temperature)?.label ?? lead.temperature;
    const [lostOpen, setLostOpen] = useState(false);
    const [markingLost, setMarkingLost] = useState(false);
    function convert() { if (confirm('Tandai lead ini Terkualifikasi & lanjutkan sebagai opportunity?')) router.post(`/sales/leads/${lead.id}/convert`); }
    function destroy() {
        if (confirm('Hapus lead ini? Requirement, Procurement Request, Quotation, dan Survey di bawahnya akan ikut terhapus sekaligus. Tindakan ini tidak bisa dibatalkan.')) {
            router.delete(`/sales/leads/${lead.id}`);
        }
    }
    function markLost() {
        setMarkingLost(true);
        router.post(`/sales/leads/${lead.id}/mark-lost`, {}, {
            preserveScroll: true,
            onSuccess: () => setLostOpen(false),
            onFinish: () => setMarkingLost(false),
        });
    }

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
                                    ? <Button variant="primary" disabled title={convertBlockReason} icon={FiArrowUpRight}>Tandai Terkualifikasi & Lanjutkan</Button>
                                    : <Button onClick={convert} icon={FiArrowUpRight}>Tandai Terkualifikasi & Lanjutkan</Button>
                            )}
                            {leadEditable && <Button href={`/sales/leads/${lead.id}/edit`} variant="outline" icon={FiEdit2}>Edit</Button>}
                            {canMarkLost && <Button onClick={() => setLostOpen(true)} variant="outline" icon={FiXCircle} className="border-danger/30 text-danger hover:bg-danger-soft">Tandai Gagal</Button>}
                            {canDelete && <Button onClick={destroy} variant="ghost" icon={FiTrash2} className="text-danger hover:bg-danger-soft hover:text-danger">Hapus</Button>}
                        </>
                    }
                />

                <ConfirmDialog
                    open={lostOpen}
                    onClose={() => setLostOpen(false)}
                    onConfirm={markLost}
                    title="Tandai lead sebagai gagal?"
                    description="Pipeline Stage akan berubah menjadi Gagal. Gunakan tindakan ini jika proses penawaran benar-benar tidak dilanjutkan."
                    tone="danger"
                    confirmLabel="Ya, Tandai Gagal"
                    processing={markingLost}
                />

                {lead.type === 'lead' && convertBlockReason && (
                    <div className="rounded-xl border border-warning/25 bg-warning-soft px-4 py-3 text-sm font-medium text-warning">{convertBlockReason}</div>
                )}

                <Card>
                    <InfoGrid>
                        <Info label="Stage" value={stageLabel} />
                        <Info label="Status Lead" value={temperatureLabel} />
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
