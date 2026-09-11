import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { FiX } from 'react-icons/fi';

const SIZE = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' };

export default function Modal({ open, onClose, title, children, footer, size = 'md' }) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && onClose?.();
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center">
            <div className="fixed inset-0 bg-navy/30 backdrop-blur-[2px]" onClick={onClose} />
            <div className={`relative w-full ${SIZE[size]} rounded-2xl border border-border bg-surface shadow-xl`}>
                <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                    <h3 className="text-base font-bold tracking-tight text-text">{title}</h3>
                    <button onClick={onClose} className="flex h-8 w-8 items-center justify-center rounded-full text-text-muted transition hover:bg-bg hover:text-text">
                        <FiX className="h-4 w-4" />
                    </button>
                </div>
                <div className="px-5 py-4">{children}</div>
                {footer && <div className="flex flex-wrap justify-end gap-2 border-t border-border px-5 py-4">{footer}</div>}
            </div>
        </div>,
        document.body,
    );
}
