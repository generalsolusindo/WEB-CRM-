const MAP = {
    service: { label: 'Jasa', cls: 'bg-info/10 text-info' },
    reimburse: { label: 'Reimburse', cls: 'bg-warning/10 text-warning' },
};

export default function CategoryBadge({ category }) {
    const meta = MAP[category];
    if (!meta) return null;

    return <span className={`ml-1 rounded px-1.5 py-0.5 text-[10px] font-semibold ${meta.cls}`}>{meta.label}</span>;
}
