import { useState } from 'react';
import Button from './Button';

const DATE_CLS = 'rounded-lg border border-border-strong bg-surface px-3 py-2 text-sm text-text outline-none transition hover:border-text-faint focus:border-primary focus:shadow-[0_0_0_3px_var(--color-primary-ring)]';

/** Filter rentang tanggal (dari—sampai) dipakai di halaman monitoring/tracking. */
export default function DateRangeFilter({ from: initialFrom, to: initialTo, onApply, onReset }) {
    const [from, setFrom] = useState(initialFrom || '');
    const [to, setTo] = useState(initialTo || '');

    function submit(e) {
        e.preventDefault();
        onApply(from, to);
    }

    function reset() {
        setFrom('');
        setTo('');
        onReset();
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-center gap-2">
            <span className="text-sm font-medium text-text-muted">Tanggal:</span>
            <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className={DATE_CLS} />
            <span className="text-sm text-text-faint">—</span>
            <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className={DATE_CLS} />
            <Button type="submit" variant="outline" className="text-sm">Terapkan</Button>
            {(initialFrom || initialTo) && (
                <Button type="button" variant="ghost" className="text-sm" onClick={reset}>Reset</Button>
            )}
        </form>
    );
}
