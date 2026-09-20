import { useId } from 'react';
import { FiAlertTriangle, FiCheck, FiHelpCircle, FiInfo, FiX } from 'react-icons/fi';
import Button from './Button';
import Modal from './Modal';

/** Ikon besar bergaya SweetAlert: lingkaran berbingkai + ikon di tengah, warna mengikuti tone. */
const TONES = {
    success: { icon: FiCheck, ring: 'border-success/30 text-success', button: 'primary' },
    error: { icon: FiX, ring: 'border-danger/30 text-danger', button: 'danger' },
    danger: { icon: FiAlertTriangle, ring: 'border-danger/30 text-danger', button: 'danger' },
    warning: { icon: FiAlertTriangle, ring: 'border-warning/40 text-warning', button: 'primary' },
    info: { icon: FiInfo, ring: 'border-primary/30 text-primary', button: 'primary' },
    question: { icon: FiHelpCircle, ring: 'border-primary/30 text-primary', button: 'primary' },
};

/**
 * Pop-up di tengah layar: ikon besar, judul, teks, tombol. Dipakai untuk konfirmasi
 * (showCancel) maupun hasil aksi (hanya tombol OK). Isi tambahan (mis. kolom alasan) lewat children.
 */
export default function AlertDialog({
    open, onClose, onConfirm, tone = 'question', title, text, children,
    confirmLabel = 'OK', cancelLabel = 'Batal', showCancel = false,
    processing = false, disabled = false,
}) {
    const config = TONES[tone] ?? TONES.question;
    const Icon = config.icon;
    const textId = useId();

    return (
        <Modal open={open} onClose={onClose} title={title} size="sm" bare busy={processing} ariaDescribedBy={text ? textId : undefined}>
            <div className="flex flex-col items-center text-center">
                <span className={`flex h-20 w-20 items-center justify-center rounded-full border-4 ${config.ring}`}>
                    <Icon className="h-9 w-9" strokeWidth={2.5} />
                </span>
                <h3 className="mt-5 break-words text-2xl font-semibold tracking-tight text-text">{title}</h3>
                {text && <p id={textId} className="mt-2 break-words text-sm leading-relaxed text-text-muted">{text}</p>}
                {children && <div className="mt-4 w-full text-left text-sm">{children}</div>}
                <div className="mt-7 flex w-full flex-col-reverse justify-center gap-2 sm:flex-row">
                    {showCancel && (
                        <Button variant="outline" onClick={onClose} disabled={processing} className="w-full sm:w-auto sm:min-w-28">{cancelLabel}</Button>
                    )}
                    <Button variant={config.button} onClick={onConfirm ?? onClose} loading={processing} disabled={disabled} className="w-full sm:w-auto sm:min-w-28">{confirmLabel}</Button>
                </div>
            </div>
        </Modal>
    );
}
