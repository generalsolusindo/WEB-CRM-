import { FiAlertCircle, FiCheckCircle, FiHelpCircle, FiInfo } from 'react-icons/fi';
import Button from './Button';
import Modal from './Modal';

const TONES = {
    danger: { icon: FiAlertCircle, iconClass: 'bg-danger-soft text-danger', button: 'danger' },
    warning: { icon: FiAlertCircle, iconClass: 'bg-warning-soft text-warning', button: 'primary' },
    success: { icon: FiCheckCircle, iconClass: 'bg-success-soft text-success', button: 'primary' },
    info: { icon: FiInfo, iconClass: 'bg-primary-soft text-primary-strong', button: 'primary' },
    neutral: { icon: FiHelpCircle, iconClass: 'bg-bg text-text-muted', button: 'primary' },
};

export default function ConfirmDialog({
    open, onClose, onConfirm, title, description, children, tone = 'warning',
    confirmLabel = 'Lanjutkan', cancelLabel = 'Batal', processing = false, disabled = false,
}) {
    const config = TONES[tone] ?? TONES.warning;
    const Icon = config.icon;

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            size="sm"
            busy={processing}
            footer={(
                <>
                    <Button variant="outline" onClick={onClose} disabled={processing} className="w-full sm:w-auto">{cancelLabel}</Button>
                    <Button variant={config.button} onClick={onConfirm} loading={processing} disabled={disabled} className="w-full sm:w-auto">{confirmLabel}</Button>
                </>
            )}
        >
            <div className="flex items-start gap-3">
                <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl ${config.iconClass}`}>
                    <Icon className="h-5 w-5" />
                </span>
                <div className="min-w-0 flex-1 break-words text-sm leading-relaxed text-text-muted">
                    {description && <p>{description}</p>}
                    {children}
                </div>
            </div>
        </Modal>
    );
}
