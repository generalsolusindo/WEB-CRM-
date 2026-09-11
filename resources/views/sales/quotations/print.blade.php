@php
    $number = $quotation->number ?? ('QT-'.str_pad($quotation->id, 6, '0', STR_PAD_LEFT).' / R'.$quotation->revision_number);
    $rupiah = fn ($v) => number_format(round((float) $v), 0, ',', '.');
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', '.'), '0'), ',').'%';

    $hasDiscount = ($totals['discount'] ?? 0) > 0;
    $hasTax = $quotation->lines->contains(fn ($l) => (float) $l->tax_rate > 0);
    $ppnRate = (float) ($quotation->lines->max('tax_rate') ?? 0);

    $materials = $quotation->lines->filter(fn ($l) => $l->category === 'material')->values();
    $services = $quotation->lines->filter(fn ($l) => in_array($l->category, ['service', 'reimburse'], true))->values();
    $groups = array_filter(['Materials' => $materials, 'Services' => $services], fn ($g) => $g->isNotEmpty());

    $qtyFmt = fn ($l) => rtrim(rtrim(number_format((float) $l->qty, 2, ',', '.'), '0'), ',').' '.$l->unit;

    // kolom: Quantity, Description, Unit Price, Disc (+ Pajak?), Amount
    $cols = 4 + ($hasTax ? 1 : 0);
    $labelSpan = $cols - 1;

    $terms = [
        'Price Include Tax',
        'Payment DP 50%',
        'Payment 50% After BAST',
        'Warranty Services 1 Month',
        'No Cancellation',
        'The final report will be submitted one business day after full payment (100%) has been received',
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', Arial, sans-serif; color: #1E293B; font-size: 11px; background: #f1f5f9; }
        .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 15mm 16mm; background: #fff; }
        .toolbar { width: 210mm; margin: 12px auto 0; text-align: right; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 6px; background: #001B3A; color: #fff; font-size: 12px; cursor: pointer; }

        header { display: flex; justify-content: space-between; align-items: flex-start; }
        header .brand img { height: 46px; display: block; }
        header .brand .tag { font-size: 9px; font-style: italic; color: #475569; margin-top: 4px; letter-spacing: .2px; }
        header .brand .web { font-size: 9px; color: #2563EB; }
        header h1 { font-size: 34px; font-weight: 300; letter-spacing: 3px; color: #64748B; }

        .top { display: flex; justify-content: space-between; margin-top: 6px; }
        .top .addr { font-size: 10px; color: #475569; line-height: 1.6; }
        .top .meta { font-size: 10px; }
        .top .meta table { border-collapse: collapse; }
        .top .meta td { padding: 1px 0 1px 12px; text-align: right; }
        .top .meta td.k { font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }

        .parties { display: flex; justify-content: space-between; margin-top: 14px; }
        .parties .label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #64748B; margin-bottom: 3px; }
        .parties .to strong { font-size: 12px; }
        .parties .valid { text-align: right; font-size: 10px; }
        .parties .valid .row { margin-bottom: 2px; }
        .parties .valid em { color: #64748B; font-style: italic; }

        table.grid { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.grid th, table.grid td { border: 1px solid #94A3B8; padding: 4px 7px; text-align: left; vertical-align: top; }
        table.grid thead th { background: #F1F5F9; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; text-align: center; }

        .strip td { font-size: 10px; }
        .strip td { text-align: center; }

        .items th.num, .items td.num { text-align: right; white-space: nowrap; }
        .items tr.group td { background: #F8FAFC; font-weight: 700; font-size: 10px; }
        .items .desc .sub { color: #64748B; font-size: 9.5px; margin-top: 1px; }

        .summary { display: flex; justify-content: space-between; margin-top: 4px; }
        .summary .left { width: 56%; font-size: 10px; }
        .summary .left .prep { font-weight: 700; margin-bottom: 4px; }
        .summary .left .lead { margin-bottom: 3px; }
        .summary .left ul { list-style: none; }
        .summary .left ul li { padding: 1px 0; }
        .summary .left ul li::before { content: "- "; }
        .summary .totals { width: 40%; }
        .summary .totals table { width: 100%; border-collapse: collapse; }
        .summary .totals td { padding: 3px 4px; font-size: 10px; }
        .summary .totals td.k { text-align: right; text-transform: uppercase; font-weight: 700; letter-spacing: .3px; color: #475569; }
        .summary .totals td.v { text-align: right; border: 1px solid #94A3B8; font-weight: 700; }
        .summary .totals tr.grand td { font-size: 12px; }

        .sign { margin-top: 18px; font-size: 10px; }
        .sign .line { display: inline-block; border-bottom: 1px solid #334155; width: 320px; margin-left: 6px; }
        .notes { margin-top: 14px; }
        .notes .label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #64748B; margin-bottom: 3px; }

        footer { margin-top: 28px; text-align: center; font-size: 10px; color: #475569; }
        footer .thanks { font-weight: 700; color: #1E293B; margin-top: 2px; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; width: auto; min-height: auto; padding: 0; }
            @page { size: A4; margin: 14mm 15mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
    <div class="sheet">
        <header>
            <div class="brand">
                <img src="{{ asset('images/logo-gs.png') }}" alt="General Solusindo">
                <div class="tag">IT - Consultant Integrator Supplier Training</div>
                <div class="web">https://generalsolusindo.com/</div>
            </div>
            <h1>QUOTATION</h1>
        </header>

        <div class="top">
            <div class="addr">
                Pondok Jati II AS - 31, Sidoarjo, 61252<br>
                Email : informasi@generalsolusindo.com<br>
                Phone : 08113219992
            </div>
            <div class="meta">
                <table>
                    <tr><td class="k">Date :</td><td>{{ $quotation->created_at?->format('d/m/Y') }}</td></tr>
                    <tr><td class="k">Quotation # :</td><td>{{ $number }}</td></tr>
                    <tr><td class="k">Customer ID :</td><td>&nbsp;</td></tr>
                </table>
            </div>
        </div>

        <div class="parties">
            <div class="to">
                <div class="label">Quotation For:</div>
                <strong>{{ $quotation->contact->company_name ?: $quotation->contact->name }}</strong>
                @if ($quotation->contact->company_name)<div>Attn. {{ $quotation->contact->name }}</div>@endif
                @if ($quotation->contact->address)<div style="color:#64748B">{{ $quotation->contact->address }}</div>@endif
            </div>
            <div class="valid">
                <div class="row"><em>Quotation valid until:</em> {{ $quotation->valid_until?->format('d/m/Y') }}</div>
                <div class="row"><em>Prepared by:</em> {{ $quotation->sales?->name ?? '-' }}</div>
            </div>
        </div>

        <table class="grid strip">
            <thead>
                <tr>
                    <th>Salesperson</th><th>P.O. Number</th><th>Ship Date</th>
                    <th>Ship Via</th><th>F.O.B. Point</th><th>Terms</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $quotation->sales?->name ?? '-' }}</td>
                    <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                    <td>Due on receipt</td>
                </tr>
            </tbody>
        </table>

        <table class="grid items">
            <thead>
                <tr>
                    <th>Quantity</th>
                    <th>Description</th>
                    <th class="num">Unit Price</th>
                    <th class="num">Disc</th>
                    @if ($hasTax)<th class="num">Pajak</th>@endif
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $groupLabel => $lines)
                    <tr class="group"><td colspan="{{ $cols }}">{{ $groupLabel }}</td></tr>
                    @foreach ($lines as $line)
                        <tr>
                            <td>{{ $qtyFmt($line) }}</td>
                            <td class="desc">
                                <strong>{{ $line->item_name }}</strong>
                                @if ($line->description)<div class="sub">{{ $line->description }}</div>@endif
                                @if ($line->sourcing_note)<div class="sub">Opsi: {{ $line->sourcing_note }}</div>@endif
                            </td>
                            <td class="num">{{ $rupiah($line->selling_price) }}</td>
                            <td class="num">@if ((float) $line->discount_amount > 0){{ $rupiah($line->discount_amount) }}<br><span style="color:#64748B;font-size:9px">({{ $pct($line->discount_percent ?? 0) }})</span>@else&mdash;@endif</td>
                            @if ($hasTax)<td class="num">{{ (float) $line->tax_rate > 0 ? $pct($line->tax_rate) : '—' }}</td>@endif
                            <td class="num">{{ $rupiah($line->subtotal) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <div class="left">
                <div class="prep">Quotation prepared by: {{ $quotation->sales?->name ?? '-' }}</div>
                <div class="lead">This is a quotation on the items listed, subject to the conditions noted below:</div>
                <ul>
                    @foreach ($terms as $term)<li>{{ $term }}</li>@endforeach
                </ul>
            </div>
            <div class="totals">
                <table>
                    <tr><td class="k">Sub Total</td><td class="v">{{ $rupiah($totals['gross']) }}</td></tr>
                    @if ($hasDiscount)
                        <tr><td class="k">Disc</td><td class="v">{{ $pct($totals['discount_percent']) }}</td></tr>
                    @endif
                    <tr><td class="k">DPP</td><td class="v">{{ $rupiah($totals['subtotal']) }}</td></tr>
                    <tr><td class="k">PPN ({{ $pct($ppnRate) }})</td><td class="v">{{ $rupiah($totals['tax']) }}</td></tr>
                    <tr class="grand"><td class="k">Total</td><td class="v">{{ $rupiah($totals['grand_total']) }}</td></tr>
                </table>
            </div>
        </div>

        @if ($quotation->notes)
            <div class="notes">
                <div class="label">Catatan</div>
                <div>{{ $quotation->notes }}</div>
            </div>
        @endif

        <div class="sign">To accept this quotation, sign here and return : <span class="line"></span></div>

        <footer>
            <div>If you have any questions concerning this quotation, contact {{ $quotation->sales?->name ?? 'kami' }}, 08113219992, informasi@generalsolusindo.com.</div>
            <div class="thanks">THANK YOU FOR YOUR BUSINESS!</div>
        </footer>
    </div>
</body>
</html>
