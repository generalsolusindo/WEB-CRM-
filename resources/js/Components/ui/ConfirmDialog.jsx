import AlertDialog from './AlertDialog';

/** Tone lama (danger/warning/success/info/neutral) dipetakan ke ikon & warna AlertDialog. */
const TONE_MAP = { danger: 'danger', warning: 'warning', success: 'success', info: 'info', neutral: 'question' };

export default function ConfirmDialog({
    open, onClose, onConfirm, title, description, children, tone = 'warning',
    confirmLabel = 'Lanjutkan', cancelLabel = 'Batal', processing = false, disabled = false,
}) {
    return (
        <AlertDialog
            open={open}
            onClose={onClose}
            onConfirm={onConfirm}
            tone={TONE_MAP[tone] ?? 'warning'}
            title={title}
            text={description}
            confirmLabel={confirmLabel}
            cancelLabel={cancelLabel}
            showCancel
            processing={processing}
            disabled={disabled}
        >
            {children}
        </AlertDialog>
    );
}
