import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { pickFiles } from '../../../utils/fileValidation';

export default function Submit({ project }) {
    const allDone = project.tasks.length > 0 && project.tasks.every((t) => t.status === 'done');
    const form = useForm({ notes: '', documents: [] });

    function submit(e) {
        e.preventDefault();
        form.post(`/technician/projects/${project.id}/bast`, { forceFormData: true });
    }

    return (
        <AppLayout>
            <Head title="Submit BAST" />
            <div className="mx-auto max-w-2xl space-y-5">
                <div>
                    <Link href="/technician/tasks" className="text-sm text-info">← Tugas Saya</Link>
                    <h1 className="mt-2 text-2xl font-bold text-text">Submit BAST — {project.number}</h1>
                    <p className="text-sm text-text-muted">{project.sales_order}</p>
                </div>

                {form.errors.bast && <div className="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{form.errors.bast}</div>}

                <section className="rounded-xl border border-border bg-surface p-5 shadow-sm">
                    <h2 className="mb-2 text-sm font-semibold text-text">Status Task</h2>
                    <ul className="space-y-1 text-sm">
                        {project.tasks.map((t) => (
                            <li key={t.id} className="flex justify-between">
                                <span className="text-text">{t.title}</span>
                                <span className={t.status === 'done' ? 'text-success' : 'text-warning'}>{t.status}</span>
                            </li>
                        ))}
                    </ul>
                    {!allDone && <p className="mt-2 text-xs text-danger">Semua task harus Selesai sebelum BAST dikirim.</p>}
                </section>

                <form onSubmit={submit} className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-sm">
                    <label className="block text-sm font-medium text-text">Catatan
                        <textarea rows="3" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} className="input" />
                    </label>
                    <label className="block text-sm font-medium text-text">Dokumen BAST (pdf/jpg/png, minimal 1)
                        <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => pickFiles(form, 'documents', e.target.files, 5)} className="mt-1 block w-full text-sm" />
                        <span className="block text-[11px] text-text-muted">maks 5 MB per file</span>
                        {(form.errors.documents || form.errors['documents.0']) && <span className="text-xs text-danger">{form.errors.documents || form.errors['documents.0']}</span>}
                    </label>
                    <div className="flex justify-end">
                        <button disabled={form.processing || !allDone} className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            {form.processing ? 'Mengirim...' : 'Kirim BAST'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
