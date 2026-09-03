@php
    $rupiah = fn ($v) => 'Rp '.number_format(round((float) $v), 0, ',', '.');
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',').'%';
    $qtyFmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');

    $forPdf = $forPdf ?? false;
    $grouped = $grouped ?? false;
    $globalDiscount = $globalDiscount ?? false;

    $lineGross = fn ($l) => round((float) $l->subtotal + (float) $l->discount_amount, 2);
    $hasDiscount = ($totals['discount'] ?? 0) > 0;
    $showLineDiscount = $hasDiscount && ! $globalDiscount;
    $hasTax = $invoice->lines->contains(fn ($l) => (float) $l->tax_rate > 0);
    $taxRates = $invoice->lines->filter(fn ($l) => (float) $l->subtotal != 0)->map(fn ($l) => (float) $l->tax_rate)->unique();
    $ppnLabel = 'PPN'.($taxRates->count() === 1 ? ' ('.$pct($taxRates->first()).')' : ($hasTax ? '' : ' (0%)'));
    $pph23 = (float) ($totals['pph23_amount'] ?? 0);
    $pph23On = (bool) $invoice->pph23_enabled;

    $itemLines = $invoice->lines;
    $materials = $itemLines->filter(fn ($l) => $l->category === 'material');
    $services = $itemLines->filter(fn ($l) => in_array($l->category, ['service', 'reimburse'], true));

    // Kolom: No | Deskripsi | Qty | Satuan | Harga Satuan | [Diskon] | [Pajak] | Amount
    $cols = 6 + ($showLineDiscount ? 1 : 0) + ($hasTax ? 1 : 0);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #111827; font-size: 11px; background: {{ $forPdf ? '#fff' : '#f1f5f9' }}; }
        .sheet { background: #fff; {{ $forPdf ? '' : 'width: 210mm; min-height: 297mm; margin: 12px auto; padding: 16mm;' }} }
        .toolbar { width: 210mm; margin: 12px auto 0; text-align: right; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 6px; background: #001B3A; color: #fff; font-size: 12px; cursor: pointer; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }

        .top td { border: 0; padding: 0; }
        .brand-name { font-size: 20px; font-weight: 700; color: #001B3A; letter-spacing: .5px; }
        .brand-sub { font-size: 10px; font-style: italic; color: #374151; margin: 2px 0 6px; }
        .muted { color: #6B7280; }
        .doc-title { font-size: 22px; font-weight: 700; color: #9CA3AF; letter-spacing: 2px; text-align: right; }
        .meta { text-align: right; margin-top: 6px; }
        .meta span { display: inline-block; min-width: 110px; text-align: left; }

        .bill { margin-top: 14px; }
        .bill td { border: 0; padding: 0; width: 50%; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #6B7280; }

        .terms-tbl { margin-top: 12px; border: 1px solid #D1D5DB; }
        .terms-tbl th { background: #F3F4F6; border: 1px solid #D1D5DB; padding: 5px 7px; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; text-align: left; }
        .terms-tbl td { border: 1px solid #D1D5DB; padding: 5px 7px; }

        .items { margin-top: 12px; }
        .items th { background: #F3F4F6; border: 1px solid #D1D5DB; padding: 6px 7px; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; text-align: left; }
        .items td { border: 1px solid #D1D5DB; padding: 6px 7px; }
        .items .grp td { background: #E5E7EB; font-weight: 700; }
        .num { text-align: right; }

        .foot { margin-top: 10px; }
        .foot td { border: 0; padding: 0; }
        .pay-box { width: 55%; font-size: 10px; }
        .pay-box .bank { font-weight: 700; }
        .sum { width: 45%; }
        .sum td { padding: 3px 7px; border: 0; }
        .sum tr.rule td { border-top: 1px solid #9CA3AF; }
        .sum tr.grand td { border-top: 2px solid #001B3A; font-weight: 700; font-size: 12px; }

        .tc { margin-top: 14px; font-size: 10px; }
        .tc li { margin-left: 16px; }
        .sign { margin-top: 26px; width: 240px; }
        .sign .space { height: 54px; }
        .sign .name { border-top: 1px solid #111827; padding-top: 3px; font-weight: 700; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .badge.paid { background: #dcfce7; color: #166534; }
        .badge.unpaid { background: #fef9c3; color: #854d0e; }
        @page { size: A4; margin: 14mm; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; width: auto; min-height: auto; padding: 0; } }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
    @endunless
    <div class="sheet">

        <table class="top">
            <tr>
                <td style="width:58%">
                    <div class="brand-name">{{ $company['name'] }}</div>
                    <div class="brand-sub">{{ $company['tagline'] }}</div>
                    <div class="muted">{{ $company['website'] }}</div>
                    <div class="muted">{{ $company['address'] }}</div>
                    <div class="muted">Email: {{ $company['email'] }}</div>
                    <div class="muted">Telp: {{ $company['phone'] }}</div>
                </td>
                <td>
                    <div class="doc-title">INVOICE</div>
                    <div class="meta">
                        <div><span class="muted">Date</span> {{ $invoice->created_at?->format('d/m/Y') }}</div>
                        <div><span class="muted">Invoice No.</span> {{ $invoice->number }}</div>
                        <div><span class="muted">Valid Until</span>
                            {{ $invoice->due_date ? \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d/m/Y') : '-' }}</div>
                        <div><span class="muted">Prepared By</span> {{ $preparedBy ?? '-' }}</div>
                        <div style="margin-top:4px">
                            <span class="badge {{ $invoice->status === 'paid' ? 'paid' : 'unpaid' }}">
                                {{ $invoice->status === 'paid' ? 'Lunas' : ($invoice->status === 'partially_paid' ? 'Dibayar Sebagian' : 'Belum Lunas') }}
                            </span>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="bill">
            <tr>
                <td>
                    <div class="label">Invoice For</div>
                    <div><strong>{{ $customer?->company_name ?: $customer?->name ?: '—' }}</strong></div>
                    @if ($customer?->company_name && $customer?->name)<div>Attn. {{ $customer->name }}</div>@endif
                    @if ($customer?->address)<div class="muted">{{ $customer->address }}</div>@endif
                    @if ($customer?->npwp)<div class="muted">NPWP: {{ $customer->npwp }}</div>@endif
                </td>
                <td>
                    <div class="label">Referensi</div>
                    <div>{{ $reference }}</div>
                    @if ($customer?->email)<div class="muted">{{ $customer->email }}</div>@endif
                    @if ($customer?->phone)<div class="muted">{{ $customer->phone }}</div>@endif
                </td>
            </tr>
        </table>

        <table class="terms-tbl">
            <tr><th>Prepared By</th><th>P.O. Number</th><th>Terms</th></tr>
            <tr>
                <td>{{ $preparedBy ?? '-' }}</td>
                <td>{{ $invoice->salesOrder?->po_number ?: '-' }}</td>
                <td>Due on receipt</td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:26px">No</th>
                    <th>Deskripsi</th>
                    <th class="num" style="width:44px">Qty</th>
                    <th style="width:52px">Satuan</th>
                    <th class="num" style="width:90px">Harga Satuan</th>
                    @if ($showLineDiscount)<th class="num" style="width:80px">Diskon</th>@endif
                    @if ($hasTax)<th style="width:52px">Pajak</th>@endif
                    <th class="num" style="width:100px">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 0; @endphp
                @if ($grouped)
                    @foreach (['Materials' => $materials, 'Services' => $services] as $groupName => $groupLines)
                        @continue($groupLines->isEmpty())
                        <tr class="grp"><td colspan="{{ $cols }}">{{ $groupName }}</td></tr>
                        @foreach ($groupLines as $line)
                            @include('finance.invoices._line', ['line' => $line, 'no' => ++$no])
                        @endforeach
                    @endforeach
                @else
                    @foreach ($invoice->lines as $line)
                        @include('finance.invoices._line', ['line' => $line, 'no' => ++$no])
                    @endforeach
                @endif
            </tbody>
        </table>

        <table class="foot">
            <tr>
                <td class="pay-box">
                    <div class="label">Pembayaran ke</div>
                    <div class="bank">{{ $bank['bank'] }}</div>
                    <div>No. Rek {{ $bank['account'] }}</div>
                    <div>a.n. {{ $bank['holder'] }}</div>
                    <div class="muted" style="margin-top:4px">Cantumkan nomor invoice pada berita transfer.</div>
                </td>
                <td class="sum">
                    <table>
                        <tr><td class="muted">Sub Total</td><td class="num">{{ $rupiah($totals['gross']) }}</td></tr>
                        @if ($hasDiscount)
                            <tr><td class="muted">Diskon ({{ $pct($totals['discount_percent']) }})</td><td class="num">− {{ $rupiah($totals['discount']) }}</td></tr>
                        @endif
                        <tr class="rule"><td><strong>DPP</strong></td><td class="num"><strong>{{ $rupiah($totals['subtotal']) }}</strong></td></tr>
                        <tr><td class="muted">{{ $ppnLabel }}</td><td class="num">{{ $rupiah($totals['tax']) }}</td></tr>
                        <tr class="rule"><td><strong>Total Tagihan</strong></td><td class="num"><strong>{{ $rupiah($totals['grand_total']) }}</strong></td></tr>
                        @if ($pph23On && $pph23 > 0)
                            <tr><td class="muted">PPh 23 ({{ $pct($totals['pph23_rate']) }})</td><td class="num">− {{ $rupiah($pph23) }}</td></tr>
                            <tr class="grand"><td>Total Pembayaran</td><td class="num">{{ $rupiah($totals['payable']) }}</td></tr>
                        @else
                            <tr class="grand"><td>Total Pembayaran</td><td class="num">{{ $rupiah($totals['grand_total']) }}</td></tr>
                        @endif
                        @if ($totalPaid > 0)
                            <tr><td class="muted">Sudah dibayar</td><td class="num">{{ $rupiah($totalPaid) }}</td></tr>
                            <tr><td class="muted">Sisa</td><td class="num">{{ $rupiah(($totals['payable'] ?? $totals['grand_total']) - $totalPaid) }}</td></tr>
                        @endif
                        @if ($pph23BuktiPotong ?? null)
                            <tr><td class="muted" colspan="2" style="padding-top:5px">Bukti Potong PPh 23: {{ $pph23BuktiPotong }}</td></tr>
                        @endif

                        @if ($settlement ?? null)
                            <tr><td colspan="2" style="padding-top:8px"></td></tr>
                            <tr><td class="muted">Nilai Kontrak (100%)</td><td class="num">{{ $rupiah($settlement['contract_payable']) }}</td></tr>
                            <tr><td class="muted">Ditagih sekarang (DP {{ $settlement['dp_percent'] }}%)</td><td class="num">{{ $rupiah($settlement['dp_payable']) }}</td></tr>
                            <tr class="rule"><td><strong>Sisa — pelunasan setelah BAST</strong></td><td class="num"><strong>{{ $rupiah($settlement['remaining']) }}</strong></td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <div class="tc">
            <div class="label">Invoice Terms &amp; Conditions</div>
            <ul>
                @foreach ($terms as $t)<li>{{ $t }}</li>@endforeach
            </ul>
        </div>

        <table class="sign"><tr><td>
            <div class="muted">Hormat kami,</div>
            <div>{{ $company['name'] }}</div>
            <div class="space"></div>
            <div class="name">{{ $preparedBy ?? '' }}</div>
        </td></tr></table>

        @if ($invoice->payments->isNotEmpty())
            <div class="tc">
                <div class="label">Riwayat Pembayaran</div>
                @foreach ($invoice->payments as $payment)
                    <div>{{ \Illuminate\Support\Carbon::parse($payment->paid_at)->format('d M Y H:i') }} — {{ $rupiah($payment->amount_paid) }}{{ $payment->notes ? ' ('.$payment->notes.')' : '' }}</div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
