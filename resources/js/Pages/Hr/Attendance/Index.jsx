import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, DataTable, EmptyState, Pagination, DateRangeFilter, Toolbar, FilterSelect } from '../../../Components/ui';

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : null;
}

function formatDuration(minutes) {
    if (minutes == null) return null;
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return hours > 0 ? `${hours} jam ${rest} menit` : `${rest} menit`;
}

function Selfie({ url, at, label }) {
    if (!url) return <span className="text-xs text-text-muted">Belum absen {label}</span>;
    return (
        <a href={url} target="_blank" rel="noreferrer" className="flex items-center gap-2">
            <img src={url} alt={`Absen ${label}`} className="h-12 w-12 shrink-0 rounded-lg object-cover" />
            <span className="text-xs text-text-muted">{formatDateTime(at)}</span>
        </a>
    );
}

export default function Index({ attendance, filters, technicianOptions = [] }) {
    function query(overrides) {
        return {
            ...(filters.type ? { type: filters.type } : {}),
            ...(filters.technician ? { technician: filters.technician } : {}),
            ...(filters.from ? { from: filters.from } : {}),
            ...(filters.to ? { to: filters.to } : {}),
            ...overrides,
        };
    }
    const go = (overrides) => router.get('/hr/attendance', query(overrides), { preserveState: true, replace: true });

    const columns = [
        {
            key: 'technician',
            label: 'Teknisi',
            render: (a) => (
                <div>
                    <div className="font-medium text-text">{a.technician || '—'}</div>
                    {a.vendor && <div className="text-xs text-text-muted">Vendor: {a.vendor}</div>}
                </div>
            ),
        },
        {
            key: 'job',
            label: 'Pekerjaan',
            render: (a) => (
                <div>
                    <div className="font-medium text-text">
                        {a.job_code} <span className="ml-1 rounded-full bg-bg px-2 py-0.5 text-[10px] font-semibold uppercase text-text-muted">{a.type === 'project' ? 'Project' : 'Survey'}</span>
                    </div>
                    <div className="text-xs text-text-muted">{[a.customer, a.company].filter(Boolean).join(' · ') || '—'}</div>
                </div>
            ),
        },
        { key: 'in', label: 'Absen Masuk', render: (a) => <Selfie url={a.check_in_url} at={a.check_in_at} label="masuk" /> },
        { key: 'out', label: 'Absen Pulang', render: (a) => <Selfie url={a.check_out_url} at={a.check_out_at} label="pulang" /> },
        { key: 'duration', hideBelow: 'md', label: 'Durasi', render: (a) => <span className="text-text-muted">{formatDuration(a.duration_minutes) ?? '—'}</span> },
    ];

    return (
        <AppLayout>
            <Head title="Absensi Teknisi" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title="Absensi Teknisi"
                    subtitle="Foto absen masuk dan pulang teknisi/surveyor saat mengerjakan survey atau project — hanya untuk dilihat."
                    back={{ href: '/dashboard', label: 'Kembali ke Dashboard' }}
                />
                <Toolbar>
                    <FilterSelect value={filters.type} onChange={(value) => go({ type: value || undefined })}>
                        <option value="">Semua pekerjaan</option>
                        <option value="project">Project</option>
                        <option value="survey">Survey</option>
                    </FilterSelect>
                    <FilterSelect value={filters.technician} onChange={(value) => go({ technician: value || undefined })}>
                        <option value="">Semua teknisi</option>
                        {technicianOptions.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </FilterSelect>
                </Toolbar>
                <DateRangeFilter
                    from={filters.from}
                    to={filters.to}
                    onApply={(from, to) => go({ from: from || undefined, to: to || undefined })}
                    onReset={() => go({ from: undefined, to: undefined })}
                />
                <DataTable
                    columns={columns}
                    rows={attendance.data}
                    rowKey="id"
                    empty={<EmptyState title="Belum ada absensi." description="Absensi muncul di sini begitu teknisi absen di survey atau project." />}
                    footer={<Pagination links={attendance.links} />}
                />
            </div>
        </AppLayout>
    );
}
