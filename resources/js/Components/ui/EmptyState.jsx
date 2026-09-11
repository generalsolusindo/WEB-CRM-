import { FiInbox } from 'react-icons/fi';

export default function EmptyState({ icon: Icon = FiInbox, title, description, action, className = '' }) {
    return (
        <div className={`flex flex-col items-center gap-2 px-6 py-14 text-center ${className}`.trim()}>
            <span className="mb-1 flex h-12 w-12 items-center justify-center rounded-2xl bg-bg text-text-faint">
                <Icon className="h-6 w-6" />
            </span>
            <p className="text-sm font-semibold text-text">{title}</p>
            {description && <p className="max-w-sm text-xs leading-relaxed text-text-muted">{description}</p>}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
