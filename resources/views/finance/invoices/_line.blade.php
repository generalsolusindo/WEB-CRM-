<tr>
    <td>{{ $no }}</td>
    <td>{{ $line->item_name }}</td>
    <td class="num">{{ $qtyFmt($line->qty) }}</td>
    <td>{{ $line->salesOrderLine?->unit ?? '—' }}</td>
    <td class="num">{{ $rupiah($line->unit_price) }}</td>
    @if ($showLineDiscount)
        <td class="num">{{ (float) $line->discount_amount > 0 ? $rupiah($line->discount_amount) : '—' }}</td>
    @endif
    @if ($hasTax)
        <td>{{ (float) $line->tax_rate > 0 ? $pct($line->tax_rate) : '—' }}</td>
    @endif
    <td class="num">{{ $rupiah($lineGross($line)) }}</td>
</tr>
