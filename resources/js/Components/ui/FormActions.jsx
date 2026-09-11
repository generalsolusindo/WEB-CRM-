import Button from './Button';

export default function FormActions({
    cancelHref, onCancel, submitLabel = 'Simpan', processing = false, disabled = false, extra, align = 'end',
}) {
    return (
        <div className={`flex flex-wrap items-center gap-2 ${align === 'between' ? 'justify-between' : 'justify-end'}`}>
            {extra}
            {(cancelHref || onCancel) && (
                <Button variant="outline" href={cancelHref} onClick={onCancel} type="button">Batal</Button>
            )}
            <Button type="submit" loading={processing} disabled={disabled}>
                {processing ? 'Menyimpan…' : submitLabel}
            </Button>
        </div>
    );
}
