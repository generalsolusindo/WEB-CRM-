/**
 * Validasi ukuran file di sisi klien sebelum upload, supaya user langsung
 * lihat pesan yang jelas ("melebihi X MB") alih-alih menunggu server
 * menolak dengan pesan generik.
 */
export function validateFileSize(file, maxMb) {
    if (!file) return null;
    const maxBytes = maxMb * 1024 * 1024;
    if (file.size > maxBytes) {
        const sizeMb = (file.size / 1024 / 1024).toFixed(1);
        return `Ukuran file "${file.name}" (${sizeMb} MB) melebihi batas maksimal ${maxMb} MB.`;
    }
    return null;
}

/** Pasang ke onChange input file tunggal: pickFile(form, 'field', e.target.files[0], 5) */
export function pickFile(form, field, file, maxMb) {
    if (!file) {
        form.setData(field, null);
        form.clearErrors(field);
        return;
    }
    const error = validateFileSize(file, maxMb);
    if (error) {
        form.setData(field, null);
        form.setError(field, error);
        return;
    }
    form.clearErrors(field);
    form.setData(field, file);
}

/** Pasang ke onChange input file multiple: pickFiles(form, 'documents', e.target.files, 5) */
export function pickFiles(form, field, fileList, maxMb) {
    const files = Array.from(fileList || []);
    const error = files.map((f) => validateFileSize(f, maxMb)).find(Boolean);
    form.clearErrors(field);
    if (error) {
        form.setData(field, []);
        form.setError(field, error);
        return;
    }
    form.setData(field, files);
}
