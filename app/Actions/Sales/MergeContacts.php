<?php

namespace App\Actions\Sales;

use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

/**
 * Gabungkan dua Contact duplikat jadi satu — dipakai kalau satu customer/perusahaan
 * ternyata kepencet dibuat dua kali. $duplicate dipindah seluruh datanya ke $keep,
 * lalu $duplicate dihapus.
 *
 * contact_id disimpan berulang (denormalized) di Lead, Quotation, DAN Sales Order —
 * bukan cuma diturunkan lewat Lead — jadi ketiganya harus ikut dipindah, bukan cuma
 * Lead saja, supaya tidak ada dokumen yang diam-diam masih "menunjuk" ke contact yang
 * sudah dihapus.
 */
class MergeContacts
{
    public function handle(Contact $keep, Contact $duplicate): Contact
    {
        return DB::transaction(function () use ($keep, $duplicate) {
            Lead::where('contact_id', $duplicate->id)->update(['contact_id' => $keep->id]);
            Quotation::where('contact_id', $duplicate->id)->update(['contact_id' => $keep->id]);
            SalesOrder::where('contact_id', $duplicate->id)->update(['contact_id' => $keep->id]);

            Attachment::where('attachable_type', Contact::class)
                ->where('attachable_id', $duplicate->id)
                ->update(['attachable_id' => $keep->id]);

            // Isi field yang masih kosong di $keep pakai data dari $duplicate (mis. NPWP
            // atau alamat yang kebetulan cuma terisi di salah satu duplikatnya).
            $fillable = ['email', 'phone', 'address', 'npwp', 'notes'];
            $fill = [];
            foreach ($fillable as $field) {
                if (blank($keep->{$field}) && filled($duplicate->{$field})) {
                    $fill[$field] = $duplicate->{$field};
                }
            }
            if ($fill !== []) {
                $keep->update($fill);
            }

            $duplicate->delete();

            return $keep->fresh();
        });
    }
}
