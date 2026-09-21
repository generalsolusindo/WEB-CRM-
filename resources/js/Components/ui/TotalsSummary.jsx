function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value || 0));
}

function Row({ label, value, tone = '', small = false }) {
    return (
        <div className={`flex items-baseline justify-between gap-4 ${small ? 'text-xs' : 'text-sm'}`}>
            <span className="text-text-muted">{label}</span>
            <span className={`text-right font-medium tabular-nums ${tone || 'text-text'}`}>{value}</span>
        </div>
    );
}

/**
 * Ringkasan total dokumen (Quotation / Invoice / Sales Order) sebagai blok terpisah di bawah tabel,
 * bukan <tfoot> di dalam tabel — supaya di layar sempit total tidak ikut tergulung ke samping.
 * `totals` berformat sama dengan yang dipakai Totals lama.
 */
export default function TotalsSummary({ totals, className = '' }) {
    const hasDiscount = Number(totals.discount) > 0;

    return (
        <div className={`space-y-1.5 border-t border-border bg-surface-2 px-4 py-4 ${className}`.trim()}>
            <Row label="Subtotal Bruto" value={money(totals.gross)} />
            <Row
                label={`Total Diskon${hasDiscount ? ` (${totals.discount_percent}%)` : ''}`}
                value={hasDiscount ? `− ${money(totals.discount)}` : money(0)}
                tone={hasDiscount ? 'text-danger' : ''}
            />
            <Row label="DPP" value={money(totals.subtotal)} />
            <Row label="Total PPN" value={money(totals.tax)} />
            <div className="flex items-baseline justify-between gap-4 border-t border-border pt-2">
                <span className="font-semibold text-text">Grand Total</span>
                <span className="text-right text-lg font-bold tabular-nums text-text">{money(totals.grand_total)}</span>
            </div>
            {Number(totals.pph23_estimate) > 0 && (
                <>
                    <Row small label="Estimasi PPh 23 (2%) — jika customer memotong" value={`− ${money(totals.pph23_estimate)}`} tone="text-warning" />
                    <Row small label="Estimasi diterima tunai" value={money(Number(totals.grand_total) - Number(totals.pph23_estimate))} />
                </>
            )}
            {totals.margin_percent != null && (
                <Row
                    label="Estimasi margin keseluruhan"
                    value={`${money(totals.margin_amount)} (${totals.margin_percent}%)`}
                    tone={Number(totals.margin_percent) < 0 ? 'text-danger' : 'text-success'}
                />
            )}
        </div>
    );
}
