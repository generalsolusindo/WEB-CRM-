<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_update_and_delete_meeting(): void
    {
        [$sales, $lead] = $this->makeLead();

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/meetings", [
            'title' => 'Kickoff meeting',
            'meeting_date' => '2026-08-20',
            'location' => 'Kantor customer',
            'attendees' => 'Budi (IT), Sari (Finance)',
            'notes' => 'Diskusi kebutuhan jaringan.',
        ])->assertSessionHas('success');

        $meeting = Meeting::firstOrFail();
        $this->assertSame($lead->id, $meeting->lead_id);
        $this->assertSame($sales->id, $meeting->created_by);

        $this->actingAs($sales)->put("/sales/leads/{$lead->id}/meetings/{$meeting->id}", [
            'title' => 'Kickoff (revisi)',
            'meeting_date' => '2026-08-21',
            'notes' => 'Update jadwal.',
        ])->assertSessionHas('success');
        $this->assertSame('Kickoff (revisi)', $meeting->fresh()->title);

        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}/meetings/{$meeting->id}")
            ->assertSessionHas('success');
        $this->assertDatabaseCount('meetings', 0);
    }

    public function test_meeting_requires_title_date_and_notes(): void
    {
        [$sales, $lead] = $this->makeLead();

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/meetings", [
            'title' => '',
            'meeting_date' => '',
            'notes' => '',
        ])->assertSessionHasErrors(['title', 'meeting_date', 'notes']);
    }

    public function test_other_sales_cannot_touch_meetings_of_a_lead(): void
    {
        [$sales, $lead] = $this->makeLead();
        $other = User::factory()->create(['role' => 'sales']);
        $meeting = $lead->meetings()->create([
            'title' => 'X',
            'meeting_date' => '2026-08-20',
            'notes' => 'nota',
            'created_by' => $sales->id,
        ]);

        $this->actingAs($other)->post("/sales/leads/{$lead->id}/meetings", [
            'title' => 'Y', 'meeting_date' => '2026-08-20', 'notes' => 'z',
        ])->assertForbidden();

        $this->actingAs($other)->put("/sales/leads/{$lead->id}/meetings/{$meeting->id}", [
            'title' => 'Y', 'meeting_date' => '2026-08-20', 'notes' => 'z',
        ])->assertForbidden();

        $this->actingAs($other)->delete("/sales/leads/{$lead->id}/meetings/{$meeting->id}")
            ->assertForbidden();
    }

    public function test_meeting_from_another_lead_returns_404(): void
    {
        [$sales, $lead] = $this->makeLead();
        [, $otherLead] = $this->makeLead($sales);
        $meeting = $otherLead->meetings()->create([
            'title' => 'X',
            'meeting_date' => '2026-08-20',
            'notes' => 'nota',
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}/meetings/{$meeting->id}")
            ->assertNotFound();
    }

    public function test_lead_report_and_quotation_print_are_reachable(): void
    {
        [$sales, $lead] = $this->makeLead();
        $lead->update(['type' => 'opportunity', 'stage' => 'won', 'source' => 'Referral']);

        $this->actingAs($sales)->get('/sales/reports/leads')->assertOk();
        $this->actingAs($sales)->get('/sales/reports/leads?from=2026-01-01&to=2026-12-31')->assertOk();
    }

    /**
     * @return array{User, Lead}
     */
    private function makeLead(?User $sales = null): array
    {
        $sales ??= User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'lead',
            'stage' => 'new',
        ]);

        return [$sales, $lead];
    }
}
