<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CreateSalesOrder;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ConfirmQuotationRequest;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuotationConfirmationController extends Controller
{
    public function create(Quotation $quotation): Response
    {
        Gate::authorize('confirm', $quotation);
        $quotation->load([
            'contact:id,name,company_name,email,phone',
            'lines:id,quotation_id,item_name,category,description,sourcing_note,qty,unit,cost_price,selling_price,discount_percent,discount_amount,markup_percent,tax_id,tax_rate,subtotal',
            'lines.tax:id,name,rate',
        ]);

        return Inertia::render('Sales/Quotations/Confirm', [
            'quotation' => $quotation,
            'orderTypes' => OrderType::options(),
            'totals' => \App\Services\Sales\DocumentTotals::of($quotation->lines),
        ]);
    }

    public function store(
        ConfirmQuotationRequest $request,
        Quotation $quotation,
        CreateSalesOrder $action,
    ): RedirectResponse {
        Gate::authorize('confirm', $quotation);
        $salesOrder = $action->handle(
            $quotation,
            $request->user(),
            OrderType::from($request->validated('order_type')),
            [
                'quotation_signed' => $request->file('signed_quotation'),
                'purchase_order' => $request->file('purchase_order'),
            ],
            $request->validated('po_number'),
        );

        return redirect()->route('sales.sales-orders.show', $salesOrder)
            ->with('success', 'Deal dikonfirmasi dan Sales Order berhasil dibuat.');
    }
}
