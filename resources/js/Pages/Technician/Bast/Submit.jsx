import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { pickFiles } from '../../../utils/fileValidation';
import { PageHeader } from '../../../Components/ui';
import { feedback } from '../../../Components/feedback';

export default function Submit({ project }) {
    const allDone = project.tasks.length > 0 && project.tasks.every((t) => t.status === 'done');
    const form = useForm({ notes: '', documents: [] });

    async function submit(e) {
        e.preventDefault();
        const ok = await feedback.confirm({
            tone: 'question',
            title: 'Kirim BAST?',
            text: 'BAST dikirim ke Operasional untuk diverifikasi. Setelah dikirim, isinya tidak bisa diubah lagi.',
            confirmLabel: 'Ya, kirim BAST',
        });
        if (!ok) return;
        feedback.expect({ success: { title: 'BAST terkirim', style: 'popup' }, error: { title: 'BAST belum terkirim' } });
        form.post(`/technician/projects/${project.id}/bast`, { forceFormData: true });
    }

    return (
        <AppLayout>
            <Head title="Submit BAST" />
            <div className="mx-auto min-w-0 break-words max-w-2xl space-y-5">
                <PageHeader
                    title={`Submit BAST — ${project.number}`}
                    subtitle={project.sales_order}
                    back={{ href: '/technician/tasks', label: 'Tugas Saya' }}
                />

                {form.errors.bast && <div className="rounded-xl border border-danger/25 bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{form.errors.bast}</div>}

                <section className="card p-5">
                    <h2 className="mb-2 text-sm font-semibold text-text">Status Task</h2>
                    <ul className="space-y-1 text-sm">
                        {project.tasks.map((t) => (
                            <li key={t.id} className="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                <span className="min-w-0 break-words text-text">{t.title}</span>
                                <span className={t.status === 'done' ? 'text-success' : 'text-warning'}>{t.status}</span>
                            </li>
                        ))}
                    </ul>
                    {!allDone && <p className="mt-2 text-xs text-danger">Semua task harus Selesai sebelum BAST dikirim.</p>}
                </section>

                <form onSubmit={submit} className="space-y-4 card p-4 sm:p-6">
                    <label className="block text-sm font-medium text-text">Catatan
                        <textarea rows="3" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} className="input" />
                    </label>
                    <label className="block text-sm font-medium text-text">Dokumen BAST (pdf/jpg/png, minimal 1)
                        <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFiles(form, 'documents', e.target.files, 5)} className="mt-1 block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-strong" />
                        <span className="block text-[11px] text-text-muted">maks 5 MB per file</span>
                        {(form.errors.documents || form.errors['documents.0']) && <span className="text-xs text-danger">{form.errors.documents || form.errors['documents.0']}</span>}
                    </label>
                    <div className="flex justify-end">
                        <button disabled={form.processing || !allDone} className="btn btn-primary">
                            {form.processing ? 'Mengirim...' : 'Kirim BAST'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
