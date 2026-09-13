const TONE = {
    info: 'bg-primary-soft text-primary-strong',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    neutral: 'bg-bg text-text-muted',
};

/** status → [tone, label]. Label boleh dioverride lewat prop. */
const MAP = {
    // Quotation
    draft: ['warning', 'Draft'],
    sent: ['info', 'Terkirim'],
    confirmed: ['success', 'Confirmed'],
    revised: ['neutral', 'Direvisi'],
    rejected: ['danger', 'Ditolak'],
    // Invoice
    partially_paid: ['info', 'Dibayar Sebagian'],
    paid: ['success', 'Lunas'],
    overdue: ['danger', 'Jatuh Tempo'],
    cancelled: ['neutral', 'Dibatalkan'],
    // Procurement Request
    submitted: ['warning', 'Masuk'],
    searching: ['info', 'Dicari'],
    ready: ['success', 'Ready'],
    // Project
    planning: ['info', 'Perencanaan'],
    waiting_resource: ['warning', 'Menunggu Barang'],
    in_progress: ['info', 'Berjalan'],
    verification: ['warning', 'Verifikasi'],
    completed: ['success', 'Selesai'],
    // Actual procurement
    pending: ['warning', 'Pending'],
    purchased: ['info', 'Dibeli'],
    received: ['success', 'Diterima'],
    // Survey
    scheduled: ['info', 'Terjadwal'],
    finance_review: ['warning', 'Review Finance'],
    awaiting_payment: ['warning', 'Menunggu Bayar'],
    verified: ['success', 'Terverifikasi'],
    closed: ['neutral', 'Ditutup'],
    // SOW
    pending_hr_review: ['warning', 'Review HR'],
    rejected_by_hr: ['danger', 'Ditolak HR'],
    pending_technician_signature: ['warning', 'Menunggu TTD Teknisi'],
    pending_vendor_signature: ['warning', 'Menunggu TTD PIC Vendor'],
    pending_hr_verification: ['warning', 'Verifikasi TTD (HR)'],
    rejected_signature: ['danger', 'TTD Ditolak'],
    pending_admin_signature: ['warning', 'Menunggu TTD Admin'],
    pending_director_signature: ['warning', 'Menunggu TTD Direktur'],
    // Lead (type)
    lead: ['neutral', 'Lead'],
    opportunity: ['info', 'Opportunity'],
    approved: ['success', 'Disetujui'],
    // Lead stage
    new: ['neutral', 'Baru'],
    qualified: ['info', 'Terkualifikasi'],
    requirement: ['info', 'Requirement'],
    procurement: ['warning', 'Procurement'],
    quotation: ['warning', 'Quotation'],
    negotiation: ['warning', 'Negosiasi'],
    won: ['success', 'Won'],
    lost: ['danger', 'Lost'],
};

function titleize(s) {
    return String(s ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function StatusBadge({ status, label, tone, className = '' }) {
    const [mapTone, mapLabel] = MAP[status] ?? ['neutral', titleize(status)];
    const t = tone ?? mapTone;
    return (
        <span className={`inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold ${TONE[t]} ${className}`.trim()}>
            {label ?? mapLabel}
        </span>
    );
}
