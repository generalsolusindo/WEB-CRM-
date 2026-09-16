@php
    $forPdf = $forPdf ?? false;
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

    $terms = array_values(array_filter(array_map(
        'trim',
        explode("\n", $quotation->terms ?? \App\Support\QuotationDefaults::terms()),
    ), fn ($line) => $line !== ''));

    // Base64 supaya logo tetap tampil saat dirender DomPDF (tidak bisa fetch URL remote).
    $logoPath = public_path('images/logo-gs.png');
    $logoSrc = is_file($logoPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
        : asset('images/logo-gs.png');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quotation {{ $number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', Arial, sans-serif; color: #1E293B; font-size: 11px; background: {{ $forPdf ? '#fff' : '#f1f5f9' }}; }
        .sheet { background: #fff; {{ $forPdf ? 'padding: 15mm 16mm;' : 'width: 210mm; min-height: 297mm; margin: 12px auto; padding: 15mm 16mm;' }} }
        .grid-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .toolbar { width: 210mm; margin: 12px auto 0; text-align: right; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 6px; background: #001B3A; color: #fff; font-size: 12px; cursor: pointer; }
        @if (! $forPdf)
            @media (max-width: 210mm) {
                .toolbar { width: auto; margin: 12px 12px 0; }
                .sheet { width: auto; min-height: 0; margin: 12px; padding: 5mm; }
                .grid-scroll table.grid { min-width: 680px; }
            }
        @endif

        table.layout { width: 100%; border-collapse: collapse; }
        table.layout td { vertical-align: top; border: none; padding: 0; }

        table.header-table { margin: 0; }
        table.header-table td.title-cell { text-align: right; }
        .brand img { height: 82px; display: block; }
        .brand .tag { font-size: 9px; font-style: italic; color: #475569; margin-top: 4px; letter-spacing: .2px; }
        .brand .web { font-size: 9px; color: #2563EB; }
        h1 { font-size: 34px; font-weight: 300; letter-spacing: 3px; color: #64748B; }

        table.top-table { margin-top: 6px; }
        .addr { font-size: 10px; color: #475569; line-height: 1.6; }
        .meta { font-size: 10px; text-align: right; }
        .meta table { border-collapse: collapse; margin-left: auto; }
        .meta td { padding: 1px 0 1px 12px; text-align: right; border: none; }
        .meta td.k { font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }

        table.parties-table { margin-top: 14px; }
        .label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #64748B; margin-bottom: 3px; }
        .to strong { font-size: 12px; }
        .valid { text-align: right; font-size: 10px; }
        .valid .row { margin-bottom: 2px; }
        .valid em { color: #64748B; font-style: italic; }

        table.grid { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.grid th, table.grid td { border: 1px solid #94A3B8; padding: 4px 7px; text-align: left; vertical-align: top; }
        table.grid thead th { background: #F1F5F9; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; text-align: center; }

        .strip td { font-size: 10px; }
        .strip td { text-align: center; }

        .items th.num, .items td.num { text-align: right; white-space: nowrap; }
        .items tr.group td { background: #F8FAFC; font-weight: 700; font-size: 10px; }
        .items .desc .sub { color: #64748B; font-size: 9.5px; margin-top: 1px; }

        table.summary-table { margin-top: 4px; }
        table.summary-table td.left-cell { width: 56%; font-size: 10px; }
        table.summary-table td.totals-cell { width: 44%; }
        .prep { font-weight: 700; margin-bottom: 4px; }
        .lead { margin-bottom: 3px; }
        .left-cell ul { list-style: none; }
        .left-cell ul li { padding: 1px 0; }
        .left-cell ul li::before { content: "- "; }
        .totals-cell table { width: 100%; border-collapse: collapse; }
        .totals-cell td { padding: 3px 4px; font-size: 10px; border: none; }
        .totals-cell td.k { text-align: right; text-transform: uppercase; font-weight: 700; letter-spacing: .3px; color: #475569; }
        .totals-cell td.v { text-align: right; border: 1px solid #94A3B8; font-weight: 700; }
        .totals-cell tr.grand td { font-size: 12px; }

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
    @unless ($forPdf)
        <div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
    @endunless
    <div class="sheet">
        <table class="layout header-table">
            <tr>
                <td class="brand">
                    <img src="{{ $logoSrc }}" alt="General Solusindo">
                    <div class="tag">IT - Consultant Integrator Supplier Training</div>
                    <div class="web">https://generalsolusindo.com/</div>
                </td>
                <td class="title-cell"><h1>QUOTATION</h1></td>
            </tr>
        </table>

        <table class="layout top-table">
            <tr>
                <td class="addr">
                    Pondok Jati II AS - 31, Sidoarjo, 61252<br>
                    Email : informasi@generalsolusindo.com<br>
                    Phone : 08113219992
                </td>
                <td class="meta">
                    <table>
                        <tr><td class="k">Date :</td><td>{{ $quotation->created_at?->format('d/m/Y') }}</td></tr>
                        <tr><td class="k">Quotation # :</td><td>{{ $number }}</td></tr>
                        <tr><td class="k">Customer ID :</td><td>&nbsp;</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="layout parties-table">
            <tr>
                <td class="to">
                    <div class="label">Quotation For:</div>
                    <strong>{{ $quotation->contact->company_name ?: $quotation->contact->name }}</strong>
                    @if ($quotation->contact->company_name)<div>Attn. {{ $quotation->contact->name }}</div>@endif
                    @if ($quotation->contact->address)<div style="color:#64748B">{{ $quotation->contact->address }}</div>@endif
                </td>
                <td class="valid">
                    <div class="row"><em>Quotation valid until:</em> {{ $quotation->valid_until?->format('d/m/Y') }}</div>
                    <div class="row"><em>Prepared by:</em> {{ $quotation->sales?->name ?? '-' }}</div>
                </td>
            </tr>
        </table>

        <div class="grid-scroll">
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
        </div>

        <div class="grid-scroll">
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
                                @if ($line->description)<div class="sub">{!! nl2br(e($line->description)) !!}</div>@endif
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
        </div>

        <table class="layout summary-table">
            <tr>
                <td class="left-cell">
                    <div class="prep">Quotation prepared by: {{ $quotation->sales?->name ?? '-' }}</div>
                    <div class="lead">This is a quotation on the items listed, subject to the conditions noted below:</div>
                    <ul>
                        @foreach ($terms as $term)<li>{{ $term }}</li>@endforeach
                    </ul>
                </td>
                <td class="totals-cell">
                    <table>
                        <tr><td class="k">Sub Total</td><td class="v">{{ $rupiah($totals['gross']) }}</td></tr>
                        @if ($hasDiscount)
                            <tr><td class="k">Disc</td><td class="v">{{ $pct($totals['discount_percent']) }}</td></tr>
                        @endif
                        <tr><td class="k">DPP</td><td class="v">{{ $rupiah($totals['subtotal']) }}</td></tr>
                        <tr><td class="k">PPN ({{ $pct($ppnRate) }})</td><td class="v">{{ $rupiah($totals['tax']) }}</td></tr>
                        <tr class="grand"><td class="k">Total</td><td class="v">{{ $rupiah($totals['grand_total']) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

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
