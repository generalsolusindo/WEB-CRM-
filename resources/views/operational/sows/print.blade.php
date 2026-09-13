@php
    $forPdf = $forPdf ?? false;
    $dots = fn (?string $v, int $min = 20) => $v !== null && $v !== '' ? $v : str_repeat('.', $min);
    $vendor = $project->vendor;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SOW {{ $sow->number ?? $project->id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #111827; font-size: 12px; background: {{ $forPdf ? '#fff' : '#f1f5f9' }}; line-height: 1.5; }
        .sheet { background: #fff; {{ $forPdf ? '' : 'width: 210mm; min-height: 297mm; margin: 12px auto; padding: 18mm;' }} }
        .toolbar { width: 210mm; margin: 12px auto 0; text-align: right; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 6px; background: #001B3A; color: #fff; font-size: 12px; cursor: pointer; }
        h1 { text-align: center; font-size: 16px; letter-spacing: 1px; margin-bottom: 16px; }
        h2 { font-size: 12px; margin: 16px 0 6px; border-bottom: 1px solid #D1D5DB; padding-bottom: 2px; }
        table.meta td { border: 0; padding: 1px 0; vertical-align: top; }
        table.meta td.label { width: 140px; }
        p.body-text { white-space: pre-line; text-align: justify; }
        ul { margin-left: 18px; }
        .sign { margin-top: 30px; width: 100%; }
        .sign td { width: 25%; vertical-align: top; padding: 0 6px; text-align: center; }
        .sign .role { font-weight: 700; }
        .sign .space { height: 55px; }
        .sign .name { border-top: 1px solid #111827; display: inline-block; padding-top: 4px; margin-top: 4px; font-size: 11px; }
        @page { size: A4; margin: 16mm; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; width: auto; min-height: auto; padding: 0; } }
    </style>
</head>
<body>
    @unless($forPdf)
        <div class="toolbar"><button onclick="window.print()">Print / Simpan PDF</button></div>
    @endunless
    <div class="sheet">
        <h1>SCOPE OF WORK (SOW)</h1>

        <h2>1. INFORMASI UMUM</h2>
        <table class="meta">
            <tr><td class="label">Nama Proyek</td><td>: {{ $dots($sow->project_name) }}</td></tr>
            <tr><td class="label">Nomor</td><td>: {{ $dots($sow->number) }}</td></tr>
            <tr><td class="label">Lokasi</td><td>: {{ $dots($sow->site_location) }}</td></tr>
            <tr><td class="label">Client</td><td>: {{ $dots($sow->client_name) }}</td></tr>
            <tr><td class="label">Vendor/Implementor</td><td>: CV. General Solusindo</td></tr>
            <tr><td class="label">Tanggal Pelaksanaan</td><td>: {{ $dots($sow->execution_date) }}</td></tr>
        </table>

        <h2>2. LATAR BELAKANG</h2>
        <p class="body-text">{{ $sow->background ?: '.................................................' }}</p>
        @if($imageUrls->isNotEmpty())
            <div style="margin-top:8px;">
                @foreach($imageUrls as $url)
                    <img src="{{ $url }}" style="max-width:100%; margin-bottom:8px;">
                @endforeach
            </div>
        @endif

        <h2>3. RUANG LINGKUP PEKERJAAN</h2>
        @if($sow->scopeSections->isEmpty())
            <p class="body-text">.................................................</p>
        @else
            @foreach($sow->scopeSections as $i => $section)
                <p style="font-weight:700; margin-top:8px;">{{ chr(65 + $i) }}. {{ $section->title }}</p>
                <p class="body-text">{{ $dots($section->content, 20) }}</p>
                @foreach($scopeSectionImageUrls->get($section->id, collect()) as $url)
                    <img src="{{ $url }}" style="max-width:100%; margin: 6px 0;">
                @endforeach
            @endforeach
        @endif

        <h2>4. TANGGUNG JAWAB</h2>
        <p class="body-text">{{ $dots($sow->responsibilities, 40) }}</p>

        <h2>5. WAKTU PELAKSANAAN & JADWAL</h2>
        <table class="meta">
            <tr><td class="label">Estimasi Durasi Pekerjaan</td><td>: {{ $dots($sow->schedule_duration, 20) }}</td></tr>
            <tr><td class="label">Waktu Mulai</td><td>: {{ $sow->schedule_start_date?->locale('id')->translatedFormat('d F Y') ?: str_repeat('.', 20) }}</td></tr>
            <tr><td class="label">Target Selesai</td><td>: {{ $sow->schedule_end_date?->locale('id')->translatedFormat('d F Y') ?: str_repeat('.', 20) }}</td></tr>
        </table>

        <h2>6. KESELAMATAN KERJA (K3)</h2>
        <p class="body-text">{{ $dots($sow->safety, 40) }}</p>

        <h2>7. PEMBAYARAN</h2>
        <p class="body-text">{{ $dots($sow->payment_terms, 40) }}</p>

        <h2>8. OUTPUT PEKERJAAN</h2>
        <p class="body-text">{{ $dots($sow->output, 40) }}</p>

        <h2>9. GARANSI LAYANAN TEKNISI</h2>
        <p class="body-text">{{ $dots($sow->warranty, 40) }}</p>

        <h2>10. CATATAN</h2>
        <p class="body-text">{{ $dots($sow->notes, 40) }}</p>

        <h2>11. PIC & KONTAK</h2>
        <table class="meta">
            <tr><td class="label">PIC Vendor</td><td>: {{ $dots($vendor?->contact_person, 30) }} ({{ $dots($vendor?->phone, 15) }})</td></tr>
            <tr><td class="label">Team Teknisi Site</td><td>: {{ $dots($sow->technician?->name, 30) }} ({{ $dots($sow->technician?->phone, 15) }})</td></tr>
            @if($sow->technician_team_note)
                <tr><td class="label"></td><td>&nbsp;&nbsp;{{ $sow->technician_team_note }}</td></tr>
            @endif
            <tr><td class="label">PIC Client</td><td>: {{ $dots($sow->client_pic_name, 30) }} ({{ $dots($sow->client_pic_phone, 15) }})</td></tr>
        </table>

        <h2>12. PENUTUP</h2>
        <p class="body-text">{{ $dots($sow->closing, 40) }}</p>

        <p style="margin-top:20px; font-weight:700;">PENUGASAN</p>
        <p>Disetujui oleh:</p>
        <table class="sign">
            <tr>
                <td class="role">Teknisi</td>
                <td class="role">PIC Vendor</td>
                <td class="role">Operasional</td>
                <td class="role">Project Manager</td>
            </tr>
            <tr>
                <td class="space">@if($sow->technician_signature)<img src="{{ $sow->technician_signature }}" style="max-height:55px;">@endif</td>
                <td class="space">@if($sow->vendor_signature)<img src="{{ $sow->vendor_signature }}" style="max-height:55px;">@endif</td>
                <td class="space">@if($sow->admin_signature)<img src="{{ $sow->admin_signature }}" style="max-height:55px;">@endif</td>
                <td class="space">@if($sow->director_signature)<img src="{{ $sow->director_signature }}" style="max-height:55px;">@endif</td>
            </tr>
            <tr>
                <td><span class="name">{{ $sow->technician?->name ?: '.....................' }}</span></td>
                <td><span class="name">{{ $sow->vendorSignedBy?->name ?: ($vendor?->contact_person ?: '.....................') }}</span></td>
                <td><span class="name">{{ $sow->adminSignedBy?->name ?: '.....................' }}</span></td>
                <td><span class="name">{{ $sow->directorSignedBy?->name ?: '.....................' }}</span></td>
            </tr>
        </table>
    </div>
</body>
</html>
