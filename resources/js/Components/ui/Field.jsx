export function Field({ label, hint, error, required, className = '', children }) {
    return (
        <label className={`block ${className}`.trim()}>
            {label && (
                <span className="block text-sm font-medium text-text">
                    {label}{required && <span className="text-danger"> *</span>}
                </span>
            )}
            {children}
            {hint && !error && <span className="mt-1 block text-xs text-text-muted">{hint}</span>}
            {error && <span className="mt-1 block text-xs font-medium text-danger">{error}</span>}
        </label>
    );
}

export function Input({ className = '', ...props }) {
    return <input {...props} className={`input ${className}`.trim()} />;
}

export function Textarea({ className = '', rows = 4, ...props }) {
    return <textarea rows={rows} {...props} className={`input ${className}`.trim()} />;
}

export function Select({ className = '', children, ...props }) {
    return <select {...props} className={`input ${className}`.trim()}>{children}</select>;
}
