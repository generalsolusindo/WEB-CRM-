<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreMeetingRequest;
use App\Http\Requests\Sales\UpdateMeetingRequest;
use App\Models\Lead;
use App\Models\Meeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MeetingController extends Controller
{
    public function store(StoreMeetingRequest $request, Lead $lead): RedirectResponse
    {
        $lead->meetings()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Data meeting berhasil ditambahkan.');
    }

    public function update(UpdateMeetingRequest $request, Lead $lead, Meeting $meeting): RedirectResponse
    {
        $this->ensureMeetingBelongsToLead($lead, $meeting);
        $meeting->update($request->validated());

        return back()->with('success', 'Data meeting berhasil diperbarui.');
    }

    public function destroy(Lead $lead, Meeting $meeting): RedirectResponse
    {
        $this->ensureMeetingBelongsToLead($lead, $meeting);
        Gate::authorize('delete', $meeting);
        $meeting->delete();

        return back()->with('success', 'Data meeting berhasil dihapus.');
    }

    private function ensureMeetingBelongsToLead(Lead $lead, Meeting $meeting): void
    {
        abort_unless($meeting->lead_id === $lead->id, 404);
    }
}
