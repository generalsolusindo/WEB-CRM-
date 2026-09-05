@php
    $qtyFmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $customer = $deliveryNote->salesOrder->contact;
    $forPdf = $forPdf ?? false;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $deliveryNote->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #111827; font-size: 11px; background: {{ $forPdf ? '#fff' : '#f1f5f9' }}; }
        .sheet { background: #fff; {{ $forPdf ? '' : 'width: 210mm; min-height: 297mm; margin: 12px auto; padding: 16mm;' }} }
        .toolbar { width: 210mm; margin: 12px auto 0; text-align: right; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 6px; background: #001B3A; color: #fff; font-size: 12px; cursor: pointer; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }

        .top td { border: 0; padding: 0; }
        .brand-name { font-size: 16px; font-weight: 700; color: #001B3A; }
        .brand-sub { font-size: 9px; letter-spacing: .5px; color: #374151; }
        .muted { color: #6B7280; }
        .title { text-align: center; margin-top: 10px; }
        .title h1 { font-size: 18px; letter-spacing: 1px; }
        .title .num { font-size: 12px; font-weight: 700; margin-top: 2px; }

        .meta-tbl { margin-top: 12px; border: 1px solid #D1D5DB; }
        .meta-tbl td { border: 1px solid #D1D5DB; padding: 5px 8px; }
        .meta-tbl .label { color: #6B7280; width: 25%; }

        .parties { margin-top: 12px; }
        .parties td { border: 0; padding: 0; width: 50%; }
        .parties .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #6B7280; margin-bottom: 3px; }

        .items { margin-top: 12px; }
        .items th { background: #F3F4F6; border: 1px solid #D1D5DB; padding: 6px 7px; font-size: 9px; text-transform: uppercase; text-align: center; }
        .items td { border: 1px solid #D1D5DB; padding: 6px 7px; }
        .num { text-align: center; }

        .sign { margin-top: 30px; }
        .sign td { border: 1px solid #D1D5DB; padding: 8px; text-align: center; width: 33.33%; }
        .sign .role { font-weight: 700; }
        .sign .space { height: 60px; }
        .sign .name { border-top: 1px solid #111827; margin: 0 12px; padding-top: 4px; }

        .footnote { margin-top: 8px; font-size: 9px; font-style: italic; color: #6B7280; }
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
                <td style="width:60%">
                    <div class="brand-name">CV. GENERAL SOLUSINDO</div>
                    <div class="brand-sub">IT - CONSULTAN - INTEGRATOR - SUPPLIER - TRAINING</div>
                </td>
                <td class="muted">
                    <div>Jl. Pondok Jati AS-31 Sidoarjo</div>
                    <div>Phone: 0811 3219 992</div>
                    <div>Email: informasi@generalsolusindo.com</div>
                    <div>Website: https://generalsolusindo.com</div>
                </td>
            </tr>
        </table>

        <div class="title">
            <h1>DELIVERY NOTE</h1>
            <div class="num">No : {{ $deliveryNote->number }}</div>
        </div>

        <table class="meta-tbl">
            <tr>
                <td class="label">Invoice Date</td>
                <td>{{ $deliveryNote->invoice?->created_at ? $deliveryNote->invoice->created_at->format('d/m/Y') : '-' }}</td>
                <td class="label">PO Date</td>
                <td>{{ $deliveryNote->salesOrder->po_date ? \Illuminate\Support\Carbon::parse($deliveryNote->salesOrder->po_date)->format('d/m/Y') : '-' }}</td>
            </tr>
            <tr>
                <td class="label">Invoice No</td>
                <td>{{ $deliveryNote->invoice?->number ?? '-' }}</td>
                <td class="label">PO No.</td>
                <td>{{ $deliveryNote->salesOrder->po_number ?? '-' }}</td>
            </tr>
        </table>

        <table class="parties">
            <tr>
                <td>
                    <div class="label">Sold to</div>
                    <div>{{ $customer?->company_name ?: $customer?->name }}</div>
                </td>
                <td>
                    <div class="label">Delivery</div>
                    <div style="white-space: pre-line">{{ $deliveryNote->delivery_address }}</div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:26px">No</th>
                    <th>Description</th>
                    <th style="width:50px">Unit</th>
                    <th style="width:70px">Ordered</th>
                    <th style="width:80px">Previous Balance</th>
                    <th style="width:70px">Delivered</th>
                    <th style="width:70px">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($deliveryNote->lines as $i => $line)
                    <tr>
                        <td class="num">{{ $i + 1 }}</td>
                        <td>{{ $line->item_name }}</td>
                        <td class="num">{{ $line->unit }}</td>
                        <td class="num">{{ $qtyFmt($line->qty_ordered) }}</td>
                        <td class="num">{{ $qtyFmt($line->qty_previous_balance) }}</td>
                        <td class="num">{{ $qtyFmt($line->qty_delivered) }}</td>
                        <td class="num">{{ $qtyFmt($line->qty_balance) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="sign">
            <tr>
                <td>
                    <div class="role">Diterima oleh (Teknisi)</div>
                    <div class="space"></div>
                    <div class="name">&nbsp;</div>
                </td>
                <td>
                    <div class="role">Shipper</div>
                    <div class="space"></div>
                    <div class="name">{{ $deliveryNote->shipper_name ?: '' }}</div>
                </td>
                <td>
                    <div class="role">Approved by</div>
                    <div class="space"></div>
                    <div class="name">{{ $deliveryNote->approved_by_name ?: '' }}</div>
                </td>
            </tr>
        </table>

        <div class="footnote">(Original for General Solusindo, Copy-1 for Customer / Buyer)</div>
    </div>
</body>
</html>
