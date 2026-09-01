import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * Dropdown dengan kotak pencarian bawaan.
 *
 * props:
 *  - value        : nilai terpilih (string|number|'')
 *  - onChange(v)  : dipanggil dengan value opsi yang dipilih
 *  - options      : [{ value, label }]
 *  - placeholder  : teks saat belum ada pilihan
 *  - disabled     : boolean
 *  - emptyText    : teks saat daftar opsi kosong
 */
export default function SearchableSelect({
    value = '',
    onChange,
    options = [],
    placeholder = '— pilih —',
    disabled = false,
    emptyText = 'Tidak ada pilihan.',
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const rootRef = useRef(null);
    const inputRef = useRef(null);

    const selected = options.find((o) => String(o.value) === String(value)) || null;

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return options;
        return options.filter((o) => o.label.toLowerCase().includes(q));
    }, [options, query]);

    useEffect(() => {
        if (!open) return;
        function onDocClick(e) {
            if (rootRef.current && !rootRef.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onDocClick);
        return () => document.removeEventListener('mousedown', onDocClick);
    }, [open]);

    useEffect(() => {
        if (open) {
            setQuery('');
            setTimeout(() => inputRef.current?.focus(), 0);
        }
    }, [open]);

    function pick(v) {
        onChange?.(v);
        setOpen(false);
    }

    return (
        <div ref={rootRef} className="relative mt-1">
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center justify-between rounded-lg border border-border bg-surface px-3 py-2 text-left text-sm outline-none focus:border-navy disabled:bg-bg disabled:text-text-muted"
            >
                <span className={selected ? 'text-text' : 'text-text-muted'}>{selected ? selected.label : placeholder}</span>
                <span className="ml-2 text-text-muted">▾</span>
            </button>

            {open && !disabled && (
                <div className="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-border bg-surface shadow-lg">
                    <div className="border-b border-border p-2">
                        <input
                            ref={inputRef}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Escape') setOpen(false);
                                if (e.key === 'Enter' && filtered.length === 1) { e.preventDefault(); pick(filtered[0].value); }
                            }}
                            placeholder="Cari…"
                            className="w-full rounded-md border border-border px-2 py-1.5 text-sm outline-none focus:border-navy"
                        />
                    </div>
                    <ul className="max-h-56 overflow-y-auto py-1 text-sm">
                        {value !== '' && (
                            <li>
                                <button type="button" onClick={() => pick('')} className="block w-full px-3 py-1.5 text-left text-text-muted hover:bg-bg">
                                    {placeholder}
                                </button>
                            </li>
                        )}
                        {filtered.map((o) => (
                            <li key={o.value}>
                                <button
                                    type="button"
                                    onClick={() => pick(o.value)}
                                    className={`block w-full px-3 py-1.5 text-left hover:bg-bg ${String(o.value) === String(value) ? 'bg-navy/10 font-medium text-navy' : 'text-text'}`}
                                >
                                    {o.label}
                                </button>
                            </li>
                        ))}
                        {filtered.length === 0 && (
                            <li className="px-3 py-2 text-xs text-text-muted">{options.length === 0 ? emptyText : 'Tidak cocok dengan pencarian.'}</li>
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}
