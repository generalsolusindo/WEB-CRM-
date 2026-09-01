import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';

function badge(status) {
    if (status === 'verified') return 'bg-success/10 text-success';
    if (status === 'report_review') return 'bg-info/10 text-info';
    return 'bg-warning/10 text-warning';
}

export default function Index({ surveys }) {
    return (
        <AppLayout>
            <Head title="Survey Saya" />
            <div className="mx-auto max-w-3xl space-y-5">
                <h1 className="text-2xl font-bold text-text">Survey Saya</h1>

                {surveys.length === 0 && (
                    <div className="rounded-xl border border-border bg-surface p-8 text-center text-sm text-text-muted shadow-sm">
                        Belum ada survey yang ditugaskan kepada Anda.
                    </div>
                )}

                {surveys.map((s) => (
                    <Link key={s.id} href={`/technician/surveys/${s.id}`} className="block rounded-xl border border-border bg-surface p-5 shadow-sm hover:bg-bg/50">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div className="font-semibold text-text">{s.code} · {s.site_region}</div>
                                <div className="text-xs text-text-muted">{s.customer}{s.revision > 1 ? ` · revisi ke-${s.revision}` : ''}</div>
                            </div>
                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${badge(s.status)}`}>{s.status_label}</span>
                        </div>
                    </Link>
                ))}
            </div>
        </AppLayout>
    );
}
