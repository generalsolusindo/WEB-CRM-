<?php

namespace App\Actions\Operational;

use App\Models\SowScopeSection;
use Illuminate\Support\Facades\Storage;

class DeleteSowScopeSection
{
    public function handle(SowScopeSection $section): void
    {
        foreach ($section->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->file_path);
            $attachment->delete();
        }

        $section->delete();
    }
}
