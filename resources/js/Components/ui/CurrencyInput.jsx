import { useLayoutEffect, useRef } from 'react';

function onlyDigits(str) {
    return String(str ?? '').replace(/[^\d]/g, '');
}

/** Ambil bagian bulat (buang desimal) dari value eksternal, mis. "1000000.00" → "1000000". */
function integerDigits(value) {
    if (value === '' || value === null || value === undefined) return '';
    return onlyDigits(String(value).split('.')[0]);
}

function formatDots(digits) {
    return digits ? Number(digits).toLocaleString('id-ID') : '';
}

/**
 * Input nominal rupiah dengan titik ribuan otomatis saat mengetik
 * (mis. "1.500.000"). Nilai yang dikirim lewat onChange tetap angka polos
 * tanpa titik ("1500000") lewat `e.target.value` seperti input biasa —
 * jadi bisa langsung menggantikan <input type="number"> tanpa mengubah
 * handler di pemanggilnya. Selalu dibulatkan ke rupiah penuh (tanpa desimal).
 */
export default function CurrencyInput({ value, onChange, className = '', type: _ignoredType, ...props }) {
    const inputRef = useRef(null);
    const pendingCaretDigits = useRef(null);

    function handleChange(e) {
        const raw = e.target.value;
        const cursor = e.target.selectionStart ?? raw.length;
        pendingCaretDigits.current = onlyDigits(raw.slice(0, cursor)).length;

        const digits = onlyDigits(raw);
        const normalized = digits === '' ? '' : String(Number(digits));

        onChange({ target: { value: normalized }, currentTarget: { value: normalized } });
    }

    const display = formatDots(integerDigits(value));

    // Pertahankan posisi kursor berdasarkan jumlah digit di depannya —
    // supaya titik ribuan yang baru muncul/hilang tidak melempar kursor ke ujung.
    useLayoutEffect(() => {
        if (pendingCaretDigits.current === null || !inputRef.current) return;
        const target = pendingCaretDigits.current;
        let pos = display.length;
        if (target === 0) {
            pos = 0;
        } else {
            let seen = 0;
            for (let i = 0; i < display.length; i++) {
                if (/\d/.test(display[i])) seen++;
                if (seen === target) {
                    pos = i + 1;
                    break;
                }
            }
        }
        inputRef.current.setSelectionRange(pos, pos);
        pendingCaretDigits.current = null;
    });

    return (
        <input
            ref={inputRef}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            value={display}
            onChange={handleChange}
            className={className}
            {...props}
        />
    );
}
