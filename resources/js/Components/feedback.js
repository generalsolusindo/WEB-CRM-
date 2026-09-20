import { router } from '@inertiajs/react';

/**
 * Pusat pop-up aplikasi (konfirmasi, hasil aksi, toast). Berupa store sederhana di luar React
 * supaya bisa dipanggil dari halaman mana pun, dan pesan dari server (flash) otomatis tampil
 * tanpa tiap halaman menanganinya sendiri. Tiap tombol bisa mengatur judul/teks/ikon sendiri.
 */
const TOAST_MS = 4500;

let state = { dialog: null, toasts: [] };
const listeners = new Set();
const dialogQueue = [];
let toastSeq = 0;
let override = null;

function setState(next) {
    state = { ...state, ...next };
    listeners.forEach((listener) => listener());
}

export const getState = () => state;

export function subscribe(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

function openDialog(dialog) {
    return new Promise((resolve) => {
        const entry = { ...dialog, resolve };
        if (state.dialog) dialogQueue.push(entry);
        else setState({ dialog: entry });
    });
}

export function closeDialog(result) {
    const current = state.dialog;
    setState({ dialog: dialogQueue.shift() ?? null });
    current?.resolve(result);
}

export function dismissToast(id) {
    setState({ toasts: state.toasts.filter((toast) => toast.id !== id) });
}

/** Kotak konfirmasi sebelum aksi berisiko. Mengembalikan Promise<boolean>. */
function confirm(options = {}) {
    return openDialog({
        kind: 'confirm', tone: 'question', title: 'Yakin?', confirmLabel: 'Ya, lanjutkan', cancelLabel: 'Batal', ...options,
    }).then(Boolean);
}

/** Pop-up hasil (centang hijau / silang merah / dll) dengan tombol OK. */
function alert(options = {}) {
    return openDialog({ kind: 'alert', tone: 'info', confirmLabel: 'OK', ...options });
}

/** Notifikasi kecil yang hilang sendiri — untuk aksi ringan seperti simpan biasa. */
function toast({ tone = 'success', title, text }) {
    const id = ++toastSeq;
    setState({ toasts: [...state.toasts, { id, tone, title, text }] });
    window.setTimeout(() => dismissToast(id), TOAST_MS);
}

/**
 * Tampilkan pesan flash dari server. `override` (diatur oleh act()) menentukan judul/teks/gaya
 * khusus untuk tombol yang baru diklik; tanpa itu: sukses -> toast, gagal -> pop-up.
 */
function handleFlash(flash) {
    const custom = override;
    override = null;
    if (!flash) return;

    if (flash.error) {
        alert({ tone: 'error', title: custom?.error?.title ?? 'Tidak berhasil', text: custom?.error?.text ?? flash.error });
    }

    if (flash.success) {
        const spec = custom?.success ?? {};
        const payload = { tone: 'success', title: spec.title ?? 'Berhasil', text: spec.text ?? flash.success };
        if (spec.style === 'popup') alert({ ...payload, confirmLabel: spec.confirmLabel ?? 'OK' });
        else toast(payload);
    }
}

/**
 * Atur pop-up hasil untuk kunjungan Inertia BERIKUTNYA (untuk halaman yang mengirim lewat
 * useForm/router sendiri, mis. supaya error validasi tetap tampil di kolom form).
 * Dibersihkan otomatis begitu kunjungan selesai.
 */
function expect({ success, error } = {}) {
    override = { success, error };
}

/**
 * Jalankan aksi (kirim/hapus/setujui/...) dengan pop-up yang disesuaikan per tombol:
 *   feedback.act({
 *     method: 'post', url, data,
 *     confirm: { title, text, tone, confirmLabel },        // opsional: tanya dulu
 *     success: { title, text, style: 'popup' | 'toast' },  // opsional: gaya hasil sukses
 *     error:   { title, text },                            // opsional: judul pop-up gagal
 *     visit: {},                                           // opsi Inertia tambahan
 *   })
 */
async function act({ method = 'post', url, data = {}, confirm: confirmOptions, success, error, visit = {} }) {
    if (confirmOptions && !(await confirm(confirmOptions))) return false;

    expect({ success, error });
    const options = { preserveScroll: true, ...visit };

    if (method === 'delete') router.delete(url, { ...options, data });
    else router[method](url, data, options);

    return true;
}

let installed = false;

/** Pasang pendengar sekali: setiap kunjungan Inertia yang sukses membawa flash -> tampilkan. */
export function installFlashListener(initialFlash) {
    if (installed) return;
    installed = true;
    router.on('success', (event) => handleFlash(event.detail.page.props.flash));
    router.on('finish', () => { override = null; });
    handleFlash(initialFlash);
}

export const feedback = { confirm, alert, toast, act, expect, handleFlash };

export function useFeedback() {
    return feedback;
}
