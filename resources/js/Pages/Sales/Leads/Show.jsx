import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import MeetingsPanel from './MeetingsPanel';
import ProcurementStatusPanel from './ProcurementStatusPanel';
import RequirementsPanel from './RequirementsPanel';
import SurveyPanel from './SurveyPanel';

export default function Show({ lead, stageOptions, procurementRequest, requirementsEditable, leadEditable, canDelete, convertBlockReason, meetings = [], meetingsEditable = false, surveys = [], surveyRequestable = false, surveyDeliveryOptions = [] }) {
    const stageLabel = stageOptions.find((s) => s.value === lead.stage)?.label ?? lead.stage;
    function convert() { if (confirm('Konversi lead ini menjadi opportunity?')) router.post(`/sales/leads/${lead.id}/convert`); }
    function destroy() { if (confirm('Hapus lead ini?')) router.delete(`/sales/leads/${lead.id}`); }
    return <AppLayout><Head title={`${lead.type} #${lead.id}`} /><div className="mx-auto max-w-5xl space-y-5">
        <div className="flex flex-wrap items-start justify-between gap-3"><div><Link href="/sales/leads" className="text-sm text-info">← Kembali ke Leads</Link><div className="mt-2 flex items-center gap-3"><h1 className="text-2xl font-bold capitalize text-text">{lead.type} #{lead.id}</h1><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${lead.type === 'opportunity' ? 'bg-info/10 text-info' : 'bg-warning/10 text-warning'}`}>{lead.type}</span></div><p className="text-text-muted">{lead.contact.name} · {lead.contact.company_name || 'Tanpa perusahaan'}</p></div><div className="flex flex-wrap gap-2">{lead.type === 'lead' && (convertBlockReason
        ? <span title={convertBlockReason} className="cursor-not-allowed rounded-lg bg-info/40 px-4 py-2 text-sm font-semibold text-white">Jadikan Opportunity</span>
        : <button onClick={convert} className="rounded-lg bg-info px-4 py-2 text-sm font-semibold text-white">Jadikan Opportunity</button>)}{leadEditable && <Link href={`/sales/leads/${lead.id}/edit`} className="rounded-lg border border-border px-4 py-2 text-sm">Edit</Link>}{canDelete && <button onClick={destroy} className="rounded-lg border border-danger/30 px-4 py-2 text-sm text-danger">Hapus</button>}</div></div>
        {lead.type === 'lead' && convertBlockReason && <p className="text-sm text-warning">{convertBlockReason}</p>}
        <section className="grid gap-5 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-2"><Info label="Stage" value={stageLabel} /><Info label="Source" value={lead.source} /><Info label="Email" value={lead.contact.email} /><Info label="Telepon" value={lead.contact.phone} /><Info label="Alamat" value={lead.contact.address} /><Info label="PIC Customer" value={[lead.pic_name, lead.pic_position, lead.pic_phone].filter(Boolean).join(' · ')} /><Info label="Catatan" value={lead.notes} /></section>
        {lead.type === 'opportunity' && <SurveyPanel leadId={lead.id} surveys={surveys} requestable={surveyRequestable} deliveryOptions={surveyDeliveryOptions} />}
        <RequirementsPanel leadId={lead.id} requirements={lead.requirements} editable={requirementsEditable} />
        <MeetingsPanel leadId={lead.id} meetings={meetings} editable={meetingsEditable} />
        <ProcurementStatusPanel request={procurementRequest} />
    </div></AppLayout>;
}
function Info({ label, value }) { return <div><div className="text-xs font-semibold uppercase tracking-wide text-text-muted">{label}</div><div className="mt-1 whitespace-pre-line text-sm text-text">{value || '—'}</div></div>; }
