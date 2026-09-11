import { Link } from '@inertiajs/react';

const VARIANTS = {
    primary: 'btn-primary',
    outline: 'btn-outline',
    ghost: 'btn-ghost',
    danger: 'btn-danger',
};
const SIZES = {
    sm: 'px-3 py-1.5 text-xs',
    md: '',
    lg: 'px-5 py-2.5 text-[15px]',
};

function Spinner() {
    return (
        <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" />
            <path className="opacity-90" d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
        </svg>
    );
}

export default function Button({
    variant = 'primary', size = 'md', href, external = false, icon: Icon, loading = false,
    className = '', children, type = 'button', ...rest
}) {
    const cls = `btn ${VARIANTS[variant] ?? ''} ${SIZES[size] ?? ''} ${className}`.trim();
    const inner = <>{loading ? <Spinner /> : Icon ? <Icon className="h-4 w-4" /> : null}{children}</>;

    if (href && !rest.disabled && !loading) {
        if (external) {
            return <a href={href} target="_blank" rel="noreferrer" className={cls} {...rest}>{inner}</a>;
        }
        return <Link href={href} className={cls} {...rest}>{inner}</Link>;
    }
    return (
        <button type={type} className={cls} disabled={loading || rest.disabled} {...rest}>
            {inner}
        </button>
    );
}
