<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerInvoiceService
{
    /**
     * Create a draft invoice with line items.
     *
     * @param  array  $data  Invoice data: customer_id (optional), customer snapshot fields, issue_date, due_date, notes.
     * @param  array  $items  Array of item data (each: pos_product_id?, pos_product_code?, description, quantity, unit_price (net), vat_rate).
     */
    public function createDraft(array $data, array $items = []): CustomerInvoice
    {
        return DB::transaction(function () use ($data, $items) {
            $invoice = CustomerInvoice::create(array_merge($data, [
                'status' => CustomerInvoice::STATUS_DRAFT,
                'created_by' => Auth::id(),
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
            ]));

            foreach ($items as $position => $itemData) {
                $invoice->items()->create(array_merge($itemData, [
                    'position' => $itemData['position'] ?? $position,
                ]));
            }

            // Trigger a final recalc in case items array was empty
            $invoice->refresh();
            $invoice->calculateTotals();

            return $invoice;
        });
    }

    /**
     * Replace an invoice's line items wholesale (used by edit/update on a draft).
     *
     * Pass $force=true to override the draft-only guard — used by admins editing
     * an already-issued invoice. Voided invoices are still rejected.
     */
    public function syncItems(CustomerInvoice $invoice, array $items, bool $force = false): void
    {
        if ($invoice->status === CustomerInvoice::STATUS_VOID) {
            throw new \DomainException('Voided invoices cannot be edited.');
        }
        if (! $invoice->isEditable() && ! $force) {
            throw new \DomainException('Only draft invoices can be edited.');
        }

        DB::transaction(function () use ($invoice, $items) {
            $invoice->items()->delete();

            foreach ($items as $position => $itemData) {
                $invoice->items()->create(array_merge($itemData, [
                    'position' => $itemData['position'] ?? $position,
                ]));
            }

            $invoice->refresh();
            $invoice->calculateTotals();
        });
    }

    /**
     * Stamp the audit fields when an admin edits a non-draft invoice.
     */
    public function markAdminEdited(CustomerInvoice $invoice): void
    {
        $invoice->last_edited_at = now();
        $invoice->last_edited_by = Auth::id();
        $invoice->save();
    }

    /**
     * Build a line item payload from a POS product.
     * Returns NET unit price + VAT rate snapshotted from the product/tax records.
     */
    public function buildItemFromProduct(Product $product, float $quantity = 1.0): array
    {
        return [
            'pos_product_id' => $product->ID,
            'pos_product_code' => $product->CODE,
            'description' => $product->NAME,
            'quantity' => $quantity,
            'unit_price' => (float) $product->PRICESELL,
            'vat_rate' => (float) $product->getVatRate(),
        ];
    }

    /**
     * Issue a draft invoice: assigns sequential number and flips status.
     * Concurrency-safe via lockForUpdate on the sequence row.
     */
    public function issue(CustomerInvoice $invoice): CustomerInvoice
    {
        if (! $invoice->isEditable()) {
            throw new \DomainException('Only draft invoices can be issued.');
        }

        if ($invoice->items()->count() === 0) {
            throw new \DomainException('Cannot issue an invoice with no line items.');
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->refresh();
            $invoice->calculateTotals();

            $invoice->invoice_number = $this->nextInvoiceNumber($invoice->issue_date->year);
            $invoice->status = CustomerInvoice::STATUS_ISSUED;
            $invoice->save();

            return $invoice;
        });
    }

    public function void(CustomerInvoice $invoice): CustomerInvoice
    {
        if ($invoice->status === CustomerInvoice::STATUS_VOID) {
            return $invoice;
        }

        $invoice->status = CustomerInvoice::STATUS_VOID;
        $invoice->voided_at = now();
        $invoice->voided_by = Auth::id();
        $invoice->save();

        return $invoice;
    }

    /**
     * Allocate the next invoice number for a given year.
     * MUST be called inside a DB transaction. Uses lockForUpdate to be concurrency-safe.
     */
    public function nextInvoiceNumber(int $year): string
    {
        $row = DB::table('customer_invoice_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            DB::table('customer_invoice_sequences')->insert([
                'year' => $year,
                'last_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $next = 1;
        } else {
            $next = $row->last_number + 1;
            DB::table('customer_invoice_sequences')
                ->where('year', $year)
                ->update(['last_number' => $next, 'updated_at' => now()]);
        }

        return sprintf('INV-%d-%05d', $year, $next);
    }

    public function findOrCreateCustomer(array $data): Customer
    {
        if (! empty($data['id'])) {
            return Customer::findOrFail($data['id']);
        }

        return Customer::create(array_merge($data, [
            'created_by' => Auth::id(),
        ]));
    }
}
