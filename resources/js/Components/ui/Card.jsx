export default function Card({ className = '', padded = true, children }) {
    return <div className={`card ${padded ? 'p-5' : ''} ${className}`.trim()}>{children}</div>;
}

export function CardHeader({ title, subtitle, actions, className = '' }) {
    return (
        <div className={`flex items-start justify-between gap-3 border-b border-border px-5 py-4 ${className}`.trim()}>
            <div className="min-w-0">
                <h3 className="text-base font-bold tracking-tight text-text">{title}</h3>
                {subtitle && <p className="mt-0.5 text-sm text-text-muted">{subtitle}</p>}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>}
        </div>
    );
}
