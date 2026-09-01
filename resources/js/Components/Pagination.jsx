import { Link } from '@inertiajs/react';

export default function Pagination({ links = [] }) {
    if (links.length <= 3) return null;

    return (
        <nav className="flex flex-wrap gap-1" aria-label="Pagination">
            {links.map((link, index) => (
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={`rounded-lg border px-3 py-1.5 text-sm ${
                            link.active
                                ? 'border-navy bg-navy text-white'
                                : 'border-border bg-surface text-text-muted hover:text-text'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={index}
                        className="cursor-not-allowed rounded-lg border border-border px-3 py-1.5 text-sm text-text-muted opacity-50"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                )
            ))}
        </nav>
    );
}
