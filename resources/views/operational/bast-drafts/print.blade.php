@php
    $forPdf = $forPdf ?? false;
    $dayNames = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
    $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    $date = $draft->event_date;
    $dots = fn (?string $v, int $min = 20) => $v !== null && $v !== '' ? $v : str_repeat('.', $min);
    $hasPurchaseOrder = filled($salesOrder->po_number);
    $referenceLabel = $hasPurchaseOrder ? 'Purchase Order (PO)' : 'Quotation';
    $referenceNumber = $hasPurchaseOrder
        ? $salesOrder->po_number
        : ($salesOrder->quotation?->number ?: 'QT-'.str_pad((string) ($salesOrder->quotation_id ?? 0), 6, '0', STR_PAD_LEFT).' / R'.($salesOrder->quotation?->revision_number ?? 1));
    $referenceDate = $hasPurchaseOrder
        ? $salesOrder->po_date
        : ($salesOrder->quotation?->quoted_at ?? $salesOrder->quotation?->created_at);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>BAST {{ $project->salesOrder->number ?? $project->id }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #111827; font-size: 12px; background: {{ $forPdf ? '#fff' : '#f1f5f9' }}; line-height: 1.5; }
        .sheet { background: #fff; {{ $forPdf ? '' : 'width: 210mm; min-height: 297mm; margin: 12px auto; padding: 18mm;' }} }
        .toolbar { width: 210mm; margin: 12px auto 0; text-align: right; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 6px; background: #001B3A; color: #fff; font-size: 12px; cursor: pointer; }
        h1 { text-align: center; font-size: 15px; text-transform: uppercase; letter-spacing: .5px; }
        .center { text-align: center; }
        .doc-title { margin-top: 4px; }
        .party { margin-top: 18px; }
        table.party-tbl td { border: 0; padding: 1px 0; vertical-align: top; }
        table.party-tbl td.label { width: 90px; }
        .tag { font-weight: 700; }
        ol { margin: 16px 0 0 20px; }
        ol li { margin-bottom: 10px; text-align: justify; white-space: pre-line; }
        .closing { margin-top: 16px; text-align: justify; }
        .sign { margin-top: 40px; width: 100%; }
        .sign td { width: 50%; vertical-align: top; padding: 0 10px; }
        .sign .role { font-weight: 700; }
        .sign .space { height: 60px; }
        .sign .name { border-top: 1px solid #111827; display: inline-block; padding-top: 4px; margin-top: 4px; }
        @page { size: A4; margin: 16mm; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; width: auto; min-height: auto; padding: 0; } }
    </style>
</head>
<body>
    @unless($forPdf)
        <div class="toolbar"><button onclick="window.print()">Print / Simpan PDF</button></div>
    @endunless
    <div class="sheet">
        <h1>Berita Acara Serah Terima Pekerjaan</h1>
        <p class="center">Pekerjaan {{ $dots($draft->job_title, 30) }}</p>
        <p class="center">Nomor : {{ $dots($draft->number, 20) }}</p>

        <p style="margin-top:16px;">
            Pada hari ini {{ $date ? $dayNames[$date->dayOfWeek] : '.................' }},
            tanggal {{ $date ? $date->format('d') : '........' }}
            bulan {{ $date ? $monthNames[$date->month] : '..................' }}
            tahun {{ $date ? $date->format('Y') : '.......' }} yang bertanda tangan dibawah ini :
        </p>

        <div class="party">
            <table class="party-tbl">
                <tr><td class="label">1. Nama</td><td>: {{ $dots($draft->pic_name, 40) }}</td></tr>
                <tr><td class="label">Jabatan</td><td>: {{ $dots($draft->pic_position, 40) }}</td></tr>
                <tr><td class="label">Alamat</td><td>: {{ $dots($draft->pic_address, 60) }}</td></tr>
            </table>
            <p>Selanjutnya disebut sebagai <span class="tag">PIHAK KESATU</span></p>
        </div>

        <div class="party">
            <table class="party-tbl">
                <tr><td class="label">2. Nama</td><td>: {{ $dots($draft->leader_name, 40) }}</td></tr>
                <tr><td class="label">Jabatan</td><td>: {{ $dots($draft->leader_position ?: 'Teknisi', 40) }}<br>CV. General Solusindo</td></tr>
                <tr><td class="label">Alamat</td><td>: Jl. Pondok Jati Blok AS-31 Sidoarjo</td></tr>
            </table>
            <p>Selanjutnya disebut sebagai <span class="tag">PIHAK KEDUA</span></p>
        </div>

        <p class="closing">
            Pihak Pertama dan Pihak Kedua secara bersama-sama selanjutnya disebut sebagai &ldquo;Para Pihak&rdquo;.
            Para Pihak dengan ini menerangkan dan menyatakan hal-hal sebagai berikut:
        </p>

        <ol>
            <li>Bahwa, sebelumnya Pihak Pertama dan Pihak Kedua telah mengadakan suatu kerja sama kontrak kerja berdasarkan {{ $referenceLabel }} Nomor : {{ $dots($referenceNumber, 20) }} tanggal {{ $referenceDate ? $referenceDate->format('d-m-Y') : '...............' }}, tentang Pekerjaan {{ $dots($draft->job_title, 30) }}</li>
            <li>Bahwa, Pihak Kedua telah melaksanakan {{ $dots($draft->work_description, 40) }}</li>
            <li>Bahwa, Perjanjian tersebut telah mewajibkan Pihak Kedua untuk menyerahkan pekerjaan kepada Pihak Kesatu, sesuai dengan {{ $referenceLabel }}.</li>
            <li>Bahwa, untuk melaksanakan serah terima Pekerjaan berdasarkan {{ $referenceLabel }} sebagaimana dimaksud angka 2 diatas, maka Pihak Kedua dengan ini menyerahkan Pekerjaan kepada Pihak Pertama sebagaimana Pihak Pertama dengan ini menerima Pekerjaan tersebut dari Pihak Kedua.</li>
            <li>Bahwa, dengan telah dilakukannya serah terima Pekerjaan berdasarkan Berita Acara ini, maka dengan demikian kewajiban Pihak Kedua untuk menyerahkan Pekerjaan kepada Pihak Pertama dan hak Pihak Pertama untuk menerima Pekerjaan tersebut dari Pihak Kedua berdasarkan Perjanjian telah dilaksanakan.</li>
            <li>Bahwa, dengan telah dilakukannya serah terima BAST dari Pihak Kedua kepada Pihak Kesatu maka Pihak Kesatu berkewajiban untuk membayarkan sisa tagihan kepada Pihak Kedua.</li>
            <li>Bahwa, Berita Acara ini merupakan bagian dari pelaksanaan Perjanjian dan sekaligus sebagai Tanda Terima dokumen diantara Para Pihak, sehingga oleh karenanya merupakan satu kesatuan dan bagian yang tidak terpisahkan dari Perjanjian.</li>
        </ol>

        <p class="closing">Demikian Berita Acara ini dibuat pada waktu sebagaimana telah disebutkan pada bagian awal Berita Acara ini.</p>

        <table class="sign">
            <tr>
                <td class="role center">PIHAK KEDUA<br>CV. GENERAL SOLUSINDO</td>
                <td class="role center">PIHAK KESATU</td>
            </tr>
            <tr>
                <td class="space"></td>
                <td class="space"></td>
            </tr>
            <tr>
                <td class="center"><span class="name">{{ $dots($draft->leader_name, 30) }}</span><br>{{ $draft->leader_position ?: 'Teknisi' }}</td>
                <td class="center"><span class="name">{{ $dots($draft->pic_name, 30) }}</span><br>{{ $draft->pic_position ?: '&nbsp;' }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
