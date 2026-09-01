import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const emptyForm = { title: '', meeting_date: '', location: '', attendees: '', notes: '' };

export default function MeetingsPanel({ leadId, meetings, editable }) {
    const [editingId, setEditingId] = useState(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm(emptyForm);

    function beginEdit(item) {
        setEditingId(item.id);
        clearErrors();
        setData({
            title: item.title,
            meeting_date: item.meeting_date?.slice(0, 10) ?? '',
            location: item.location ?? '',
            attendees: item.attendees ?? '',
            notes: item.notes ?? '',
        });
    }

    function cancel() {
        setEditingId(null);
        reset();
        clearErrors();
    }

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: cancel };
        editingId
            ? put(`/sales/leads/${leadId}/meetings/${editingId}`, options)
            : post(`/sales/leads/${leadId}/meetings`, options);
    }

    function destroy(id) {
        if (confirm('Hapus data meeting ini?')) {
            router.delete(`/sales/leads/${leadId}/meetings/${id}`, { preserveScroll: true });
        }
    }

    return (
        <section className="rounded-xl border border-border bg-surface p-6 shadow-sm">
            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-text">Data Meeting / MoM</h2>
                    <p className="text-sm text-text-muted">Catatan hasil pertemuan dengan customer untuk lead ini.</p>
                </div>
                <span className="rounded-full bg-bg px-3 py-1 text-sm text-text-muted">{meetings.length} catatan</span>
            </div>

            {meetings.length > 0 ? (
                <div className="mb-5 space-y-3">
                    {meetings.map((item) => (
                        <div key={item.id} className="rounded-lg border border-border p-4">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div className="font-medium text-text">{item.title}</div>
                                    <div className="text-xs text-text-muted">
                                        {item.meeting_date?.slice(0, 10)}
                                        {item.location ? ` · ${item.location}` : ''}
                                    </div>
                                </div>
                                {editable && (
                                    <div className="whitespace-nowrap text-sm">
                                        <button onClick={() => beginEdit(item)} className="mr-3 text-info">Edit</button>
                                        <button onClick={() => destroy(item.id)} className="text-danger">Hapus</button>
                                    </div>
                                )}
                            </div>
                            {item.attendees && <div className="mt-2 text-xs text-text-muted">Peserta: {item.attendees}</div>}
                            <div className="mt-2 whitespace-pre-line text-sm text-text">{item.notes}</div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="mb-5 rounded-lg bg-bg p-6 text-center text-sm text-text-muted">Belum ada data meeting.</div>
            )}

            {editable && (
                <form onSubmit={submit} className="space-y-4 rounded-lg border border-border bg-bg/50 p-4">
                    <h3 className="font-medium text-text">{editingId ? 'Edit Meeting' : 'Tambah Meeting'}</h3>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Field label="Judul *" error={errors.title} className="md:col-span-2">
                            <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="input" />
                        </Field>
                        <Field label="Tanggal *" error={errors.meeting_date}>
                            <input type="date" value={data.meeting_date} onChange={(e) => setData('meeting_date', e.target.value)} className="input" />
                        </Field>
                        <Field label="Lokasi" error={errors.location}>
                            <input value={data.location} onChange={(e) => setData('location', e.target.value)} className="input" />
                        </Field>
                        <Field label="Peserta" error={errors.attendees} className="md:col-span-2">
                            <input value={data.attendees} onChange={(e) => setData('attendees', e.target.value)} placeholder="Nama, jabatan, dipisah koma" className="input" />
                        </Field>
                    </div>
                    <Field label="Catatan / MoM *" error={errors.notes}>
                        <textarea rows="3" value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="input" />
                    </Field>
                    <div className="flex justify-end gap-2">
                        {editingId && <button type="button" onClick={cancel} className="rounded-lg border border-border px-4 py-2 text-sm">Batal</button>}
                        <button disabled={processing} className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            {processing ? 'Menyimpan...' : editingId ? 'Simpan Perubahan' : 'Tambah Meeting'}
                        </button>
                    </div>
                </form>
            )}
        </section>
    );
}

function Field({ label, error, children, className = '' }) {
    return <label className={`block text-sm font-medium text-text ${className}`}>{label}{children}{error && <span className="mt-1 block text-xs text-danger">{error}</span>}</label>;
}
