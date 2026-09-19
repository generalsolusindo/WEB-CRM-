<?php

namespace App\Policies;

use App\Enums\LeadType;
use App\Enums\SurveyStatus;
use App\Models\Lead;
use App\Models\Survey;
use App\Models\User;

class SurveyPolicy
{
    /** Procurement inbox. */
    public function viewAny(User $user): bool
    {
        return $user->role === 'procurement' && $user->is_active;
    }

    public function view(User $user, Survey $survey): bool
    {
        return $user->role === 'procurement' && $user->is_active;
    }

    /** Inbox Finance. */
    public function viewAnyFinance(User $user): bool
    {
        return $user->role === 'finance' && $user->is_active;
    }

    /** Monitoring read-only Management — cuma daftar, tidak ada aksi/detail. */
    public function viewAnyManagement(User $user): bool
    {
        return $user->role === 'management' && $user->is_active;
    }

    public function viewFinance(User $user, Survey $survey): bool
    {
        return $user->role === 'finance' && $user->is_active;
    }

    /** Finance menerbitkan invoice survey / menandai biaya tercatat. */
    public function handleFinance(User $user, Survey $survey): bool
    {
        return $user->role === 'finance'
            && $user->is_active
            && in_array($survey->status, [
                SurveyStatus::FinanceReview->value,
                SurveyStatus::AwaitingPayment->value,
            ], true);
    }

    /** Sales meminta survey untuk opportunity miliknya. */
    public function create(User $user, Lead $lead): bool
    {
        return $user->role === 'sales'
            && $user->is_active
            && $lead->sales_id === $user->id
            && $lead->type === LeadType::Opportunity->value;
    }

    /** Sales menutup survey terverifikasi & (opsional) menyalin item ke requirement. */
    public function finalize(User $user, Survey $survey): bool
    {
        return $user->role === 'sales'
            && $user->is_active
            && $survey->loadMissing('lead')->lead?->sales_id === $user->id
            && $survey->status === SurveyStatus::Verified->value;
    }

    /** Pembatalan survey — Sales (tahap awal) atau Operasional (saat eksekusi). */
    public function cancel(User $user, Survey $survey): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role === 'sales'
            && $survey->loadMissing('lead')->lead?->sales_id === $user->id) {
            return in_array($survey->status, [
                SurveyStatus::Requested->value,
                SurveyStatus::FinanceReview->value,
                SurveyStatus::AwaitingPayment->value,
                SurveyStatus::AwaitingBriefing->value,
            ], true);
        }

        if ($user->role === 'operational') {
            return in_array($survey->status, [
                SurveyStatus::AwaitingBriefing->value,
                SurveyStatus::InProgress->value,
                SurveyStatus::ReportReview->value,
            ], true);
        }

        return false;
    }

    /**
     * Procurement menetapkan / mengganti surveyor + biaya.
     * Bisa diubah selama belum dijadwalkan Operasional dan Finance belum menerbitkan invoice.
     */
    public function source(User $user, Survey $survey): bool
    {
        if (! ($user->role === 'procurement' && $user->is_active)) {
            return false;
        }

        if (! in_array($survey->status, [
            SurveyStatus::Requested->value,
            SurveyStatus::FinanceReview->value,
            SurveyStatus::AwaitingBriefing->value,
        ], true)) {
            return false;
        }

        // Invoice survey sudah ada -> biaya terkunci, jangan ubah surveyor.
        return ! $survey->invoices()->where('status', '!=', 'cancelled')->exists();
    }

    /** Operasional: inbox eksekusi survey. */
    public function viewAnyOperational(User $user): bool
    {
        return $user->role === 'operational' && $user->is_active;
    }

    public function viewOperational(User $user, Survey $survey): bool
    {
        return $user->role === 'operational' && $user->is_active;
    }

    /** Operasional memberi arahan ke surveyor. */
    public function brief(User $user, Survey $survey): bool
    {
        return $user->role === 'operational'
            && $user->is_active
            && $survey->status === SurveyStatus::AwaitingBriefing->value;
    }

    /** Operasional mengubah komposisi tim selama survey berjalan. */
    public function updateTeam(User $user, Survey $survey): bool
    {
        return $user->role === 'operational'
            && $user->is_active
            && $survey->status === SurveyStatus::InProgress->value;
    }

    /** Operasional memverifikasi laporan surveyor. */
    public function verifyReport(User $user, Survey $survey): bool
    {
        return $user->role === 'operational'
            && $user->is_active
            && $survey->status === SurveyStatus::ReportReview->value;
    }

    /** Surveyor yang ditugaskan melihat survey-nya. */
    public function viewAsSurveyor(User $user, Survey $survey): bool
    {
        return $user->canWorkAsSurveyor()
            && $survey->isSurveyor($user);
    }

    /** Absen kehadiran (selfie) — anggota tim, hanya selama survey berjalan. */
    public function checkIn(User $user, Survey $survey): bool
    {
        return $this->viewAsSurveyor($user, $survey)
            && $survey->status === SurveyStatus::InProgress->value;
    }

    /** Absen pulang (selfie) — hanya setelah absen kedatangan dan belum absen pulang. */
    public function checkOut(User $user, Survey $survey): bool
    {
        return $this->checkIn($user, $survey)
            && $survey->hasCheckedIn($user)
            && ! $survey->hasCheckedOut($user);
    }

    /** Anggota tim mengisi draft / unggah lampiran (hanya saat survey berjalan, wajib sudah absen). */
    public function workReport(User $user, Survey $survey): bool
    {
        return $this->viewAsSurveyor($user, $survey)
            && $survey->status === SurveyStatus::InProgress->value
            && $survey->hasCheckedIn($user);
    }

    /** Hanya leader tim yang boleh mengirim laporan final ke Operasional. */
    public function submitReport(User $user, Survey $survey): bool
    {
        return $user->canWorkAsSurveyor()
            && $survey->isLeader($user)
            && $survey->status === SurveyStatus::InProgress->value
            && $survey->hasCheckedIn($user);
    }
}
