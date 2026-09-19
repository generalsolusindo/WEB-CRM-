import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, StatusBadge, EmptyState } from '../../../Components/ui';

export default function Index({ surveys }) {
    return (
        <AppLayout>
            <Head title="Survey Saya" />
            <div className="mx-auto min-w-0 break-words max-w-3xl space-y-5">
                <PageHeader title="Survey Saya" />

                {surveys.length === 0 && <EmptyState title="Belum ada survey yang ditugaskan kepada Anda." />}

                {surveys.map((s) => (
                    <Link key={s.id} href={`/technician/surveys/${s.id}`} className="block card p-5 transition hover:border-border-strong hover:shadow-md">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="min-w-0 max-w-full">
                                <div className="font-semibold text-text">{s.code} · {s.site_region}</div>
                                <div className="text-xs text-text-muted">{s.customer}{s.revision > 1 ? ` · revisi ke-${s.revision}` : ''}</div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {s.status === 'in_progress' && (
                                    <span className={`badge ${s.checked_out ? 'badge-success' : s.checked_in ? 'badge-primary' : 'badge-warning'}`}>
                                        {s.checked_out ? 'Sudah absen pulang' : s.checked_in ? 'Sudah absen datang' : 'Belum absen'}
                                    </span>
                                )}
                                <StatusBadge status={s.status} label={s.status_label} />
                            </div>
                        </div>
                    </Link>
                ))}
            </div>
        </AppLayout>
    );
}
