@php
    $rupiah = fn ($v) => 'Rp '.number_format((float) $v, 2, ',', '.');
    $phaseLabel = $docTitle;
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%';
    $hasDiscount = ($totals['discount'] ?? 0) > 0;
    $hasTax = $invoice->lines->contains(fn ($l) => (float) $l->tax_rate > 0);
    $grandTotal = $totals['grand_total'];
    $forPdf = $forPdf ?? false;
    $lineCols = 4 + ($hasDiscount ? 1 : 0) + ($hasTax ? 1 : 0) + 1;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', Arial, sans-serif; color: #001B3A; font-size: 12px; background: {{ $forPdf ? '#fff' : '#f1f5f9' }}; }
        .sheet { background: #fff; {{ $forPdf ? '' : 'width: 210mm; min-height: 297mm; margin: 12px auto; padding: 18mm;' }} }
        .toolbar { width: 210mm; margin: 12px auto 0; text-align: right; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 6px; background: #001B3A; color: #fff; font-size: 12px; cursor: pointer; }
        .head-tbl { width: 100%; border-bottom: 2px solid #001B3A; padding-bottom: 8px; margin-bottom: 4px; }
        .head-tbl td { vertical-align: top; border: 0; padding: 0; }
        h1 { font-size: 20px; letter-spacing: 1px; }
        .doc { text-align: right; }
        .doc .title { font-size: 16px; font-weight: 700; }
        .doc .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748B; margin-top: 6px; }
        .doc .num { font-size: 14px; font-weight: 700; }
        .parties { width: 100%; margin: 18px 0; }
        .parties td { vertical-align: top; width: 50%; border: 0; padding: 0 12px 0 0; }
        .parties .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748B; margin-bottom: 4px; }
        .items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .items th, .items td { padding: 8px 10px; border-bottom: 1px solid #E2E8F0; text-align: left; }
        .items th { background: #F8FAFC; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #64748B; }
        .items td.num, .items th.num { text-align: right; }
        .summary { width: 100%; margin-top: 14px; }
        .summary .sbox { width: 46%; border-collapse: collapse; }
        .summary .sbox td { border: 0; padding: 4px 10px; }
        .summary .sbox td.num { text-align: right; }
        .summary .sbox tr.grand td { border-top: 2px solid #001B3A; font-size: 14px; font-weight: 700; }
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
        @page { size: A4; margin: 16mm; }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
    @endunless
    <div class="sheet">
        <table class="head-tbl">
            <tr>
                <td>
                    <h1>GENERAL SOLUSINDO</h1>
                    <div class="muted">Jl. Contoh No. 123, Jakarta &middot; info@generalsolusindo.com</div>
                </td>
                <td class="doc">
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
                </td>
            </tr>
        </table>

        <table class="parties">
            <tr>
                <td>
                    <div class="label">Ditagihkan Kepada</div>
                    <div><strong>{{ $customer?->name ?? '—' }}</strong></div>
                    @if ($customer?->company_name)<div>{{ $customer->company_name }}</div>@endif
                    @if ($customer?->address)<div class="muted">{{ $customer->address }}</div>@endif
                    @if ($customer?->npwp)<div class="muted">NPWP: {{ $customer->npwp }}</div>@endif
                </td>
                <td>
                    <div class="label">Referensi</div>
                    <div>{{ $reference }}</div>
                    @if ($customer?->email)<div>{{ $customer->email }}</div>@endif
                    @if ($customer?->phone)<div>{{ $customer->phone }}</div>@endif
                </td>
            </tr>
        </table>

        <table class="items">
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

        <table class="summary">
            <tr>
                <td>&nbsp;</td>
                <td style="width:46%">
                    <table class="sbox">
                        @if ($hasDiscount)
                            <tr><td class="muted">Subtotal Bruto</td><td class="num">{{ $rupiah($totals['gross']) }}</td></tr>
                            <tr><td class="muted">Total Diskon ({{ $pct($totals['discount_percent']) }})</td><td class="num">− {{ $rupiah($totals['discount']) }}</td></tr>
                        @endif
                        <tr><td class="muted">{{ $hasTax ? 'DPP' : 'Subtotal' }}</td><td class="num">{{ $rupiah($totals['subtotal']) }}</td></tr>
                        @if ($hasTax)<tr><td class="muted">Total PPN</td><td class="num">{{ $rupiah($totals['tax']) }}</td></tr>@endif
                        @php $pph23 = (float) ($totals['pph23_amount'] ?? 0); @endphp
                        <tr class="grand"><td>{{ $pph23 > 0 ? 'Nilai Faktur' : 'Grand Total' }}</td><td class="num">{{ $rupiah($grandTotal) }}</td></tr>
                        @if ($pph23 > 0)
                            <tr><td class="muted">PPh 23 ({{ $pct($totals['pph23_rate']) }})</td><td class="num">− {{ $rupiah($pph23) }}</td></tr>
                            <tr class="grand"><td>Dibayar Customer</td><td class="num">{{ $rupiah($totals['payable']) }}</td></tr>
                        @endif
                        <tr><td class="muted">Sudah Dibayar (kas)</td><td class="num">{{ $rupiah($totalPaid) }}</td></tr>
                        <tr class="grand"><td>Sisa</td><td class="num">{{ $rupiah(($totals['payable'] ?? $grandTotal) - $totalPaid) }}</td></tr>
                        @if ($pph23BuktiPotong ?? null)
                            <tr><td class="muted" colspan="2" style="padding-top:6px">Bukti Potong PPh 23: {{ $pph23BuktiPotong }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

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
