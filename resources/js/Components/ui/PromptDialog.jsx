import { useEffect, useId, useRef, useState } from 'react';
import Button from './Button';
import Modal from './Modal';

export default function PromptDialog({
    open, onClose, onConfirm, title, description, label, initialValue = '', placeholder,
    inputType = 'text', multiline = false, required = false, maxLength, error,
    processing = false, confirmLabel = 'Simpan', cancelLabel = 'Batal', confirmVariant = 'primary',
}) {
    const [value, setValue] = useState(initialValue ?? '');
    const inputRef = useRef(null);
    const formId = useId();
    const errorId = useId();

    useEffect(() => {
        if (open) setValue(initialValue ?? '');
    }, [open, initialValue]);

    function submit(event) {
        event.preventDefault();
        const normalized = typeof value === 'string' ? value.trim() : value;
        if (required && !normalized) return;
        onConfirm?.(normalized);
    }

    const controlProps = {
        ref: inputRef,
        value,
        onChange: (event) => setValue(event.target.value),
        placeholder,
        maxLength,
        disabled: processing,
        className: `input ${error ? 'border-danger focus:border-danger' : ''}`,
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? errorId : undefined,
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            description={description}
            size="sm"
            busy={processing}
            initialFocusRef={inputRef}
            footer={(
                <>
                    <Button variant="outline" onClick={onClose} disabled={processing} className="w-full sm:w-auto">{cancelLabel}</Button>
                    <Button type="submit" form={formId} variant={confirmVariant} loading={processing} disabled={required && !value.trim()} className="w-full sm:w-auto">{confirmLabel}</Button>
                </>
            )}
        >
            <form id={formId} onSubmit={submit}>
                <label className="block text-sm font-medium text-text">
                    {label}
                    {multiline ? <textarea {...controlProps} rows="4" /> : <input {...controlProps} type={inputType} />}
                </label>
                <div className="mt-1 flex items-start justify-between gap-3">
                    {error ? <span id={errorId} className="text-xs text-danger">{error}</span> : <span />}
                    {maxLength && <span className="shrink-0 text-xs text-text-faint">{value.length}/{maxLength}</span>}
                </div>
            </form>
        </Modal>
    );
}
