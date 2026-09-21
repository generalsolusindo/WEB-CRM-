import { Link } from '@inertiajs/react';

/**
 * tabs: [{ value, label, count?, href? }]
 * Mode tombol (onChange) atau mode link (href per tab).
 */
export default function PillTabs({ tabs, value, onChange, className = '' }) {
    return (
        <div className={`flex flex-wrap gap-1.5 ${className}`.trim()}>
            {tabs.map((t) => {
                const active = t.value === value;
                const cls = `inline-flex min-h-10 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs pointer-fine:min-h-0 font-semibold transition ${
                    active
                        ? 'bg-navy text-white shadow-sm'
                        : 'border border-border bg-surface text-text-muted hover:border-border-strong hover:text-text'
                }`;
                const inner = (
                    <>
                        {t.label}
                        {t.count != null && (
                            <span className={active ? 'text-white/60' : 'text-text-faint'}>{t.count}</span>
                        )}
                    </>
                );
                return t.href
                    ? <Link key={t.value} href={t.href} className={cls}>{inner}</Link>
                    : <button key={t.value} type="button" onClick={() => onChange(t.value)} className={cls}>{inner}</button>;
            })}
        </div>
    );
}
