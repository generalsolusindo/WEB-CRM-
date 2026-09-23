import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { PageHeader, Card, EmptyState, Button } from '../../Components/ui';
import { feedback } from '../../Components/feedback';

const ROLE_LABELS = {
    operational: 'Operasional',
    project_manager: 'Project Manager',
    management: 'Management',
};

export default function UserSignatures({ users }) {
    return (
        <AppLayout>
            <Head title="Tanda Tangan Internal" />
            <div className="mx-auto max-w-3xl space-y-5">
                <PageHeader
                    title="Tanda Tangan Internal"
                    subtitle="Gambar tanda tangan tiap orang di sini dipakai otomatis saat dia sendiri menandatangani slot TTD Operasional / Project Manager pada dokumen SOW — tidak perlu digambar ulang setiap kali. Upload gambar yang dikirim orangnya (mis. lewat WhatsApp) atas nama dia di sini."
                />

                {users.length === 0 ? (
                    <EmptyState title="Belum ada staf Operasional, Project Manager, atau Management yang aktif." />
                ) : (
                    <div className="space-y-3">
                        {users.map((user) => <UserRow key={user.id} user={user} />)}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function UserRow({ user }) {
    const form = useForm({ signature: null });

    function submit(e) {
        e.preventDefault();
        feedback.expect({ success: { title: `Tanda tangan ${user.name} tersimpan`, style: 'toast' } });
        form.post(`/admin/user-signatures/${user.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('signature'),
        });
    }

    return (
        <Card className="space-y-3">
            <form onSubmit={submit} className="flex flex-wrap items-center gap-4">
                {user.signature_url ? (
                    <img src={user.signature_url} alt={`Tanda tangan ${user.name}`} className="h-16 w-32 shrink-0 rounded-lg border border-border bg-white object-contain p-1" />
                ) : (
                    <div className="flex h-16 w-32 shrink-0 items-center justify-center rounded-lg border border-dashed border-border text-[11px] text-text-faint">Belum ada</div>
                )}

                <div className="min-w-0 flex-1">
                    <div className="font-semibold text-text">{user.name}</div>
                    <div className="text-xs uppercase tracking-wide text-text-muted">{ROLE_LABELS[user.role] ?? user.role}</div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <input
                        type="file" accept=".jpg,.jpeg,.png"
                        onChange={(e) => form.setData('signature', e.target.files[0] ?? null)}
                        className="max-w-[220px] text-xs text-text-muted file:mr-2 file:rounded-lg file:border-0 file:bg-primary-soft file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-primary-strong"
                    />
                    <Button type="submit" size="sm" loading={form.processing} disabled={!form.data.signature}>
                        {user.signature_url ? 'Ganti' : 'Simpan'}
                    </Button>
                </div>
            </form>
            {form.errors.signature && <p className="text-xs text-danger">{form.errors.signature}</p>}
        </Card>
    );
}
