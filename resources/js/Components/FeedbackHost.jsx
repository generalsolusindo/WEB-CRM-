import { useSyncExternalStore } from 'react';
import { createPortal } from 'react-dom';
import { FiAlertCircle, FiCheckCircle, FiInfo, FiX } from 'react-icons/fi';
import AlertDialog from './ui/AlertDialog';
import { closeDialog, dismissToast, getState, subscribe } from './feedback';

const TOAST_TONES = {
    success: { icon: FiCheckCircle, className: 'border-success/25 text-success' },
    error: { icon: FiAlertCircle, className: 'border-danger/25 text-danger' },
    info: { icon: FiInfo, className: 'border-primary/25 text-primary' },
};

/** Dipasang sekali di akar aplikasi: merender pop-up aktif dan tumpukan toast. */
export default function FeedbackHost() {
    const { dialog, toasts } = useSyncExternalStore(subscribe, getState);

    return (
        <>
            {dialog && (
                <AlertDialog
                    open
                    tone={dialog.tone}
                    title={dialog.title}
                    text={dialog.text}
                    confirmLabel={dialog.confirmLabel}
                    cancelLabel={dialog.cancelLabel}
                    showCancel={dialog.kind === 'confirm'}
                    onClose={() => closeDialog(false)}
                    onConfirm={() => closeDialog(true)}
                />
            )}
            {typeof document !== 'undefined' && createPortal(
                <div aria-live="polite" className="pointer-events-none fixed inset-x-0 top-3 z-[60] flex flex-col items-center gap-2 px-3 sm:inset-x-auto sm:right-4 sm:top-4 sm:items-end sm:px-0">
                    {toasts.map((toast) => {
                        const tone = TOAST_TONES[toast.tone] ?? TOAST_TONES.info;
                        const Icon = tone.icon;
                        return (
                            <div key={toast.id} role="status" className={`pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-surface px-4 py-3 shadow-lg ${tone.className}`}>
                                <Icon className="mt-0.5 h-5 w-5 shrink-0" />
                                <div className="min-w-0 flex-1 text-sm">
                                    {toast.title && <div className="font-semibold text-text">{toast.title}</div>}
                                    <div className="break-words text-text-muted">{toast.text}</div>
                                </div>
                                <button type="button" onClick={() => dismissToast(toast.id)} aria-label="Tutup" className="text-text-muted transition hover:text-text"><FiX className="h-4 w-4" /></button>
                            </div>
                        );
                    })}
                </div>,
                document.body,
            )}
        </>
    );
}
