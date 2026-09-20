import { useEffect, useId, useRef } from 'react';
import { createPortal } from 'react-dom';
import { FiX } from 'react-icons/fi';

const SIZE = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' };
const modalStack = [];
let originalBodyOverflow = '';

const FOCUSABLE = [
    'a[href]', 'button:not([disabled])', 'input:not([disabled])', 'select:not([disabled])',
    'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])',
].join(',');

export default function Modal({
    open, onClose, title, description, children, footer, size = 'md', busy = false,
    closeOnBackdrop = true, closeOnEscape = true, initialFocusRef, ariaDescribedBy, bare = false,
}) {
    const panelRef = useRef(null);
    const previouslyFocusedRef = useRef(null);
    const modalIdRef = useRef(Symbol('modal'));
    const stateRef = useRef({ onClose, busy, closeOnEscape });
    const titleId = useId();
    const descriptionId = useId();
    stateRef.current = { onClose, busy, closeOnEscape };

    useEffect(() => {
        if (!open) return undefined;

        const modalId = modalIdRef.current;
        modalStack.push(modalId);
        previouslyFocusedRef.current = document.activeElement;
        if (modalStack.length === 1) originalBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        const focusTimer = window.setTimeout(() => {
            const preferred = initialFocusRef?.current;
            const firstFocusable = panelRef.current?.querySelector(FOCUSABLE);
            (preferred || firstFocusable || panelRef.current)?.focus();
        }, 0);

        function onKeyDown(event) {
            if (modalStack.at(-1) !== modalId) return;
            if (event.key === 'Escape' && stateRef.current.closeOnEscape && !stateRef.current.busy) {
                event.preventDefault();
                stateRef.current.onClose?.();
                return;
            }
            if (event.key !== 'Tab' || !panelRef.current) return;

            const focusable = [...panelRef.current.querySelectorAll(FOCUSABLE)];
            if (focusable.length === 0) {
                event.preventDefault();
                panelRef.current.focus();
                return;
            }
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (!panelRef.current.contains(document.activeElement)) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
            } else if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        document.addEventListener('keydown', onKeyDown);
        return () => {
            window.clearTimeout(focusTimer);
            document.removeEventListener('keydown', onKeyDown);
            const stackIndex = modalStack.lastIndexOf(modalId);
            if (stackIndex !== -1) modalStack.splice(stackIndex, 1);
            if (modalStack.length === 0) document.body.style.overflow = originalBodyOverflow;
            if (previouslyFocusedRef.current?.isConnected) previouslyFocusedRef.current.focus();
        };
    }, [open, initialFocusRef]);

    if (!open || typeof document === 'undefined') return null;

    function requestClose() {
        if (!busy) onClose?.();
    }

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-end justify-center overflow-hidden sm:items-center sm:p-4">
            <div
                className="absolute inset-0 bg-navy/40 backdrop-blur-[2px]"
                onMouseDown={(event) => {
                    if (closeOnBackdrop && event.target === event.currentTarget) requestClose();
                }}
                aria-hidden="true"
            />
            <div
                ref={panelRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                aria-describedby={ariaDescribedBy ?? (description ? descriptionId : undefined)}
                aria-busy={busy || undefined}
                tabIndex={-1}
                className={`relative flex max-h-[min(90dvh,48rem)] w-full ${SIZE[size] ?? SIZE.md} flex-col overflow-hidden rounded-t-3xl border border-border bg-surface shadow-2xl outline-none sm:rounded-2xl`}
            >
                {bare ? (
                    <>
                        <h2 id={titleId} className="sr-only">{title}</h2>
                        <div className="min-h-0 flex-1 overflow-y-auto px-6 py-8 sm:px-8">{children}</div>
                    </>
                ) : (
                    <>
                        <div className="flex shrink-0 items-start justify-between gap-3 border-b border-border px-4 py-4 sm:px-5">
                            <div className="min-w-0">
                                <h2 id={titleId} className="break-words text-base font-bold tracking-tight text-text">{title}</h2>
                                {description && <p id={descriptionId} className="mt-1 break-words text-sm leading-relaxed text-text-muted">{description}</p>}
                            </div>
                            <button type="button" onClick={requestClose} disabled={busy} aria-label="Tutup dialog" className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-text-muted transition hover:bg-bg hover:text-text disabled:cursor-not-allowed disabled:opacity-40 sm:h-9 sm:w-9">
                                <FiX className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">{children}</div>
                        {footer && <div className="flex shrink-0 flex-col-reverse gap-2 border-t border-border bg-surface px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:flex-row sm:flex-wrap sm:justify-end sm:px-5">{footer}</div>}
                    </>
                )}
            </div>
        </div>,
        document.body,
    );
}
