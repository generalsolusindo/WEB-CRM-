import { Link } from '@inertiajs/react';

export default function Pagination({ links = [] }) {
    if (links.length <= 3) return null;

    return (
        <nav className="flex flex-wrap items-center gap-1" aria-label="Pagination">
            {links.map((link, index) => {
                const base = 'inline-flex min-w-9 items-center justify-center rounded-lg px-3 py-1.5 text-sm font-medium transition';
                if (!link.url) {
                    return (
                        <span
                            key={index}
                            className={`${base} cursor-not-allowed text-text-faint`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                }
                return (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={`${base} ${
                            link.active
                                ? 'bg-navy text-white shadow-sm'
                                : 'text-text-muted hover:bg-bg hover:text-text'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                );
            })}
        </nav>
    );
}
