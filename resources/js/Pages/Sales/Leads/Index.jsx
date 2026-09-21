import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiUserPlus, FiTarget } from 'react-icons/fi';
import AppLayout from '../../../Layouts/AppLayout';
import { PageHeader, Toolbar, SearchInput, FilterSelect, Button, DataTable, StatusBadge, Pagination, EmptyState } from '../../../Components/ui';

export default function Index({ leads, filters, stageOptions, sourceOptions = [] }) {
    const [form, setForm] = useState(filters);
    const set = (name, value) => setForm((c) => ({ ...c, [name]: value }));

    function submit(e) {
        e?.preventDefault();
        router.get('/sales/leads', form, { preserveState: true, replace: true });
    }
    function reset() {
        setForm({ search: '', type: '', stage: '', source: '' });
        router.get('/sales/leads', {}, { replace: true });
    }

    const columns = [
        {
            key: 'customer', label: 'Customer',
            render: (l) => (
                <div>
                    <div className="font-semibold text-text">{l.contact.name}</div>
                    <div className="text-xs text-text-muted">{l.contact.company_name || '—'}</div>
                </div>
            ),
        },
        { key: 'type', label: 'Tipe', render: (l) => <StatusBadge status={l.type} /> },
        { key: 'stage', label: 'Stage', render: (l) => <span className="text-text-muted">{stageOptions.find((s) => s.value === l.stage)?.label ?? l.stage}</span> },
        { key: 'source', hideBelow: 'md', label: 'Source', render: (l) => <span className="text-text-muted">{sourceOptions.find((s) => s.value === l.source)?.label ?? (l.source || '—')}</span> },
        { key: 'requirements_count', hideBelow: 'md', label: 'Requirement', align: 'right', render: (l) => <span className="tabular-nums text-text-muted">{l.requirements_count}</span> },
    ];

    return (
        <AppLayout>
            <Head title="Leads & Opportunities" />
            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    title="Leads & Opportunities"
                    subtitle="Kelola pipeline customer milik Anda."
                    actions={<Button href="/sales/leads/create" icon={FiUserPlus}>Tambah Lead</Button>}
                />

                <form onSubmit={submit}>
                    <Toolbar>
                        <SearchInput value={form.search} onChange={(v) => set('search', v)} placeholder="Cari contact / perusahaan" />
                        <FilterSelect value={form.type} onChange={(v) => set('type', v)} className="min-w-36">
                            <option value="">Semua tipe</option>
                            <option value="lead">Lead</option>
                            <option value="opportunity">Opportunity</option>
                        </FilterSelect>
                        <FilterSelect value={form.stage} onChange={(v) => set('stage', v)} className="min-w-36">
                            <option value="">Semua stage</option>
                            {stageOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </FilterSelect>
                        <FilterSelect value={form.source} onChange={(v) => set('source', v)} className="min-w-36">
                            <option value="">Semua source</option>
                            {sourceOptions.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </FilterSelect>
                        <Button type="submit">Filter</Button>
                        <Button type="button" variant="outline" onClick={reset}>Reset</Button>
                    </Toolbar>
                </form>

                <DataTable
                    columns={columns}
                    rows={leads.data}
                    rowHref={(l) => `/sales/leads/${l.id}`}
                    footer={<Pagination links={leads.links} />}
                    empty={<EmptyState icon={FiTarget} title="Belum ada lead" description="Buat lead dari contact yang sudah ada, atau tambahkan lead baru." action={<Button href="/sales/leads/create" icon={FiUserPlus}>Tambah Lead</Button>} />}
                />
            </div>
        </AppLayout>
    );
}
