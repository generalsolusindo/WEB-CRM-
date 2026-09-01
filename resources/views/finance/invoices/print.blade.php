@php
    $rupiah = fn ($v) => 'Rp '.number_format((float) $v, 2, ',', '.');
    $phaseLabel = $docTitle;
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%';
    $hasDiscount = ($totals['discount'] ?? 0) > 0;
    $hasTax = $invoice->lines->contains(fn ($l) => (float) $l->tax_rate > 0);
    $grandTotal = $totals['grand_total'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', Arial, sans-serif; color: #001B3A; font-size: 12px; background: #f1f5f9; }
        .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 18mm; background: #fff; }
        .toolbar { width: 210mm; margin: 12px auto 0; text-align: right; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 6px; background: #001B3A; color: #fff; font-size: 12px; cursor: pointer; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #001B3A; padding-bottom: 12px; }
        header h1 { font-size: 20px; letter-spacing: 1px; }
        header .doc { text-align: right; }
        header .doc .title { font-size: 16px; font-weight: 700; }
        header .doc .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748B; margin-top: 6px; }
        header .doc .num { font-size: 14px; font-weight: 700; }
        .parties { display: flex; justify-content: space-between; margin: 18px 0; }
        .parties .block { width: 48%; }
        .parties .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748B; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #E2E8F0; text-align: left; }
        th { background: #F8FAFC; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #64748B; }
        td.num, th.num { text-align: right; }
        tfoot td { border-bottom: 0; }
        tfoot tr.grand td { border-top: 2px solid #001B3A; font-size: 14px; font-weight: 700; }
        .summary { margin-top: 16px; display: flex; justify-content: flex-end; }
        .summary table { width: 45%; }
        .summary td { border: 0; padding: 4px 10px; }
        .notes { margin-top: 20px; }
        .notes .label, .pay .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748B; margin-bottom: 4px; }
        .pay { margin-top: 16px; }
        .muted { color: #64748B; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge.paid { background: #dcfce7; color: #166534; }
        .badge.unpaid { background: #fef9c3; color: #854d0e; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; width: auto; min-height: auto; padding: 0; }
            @page { size: A4; margin: 16mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
    <div class="sheet">
        <header>
            <div>
                <h1>GENERAL SOLUSINDO</h1>
                <div class="muted">Jl. Contoh No. 123, Jakarta &middot; info@generalsolusindo.com</div>
            </div>
            <div class="doc">
                <div class="title">{{ $phaseLabel }}</div>
                <div class="label">Nomor Invoice</div>
                <div class="num">{{ $invoice->number }}</div>
                <div class="muted">Tanggal: {{ $invoice->created_at?->format('d M Y') }}</div>
                @if ($invoice->due_date)
                    <div class="muted">Jatuh tempo: {{ \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d M Y') }}</div>
                @endif
                <div style="margin-top:6px">
                    <span class="badge {{ $invoice->status === 'paid' ? 'paid' : 'unpaid' }}">
                        {{ $invoice->status === 'paid' ? 'LUNAS' : ($invoice->status === 'partially_paid' ? 'DIBAYAR SEBAGIAN' : 'BELUM LUNAS') }}
                    </span>
                </div>
            </div>
        </header>

        <div class="parties">
            <div class="block">
                <div class="label">Ditagihkan Kepada</div>
                <div><strong>{{ $customer?->name ?? '—' }}</strong></div>
                @if ($customer?->company_name)<div>{{ $customer->company_name }}</div>@endif
                @if ($customer?->address)<div class="muted">{{ $customer->address }}</div>@endif
                @if ($customer?->npwp)<div class="muted">NPWP: {{ $customer->npwp }}</div>@endif
            </div>
            <div class="block">
                <div class="label">Referensi</div>
                <div>{{ $reference }}</div>
                @if ($customer?->email)<div>{{ $customer->email }}</div>@endif
                @if ($customer?->phone)<div>{{ $customer->phone }}</div>@endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:36px">No</th>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th class="num">Harga Satuan</th>
                    @if ($hasDiscount)<th class="num">Diskon</th>@endif
                    @if ($hasTax)<th>Pajak</th>@endif
                    <th class="num">DPP</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lines as $i => $line)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $line->item_name }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="num">{{ $rupiah($line->unit_price) }}</td>
                        @if ($hasDiscount)<td class="num">{{ (float) $line->discount_amount > 0 ? $rupiah($line->discount_amount) : '—' }}</td>@endif
                        @if ($hasTax)<td>{{ $line->tax ? $line->tax->name : ((float) $line->tax_rate > 0 ? $pct($line->tax_rate) : '—') }}</td>@endif
                        <td class="num">{{ $rupiah($line->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <table>
                @if ($hasDiscount)
                    <tr><td class="muted">Subtotal Bruto</td><td class="num">{{ $rupiah($totals['gross']) }}</td></tr>
                    <tr><td class="muted">Total Diskon ({{ $pct($totals['discount_percent']) }})</td><td class="num">− {{ $rupiah($totals['discount']) }}</td></tr>
                @endif
                <tr><td class="muted">{{ $hasTax ? 'DPP' : 'Subtotal' }}</td><td class="num">{{ $rupiah($totals['subtotal']) }}</td></tr>
                @if ($hasTax)<tr><td class="muted">Total PPN</td><td class="num">{{ $rupiah($totals['tax']) }}</td></tr>@endif
                <tr class="grand"><td>Grand Total</td><td class="num">{{ $rupiah($grandTotal) }}</td></tr>
                <tr><td class="muted">Sudah Dibayar</td><td class="num">{{ $rupiah($totalPaid) }}</td></tr>
                <tr class="grand"><td>Sisa Tagihan</td><td class="num">{{ $rupiah($grandTotal - $totalPaid) }}</td></tr>
            </table>
        </div>

        @if ($invoice->payments->isNotEmpty())
            <div class="pay">
                <div class="label">Riwayat Pembayaran</div>
                @foreach ($invoice->payments as $payment)
                    <div>{{ \Illuminate\Support\Carbon::parse($payment->paid_at)->format('d M Y H:i') }} &middot; {{ $rupiah($payment->amount_paid) }}{{ $payment->notes ? ' — '.$payment->notes : '' }}</div>
                @endforeach
            </div>
        @endif

        <div class="notes">
            <div class="label">Pembayaran</div>
            <div>Transfer ke rekening BCA 1234567890 a.n. PT General Solusindo. Cantumkan nomor invoice pada berita transfer.</div>
        </div>
    </div>
</body>
</html>
