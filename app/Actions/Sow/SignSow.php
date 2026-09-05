<?php

namespace App\Actions\Sow;

use App\Enums\SowStatus;
use App\Models\Notification;
use App\Models\Sow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menangani keempat tahap tanda tangan digital SOW: Teknisi, PIC Vendor,
 * Admin Project (Operasional), dan Direktur (Manager) — satu action generik
 * supaya urutan status & notifikasi konsisten di satu tempat.
 */
class SignSow
{
    public function handle(Sow $sow, User $signer, string $role, string $signature): Sow
    {
        return DB::transaction(function () use ($sow, $signer, $role, $signature) {
            $locked = Sow::query()
                ->with(['project.salesOrder.contact', 'project.vendor', 'technician'])
                ->whereKey($sow->id)->lockForUpdate()->firstOrFail();

            [$expectedStatus, $nextStatus] = match ($role) {
                'technician' => [SowStatus::PendingTechnicianSignature->value, SowStatus::PendingVendorSignature->value],
                'vendor' => [SowStatus::PendingVendorSignature->value, SowStatus::PendingHrVerification->value],
                'admin' => [SowStatus::PendingAdminSignature->value, SowStatus::PendingDirectorSignature->value],
                'director' => [SowStatus::PendingDirectorSignature->value, SowStatus::Completed->value],
                default => throw new \InvalidArgumentException("Unknown SOW signer role: {$role}"),
            };

            if ($locked->status !== $expectedStatus) {
                throw ValidationException::withMessages(['sow' => 'SOW ini sudah tidak menunggu tanda tangan Anda.']);
            }

            $signatureFields = match ($role) {
                'technician' => ['technician_signature' => $signature, 'technician_signed_at' => now()],
                'vendor' => ['vendor_signature' => $signature, 'vendor_signed_at' => now(), 'vendor_signed_by' => $signer->id],
                'admin' => ['admin_signature' => $signature, 'admin_signed_at' => now(), 'admin_signed_by' => $signer->id],
                'director' => ['director_signature' => $signature, 'director_signed_at' => now(), 'director_signed_by' => $signer->id],
            };

            $locked->update(['status' => $nextStatus, ...$signatureFields]);

            $customer = $locked->project->salesOrder?->contact?->name ?? 'customer';

            match ($role) {
                'technician' => $this->notifyVendor($locked, $customer),
                'vendor' => $this->notifyHr($locked, $customer, 'sow.pending_hr_verification', 'perlu diverifikasi tanda tangannya'),
                'admin' => $this->notifyManagement($locked, $customer),
                'director' => $this->notifyCreator($locked, $customer, 'sow.completed', 'sudah selesai — semua pihak sudah tanda tangan'),
            };

            return $locked->refresh();
        });
    }

    private function notifyVendor(Sow $sow, string $customer): void
    {
        $vendorUser = User::query()->where('role', 'vendor')->where('vendor_id', $sow->project->vendor_id)->first();

        if ($vendorUser) {
            Notification::updateOrCreate(
                ['user_id' => $vendorUser->id, 'type' => 'sow.pending_vendor_signature', 'related_type' => $sow->getMorphClass(), 'related_id' => $sow->id],
                ['message' => "SOW {$sow->number} ({$customer}) perlu Anda tanda tangani.", 'is_sent' => true, 'read_at' => null],
            );
        }
    }

    private function notifyHr(Sow $sow, string $customer, string $type, string $verb): void
    {
        foreach (User::query()->where('role', 'hr')->where('is_active', true)->get() as $hr) {
            Notification::updateOrCreate(
                ['user_id' => $hr->id, 'type' => $type, 'related_type' => $sow->getMorphClass(), 'related_id' => $sow->id],
                ['message' => "SOW {$sow->number} ({$customer}) {$verb}.", 'is_sent' => true, 'read_at' => null],
            );
        }
    }

    private function notifyManagement(Sow $sow, string $customer): void
    {
        foreach (User::query()->where('role', 'management')->where('is_active', true)->get() as $manager) {
            Notification::updateOrCreate(
                ['user_id' => $manager->id, 'type' => 'sow.pending_director_signature', 'related_type' => $sow->getMorphClass(), 'related_id' => $sow->id],
                ['message' => "SOW {$sow->number} ({$customer}) menunggu tanda tangan Anda.", 'is_sent' => true, 'read_at' => null],
            );
        }
    }

    private function notifyCreator(Sow $sow, string $customer, string $type, string $verb): void
    {
        if (! $sow->created_by) {
            return;
        }

        Notification::updateOrCreate(
            ['user_id' => $sow->created_by, 'type' => $type, 'related_type' => $sow->getMorphClass(), 'related_id' => $sow->id],
            ['message' => "SOW {$sow->number} ({$customer}) {$verb}.", 'is_sent' => true, 'read_at' => null],
        );
    }
}
