<?php

namespace App\Actions\Operational;

use App\Models\Sow;
use App\Models\SowScopeSection;

class AddSowScopeSection
{
    public function handle(Sow $sow, string $title, ?string $content): SowScopeSection
    {
        $nextPosition = (int) $sow->scopeSections()->max('position') + 1;

        return $sow->scopeSections()->create([
            'position' => $nextPosition,
            'title' => $title,
            'content' => $content,
        ]);
    }
}
