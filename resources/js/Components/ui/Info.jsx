export function Info({ label, value, children, className = '' }) {
    const content = children ?? value;
    return (
        <div className={className}>
            <div className="text-[11px] font-bold uppercase tracking-wider text-text-faint">{label}</div>
            <div className="mt-1 whitespace-pre-line break-words text-sm text-text">
                {content === null || content === undefined || content === '' ? '—' : content}
            </div>
        </div>
    );
}

const COLS = { 1: 'sm:grid-cols-1', 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-3', 4: 'sm:grid-cols-4' };

export function InfoGrid({ cols = 2, className = '', children }) {
    return <div className={`grid gap-x-6 gap-y-4 ${COLS[cols]} ${className}`.trim()}>{children}</div>;
}
