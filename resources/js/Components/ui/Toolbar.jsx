import { FiSearch } from 'react-icons/fi';

export default function Toolbar({ children, className = '' }) {
    return (
        <div className={`flex flex-wrap items-center gap-2 rounded-2xl border border-border bg-surface p-3 shadow-sm ${className}`.trim()}>
            {children}
        </div>
    );
}

const CONTROL = 'rounded-lg border border-border-strong bg-surface py-2 text-sm text-text outline-none transition hover:border-text-faint focus:border-primary focus:shadow-[0_0_0_3px_var(--color-primary-ring)]';

export function SearchInput({ value, onChange, placeholder = 'Cari…', className = '' }) {
    return (
        <div className={`relative min-w-0 flex-1 ${className}`.trim()}>
            <FiSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-faint" />
            <input
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className={`${CONTROL} w-full pl-9 pr-3 placeholder:text-text-faint`}
            />
        </div>
    );
}

export function FilterSelect({ value, onChange, children, className = '' }) {
    return (
        <select value={value} onChange={(e) => onChange(e.target.value)} className={`${CONTROL} px-3 ${className}`.trim()}>
            {children}
        </select>
    );
}
