<?php

namespace App\Actions\Operational;

use App\Models\SowScopeSection;

class UpdateSowScopeSection
{
    public function handle(SowScopeSection $section, string $title, ?string $content): SowScopeSection
    {
        $section->update(['title' => $title, 'content' => $content]);

        return $section;
    }
}
