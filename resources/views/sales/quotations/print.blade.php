@php
    $number = $quotation->number ?? ('QT-'.str_pad($quotation->id, 6, '0', STR_PAD_LEFT).' / R'.$quotation->revision_number);
    $rupiah = fn ($v) => 'Rp '.number_format((float) $v, 2, ',', '.');
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%';
    $hasDiscount = ($totals['discount'] ?? 0) > 0;
    $hasTax = $quotation->lines->contains(fn ($l) => (float) $l->tax_rate > 0);
    // Kolom dasar: No, Item, Qty, Unit, Harga Satuan (5) + Diskon? + Pajak?  (label mengisi semua kecuali kolom nilai DPP)
    $labelSpan = 5 + ($hasDiscount ? 1 : 0) + ($hasTax ? 1 : 0);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', Arial, sans-serif; color: #001B3A; font-size: 12px; background: #f1f5f9; }
        .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 18mm; background: #fff; }
        .toolbar { width: 210mm; margin: 12px auto 0; text-align: right; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 6px; background: #001B3A; color: #fff; font-size: 12px; cursor: pointer; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #001B3A; padding-bottom: 12px; }
        header h1 { font-size: 20px; letter-spacing: 1px; }
        header .doc { text-align: right; }
        header .doc .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748B; }
        header .doc .num { font-size: 15px; font-weight: 700; }
        .parties { display: flex; justify-content: space-between; margin: 18px 0; }
        .parties .block { width: 48%; }
        .parties .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748B; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #E2E8F0; text-align: left; }
        th { background: #F8FAFC; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #64748B; }
        td.num, th.num { text-align: right; }
        tfoot td { border-bottom: 0; }
        tfoot tr.grand td { border-top: 2px solid #001B3A; font-size: 14px; font-weight: 700; }
        .notes { margin-top: 20px; }
        .notes .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748B; margin-bottom: 4px; }
        .muted { color: #64748B; }
        .approval { display: flex; justify-content: space-between; margin-top: 48px; }
        .approval .col { width: 45%; }
        .approval .role { font-size: 11px; color: #64748B; }
        .approval .party { font-weight: 700; margin-top: 2px; }
        .approval .field { margin-top: 8px; font-size: 11px; color: #334155; }
        .approval .box { height: 96px; border: 1px dashed #94A3B8; border-radius: 4px; margin-top: 8px; }
        .approval .cap { text-align: center; font-size: 10px; color: #94A3B8; margin-top: 4px; }
        .approval-note { margin-top: 12px; font-size: 10px; color: #94A3B8; }
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
                <div class="muted">Quotation Penawaran</div>
            </div>
            <div class="doc">
                <div class="label">Nomor Quotation</div>
                <div class="num">{{ $number }}</div>
                <div class="muted">Tanggal: {{ $quotation->created_at?->format('d M Y') }}</div>
                @if ($quotation->valid_until)
                    <div class="muted">Berlaku s/d: {{ \Illuminate\Support\Carbon::parse($quotation->valid_until)->format('d M Y') }}</div>
                @endif
            </div>
        </header>

        <div class="parties">
            <div class="block">
                <div class="label">Ditujukan Kepada</div>
                <div><strong>{{ $quotation->contact->name }}</strong></div>
                @if ($quotation->contact->company_name)<div>{{ $quotation->contact->company_name }}</div>@endif
                @if ($quotation->contact->address)<div class="muted">{{ $quotation->contact->address }}</div>@endif
                @if ($quotation->contact->npwp)<div class="muted">NPWP: {{ $quotation->contact->npwp }}</div>@endif
            </div>
            <div class="block">
                <div class="label">Kontak</div>
                @if ($quotation->contact->email)<div>{{ $quotation->contact->email }}</div>@endif
                @if ($quotation->contact->phone)<div>{{ $quotation->contact->phone }}</div>@endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:36px">No</th>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th>Unit</th>
                    <th class="num">Harga Satuan</th>
                    @if ($hasDiscount)<th class="num">Diskon</th>@endif
                    @if ($hasTax)<th>Pajak</th>@endif
                    <th class="num">DPP</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotation->lines as $i => $line)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            <strong>{{ $line->item_name }}</strong>@if ($line->category === 'service') <span class="muted">(Jasa)</span>@endif
                            @if ($line->description)<br><span class="muted">{{ $line->description }}</span>@endif
                            @if ($line->sourcing_note)<br><span class="muted">Opsi: {{ $line->sourcing_note }}</span>@endif
                        </td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',') }}</td>
                        <td>{{ $line->unit }}</td>
                        <td class="num">{{ $rupiah($line->selling_price) }}</td>
                        @if ($hasDiscount)<td class="num">{{ (float) $line->discount_amount > 0 ? $rupiah($line->discount_amount).' ('.$pct($line->discount_percent ?? 0).')' : '—' }}</td>@endif
                        @if ($hasTax)<td>{{ $line->tax ? $line->tax->name : ((float) $line->tax_rate > 0 ? $pct($line->tax_rate) : '—') }}</td>@endif
                        <td class="num">{{ $rupiah($line->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @if ($hasDiscount)
                    <tr><td colspan="{{ $labelSpan }}" class="num muted">Subtotal Bruto</td><td class="num">{{ $rupiah($totals['gross']) }}</td></tr>
                    <tr><td colspan="{{ $labelSpan }}" class="num muted">Total Diskon ({{ $pct($totals['discount_percent']) }})</td><td class="num">− {{ $rupiah($totals['discount']) }}</td></tr>
                @endif
                <tr><td colspan="{{ $labelSpan }}" class="num muted">{{ $hasTax ? 'DPP' : 'Subtotal' }}</td><td class="num">{{ $rupiah($totals['subtotal']) }}</td></tr>
                @if ($hasTax)<tr><td colspan="{{ $labelSpan }}" class="num muted">Total PPN</td><td class="num">{{ $rupiah($totals['tax']) }}</td></tr>@endif
                <tr class="grand"><td colspan="{{ $labelSpan }}" class="num">Grand Total</td><td class="num">{{ $rupiah($totals['grand_total']) }}</td></tr>
            </tfoot>
        </table>

        @if ($quotation->notes)
            <div class="notes">
                <div class="label">Catatan</div>
                <div>{{ $quotation->notes }}</div>
            </div>
        @endif

        <div class="approval">
            <div class="col">
                <div class="role">Hormat kami,</div>
                <div class="party">PT General Solusindo</div>
                <div class="box"></div>
                <div class="cap">( {{ $quotation->sales?->name ?? '..............................' }} )</div>
            </div>
            <div class="col">
                <div class="role">Menyetujui,</div>
                <div class="party">{{ $quotation->contact->company_name ?: $quotation->contact->name }}</div>
                <div class="field">Nama &amp; Jabatan: ..................................................</div>
                <div class="field">Tanggal: ..................................................</div>
                <div class="box"></div>
                <div class="cap">Tanda tangan &amp; Stempel</div>
            </div>
        </div>
        <div class="approval-note">Persetujuan sah bila dokumen ini ditandatangani; stempel wajib bila customer berupa perusahaan/instansi. Kirim kembali dokumen ini (atau terbitkan Purchase Order) sebagai dasar penerbitan Sales Order.</div>
    </div>
</body>
</html>
