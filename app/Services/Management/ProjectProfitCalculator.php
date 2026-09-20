<?php

namespace App\Services\Management;

use App\Enums\ProjectStatus;
use App\Models\Project;

/**
 * Hitung HPP, harga jual, dan profit satu project — dipakai bersama oleh
 * laporan "Profit Project" (Management\ProjectProfitController) dan ringkasan
 * pendapatan di Dashboard Management, supaya keduanya selalu memakai rumus
 * yang sama persis dan tidak pernah menampilkan angka yang berbeda untuk
 * data yang sama.
 *
 * HPP dihitung dari:
 * - Baris material: ActualProcurement.cost_price — otomatis "aktual kalau sudah
 *   ada, estimasi kalau belum", karena diisi = estimasi saat Project dibuat, lalu
 *   ditimpa Procurement dengan harga beli sungguhan begitu barang sudah disourcing.
 * - Baris jasa: cost_price dari Sales Order Line (estimasi). Kalau project dikerjakan vendor
 *   luar dan Procurement sudah mengisi Deal Vendor Jasa, total fee deal itu MENGGANTIKAN
 *   seluruh estimasi biaya jasa (fee yang disepakati/dibayar = biaya jasa sebenarnya).
 *
 * Harga jual = subtotal (DPP) Sales Order yang dikonfirmasi customer.
 *
 * Butuh $project sudah eager-load relasi: salesOrder, salesOrder.lines,
 * actualProcurements (lihat scopeWithProfitRelations()).
 */
class ProjectProfitCalculator
{
    /** @return array<string, mixed> */
    public static function rowFor(Project $project): array
    {
        $lines = $project->salesOrder->lines;

        $sellingTotal = round((float) $lines->sum('subtotal'), 2);
        $materialCost = round($project->actualProcurements->sum(
            fn ($ap) => (float) $ap->qty * (float) $ap->cost_price
        ), 2);
        $vendorFee = $project->vendorServicePayment?->total_fee;
        $serviceCost = $vendorFee !== null
            ? round((float) $vendorFee, 2)
            : round($lines->where('category', 'service')->sum(
                fn ($l) => (float) $l->qty * (float) $l->cost_price
            ), 2);
        $hpp = round($materialCost + $serviceCost, 2);
        $profit = round($sellingTotal - $hpp, 2);

        return [
            'id' => $project->id,
            'number' => $project->salesOrder->number,
            'customer' => $project->salesOrder->contact?->name,
            'company' => $project->salesOrder->contact?->company_name,
            'project_status' => $project->status,
            'project_status_label' => ProjectStatus::from($project->status)->label(),
            'is_won' => $project->salesOrder->status === 'won',
            'created_at' => $project->created_at->format('Y-m-d'),
            'hpp' => $hpp,
            'uses_vendor_fee' => $vendorFee !== null,
            'harga_jual' => $sellingTotal,
            'profit' => $profit,
            'margin_percent' => $hpp > 0 ? round($profit / $hpp * 100, 2) : null,
        ];
    }

    /** Relasi minimum yang harus di-eager-load supaya rowFor() tidak N+1. */
    public static function eagerLoads(): array
    {
        return [
            'salesOrder:id,number,contact_id,status',
            'salesOrder.contact:id,name,company_name',
            'salesOrder.lines:id,sales_order_id,category,qty,cost_price,subtotal',
            'actualProcurements:id,project_id,qty,cost_price',
            'vendorServicePayment:id,project_id,total_fee',
        ];
    }
}
