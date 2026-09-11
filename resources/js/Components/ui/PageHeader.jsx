import { Link } from '@inertiajs/react';
import { FiChevronLeft } from 'react-icons/fi';

export default function PageHeader({ title, subtitle, actions, back }) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div className="min-w-0">
                {back && (
                    <Link href={back.href} className="mb-1.5 inline-flex items-center gap-1 text-sm font-medium text-primary transition hover:gap-1.5">
                        <FiChevronLeft className="h-4 w-4" />
                        {back.label ?? 'Kembali'}
                    </Link>
                )}
                <h1 className="text-2xl font-bold tracking-tight text-text">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-text-muted">{subtitle}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
