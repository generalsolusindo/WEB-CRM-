/**
 * Validasi ukuran file di sisi klien sebelum upload, supaya user langsung
 * lihat pesan yang jelas ("melebihi X MB") alih-alih menunggu server
 * menolak dengan pesan generik.
 *
 * Foto (JPG/PNG) otomatis dikecilkan dulu sebelum dicek ukurannya: foto kamera HP
 * bisa 3-10 MB, padahal server/proxy (nginx) sering membatasi body request jauh lebih
 * kecil dan menolak dengan halaman error mentah. Setelah dikecilkan biasanya cuma
 * beberapa ratus KB, jadi unggahan cepat dan lolos.
 */
const COMPRESS_ABOVE_BYTES = 800 * 1024;
const MAX_IMAGE_SIDE = 1920;
const JPEG_QUALITY = 0.8;

export function validateFileSize(file, maxMb) {
    if (!file) return null;
    const maxBytes = maxMb * 1024 * 1024;
    if (file.size > maxBytes) {
        const sizeMb = (file.size / 1024 / 1024).toFixed(1);
        return `Ukuran file "${file.name}" (${sizeMb} MB) melebihi batas maksimal ${maxMb} MB.`;
    }
    return null;
}

function isCompressibleImage(file) {
    return ['image/jpeg', 'image/png'].includes(file.type) && file.size > COMPRESS_ABOVE_BYTES;
}

/**
 * Kecilkan foto (sisi terpanjang max 1920px, JPEG kualitas 0.8). Kalau gagal atau hasilnya
 * malah lebih besar, kembalikan file aslinya — tidak pernah membuat unggahan gagal.
 */
export async function shrinkImage(file) {
    if (!file || !isCompressibleImage(file)) return file;

    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, MAX_IMAGE_SIDE / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close?.();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));
        if (!blob || blob.size >= file.size) return file;

        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
    } catch {
        return file;
    }
}

/** Pasang ke onChange input file tunggal: pickFile(form, 'field', e.target.files[0], 5) */
export async function pickFile(form, field, file, maxMb) {
    if (!file) {
        form.setData(field, null);
        form.clearErrors(field);
        return;
    }
    const prepared = await shrinkImage(file);
    const error = validateFileSize(prepared, maxMb);
    if (error) {
        form.setData(field, null);
        form.setError(field, error);
        return;
    }
    form.clearErrors(field);
    form.setData(field, prepared);
}

/** Pasang ke onChange input file multiple: pickFiles(form, 'documents', e.target.files, 5) */
export async function pickFiles(form, field, fileList, maxMb) {
    const files = await Promise.all(Array.from(fileList || []).map(shrinkImage));
    const error = files.map((f) => validateFileSize(f, maxMb)).find(Boolean);
    form.clearErrors(field);
    if (error) {
        form.setData(field, []);
        form.setError(field, error);
        return;
    }
    form.setData(field, files);
}
