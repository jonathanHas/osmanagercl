<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\ElectricityBill;
use App\Services\Electricity\ElectricityBillAiReader;
use App\Services\Electricity\ElectricityBillImportService;
use App\Services\Electricity\ElectricityBillValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Electricity bills: upload, review beside the PDF, correct, confirm.
 * See docs/features/electricity.md.
 */
class ElectricityBillController extends Controller
{
    public function __construct(private ElectricityBillImportService $importer) {}

    public function index(Request $request)
    {
        $bills = ElectricityBill::query()
            ->with(['supplyPoint', 'invoice'])
            ->when($request->query('status') === 'needs_review',
                fn ($q) => $q->where('status', ElectricityBill::STATUS_NEEDS_REVIEW))
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->get();

        return view('management.electricity.bills', [
            'bills' => $bills,
            'filter' => $request->query('status'),
            'needsReview' => ElectricityBill::where('status', ElectricityBill::STATUS_NEEDS_REVIEW)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1|max:40',
            'files.*' => 'file|mimes:pdf|max:32768',
        ]);

        $done = ['imported' => 0, 'updated' => 0, 'duplicate' => 0];
        $review = 0;
        $problems = [];

        foreach ($request->file('files') as $file) {
            try {
                $outcome = $this->importer->importFromPdf($file->getRealPath(), null, $file->getClientOriginalName(), true);
            } catch (\Throwable $e) {
                $problems[] = $file->getClientOriginalName().': '.$e->getMessage();

                continue;
            }

            if ($outcome['result'] === 'unsupported') {
                $problems[] = $file->getClientOriginalName().': '.$outcome['message'];

                continue;
            }
            $done[$outcome['result']]++;
            if ($outcome['result'] !== 'duplicate' && ! $outcome['bill']->isConfirmed()) {
                $review++;
            }
        }

        $this->importer->revalidateAll();

        $message = sprintf('%d loaded', $done['imported'] + $done['updated']);
        if ($done['duplicate']) {
            $message .= sprintf(', %d already on file', $done['duplicate']);
        }
        if ($review) {
            $message .= sprintf(', %d to review', $review);
        }

        return redirect()->route('management.electricity.bills.index')
            ->with('success', $message.'.')
            ->with('problems', $problems);
    }

    public function importFromInvoices()
    {
        $outcome = $this->importer->importFromInvoices();
        $c = $outcome['counts'];

        return back()->with('success', sprintf(
            'Accounts invoices checked: %d loaded, %d already on file, %d not electricity bills, %d with no PDF on disk%s.',
            $c['imported'] + $c['updated'], $c['duplicate'], $c['unsupported'], $c['missing'],
            $c['failed'] ? ", {$c['failed']} failed" : ''
        ));
    }

    public function show(ElectricityBill $bill)
    {
        $this->importer->revalidate($bill);
        $bill->load(['charges', 'readings.meter', 'supplyPoint.meters', 'invoice', 'confirmedBy']);

        return view('management.electricity.bill', [
            'bill' => $bill,
            'data' => $this->importer->toArray($bill),
            'hasPdf' => (bool) $bill->pdfPath(),
            'aiAvailable' => app(ElectricityBillAiReader::class)->isAvailable(),
            'categories' => ElectricityBillValidator::CATEGORIES,
            'bands' => ElectricityBillValidator::BANDS,
            'readTypes' => ElectricityBillValidator::READ_TYPES,
        ]);
    }

    /** Save a manager's corrections. The bill goes back to review until confirmed. */
    public function update(Request $request, ElectricityBill $bill)
    {
        $data = $request->validate([
            'provider' => 'required|string|max:60',
            'mprn' => 'required|digits:11',
            'account_number' => 'nullable|string|max:40',
            'bill_number' => 'required|string|max:60',
            'document_type' => ['required', Rule::in(['invoice', 'credit_note'])],
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'tariff' => 'nullable|string|max:255',
            'net_total' => 'required|numeric',
            'vat_total' => 'required|numeric',
            'export_credit_total' => 'nullable|numeric',
            'gross_total' => 'required|numeric',
            'charges' => 'array',
            'charges.*.category' => ['required', Rule::in(ElectricityBillValidator::CATEGORIES)],
            'charges.*.band' => ['nullable', Rule::in(ElectricityBillValidator::BANDS)],
            'charges.*.description' => 'nullable|string|max:255',
            'charges.*.quantity' => 'nullable|numeric',
            'charges.*.unit' => 'nullable|string|max:20',
            'charges.*.rate' => 'nullable|numeric',
            'charges.*.period_start' => 'nullable|date',
            'charges.*.period_end' => 'nullable|date',
            'charges.*.net_amount' => 'required|numeric',
            'charges.*.vat_rate' => 'nullable|numeric',
            'readings' => 'array',
            'readings.*.meter_serial' => 'required|string|max:40',
            'readings.*.band' => ['required', Rule::in(ElectricityBillValidator::BANDS)],
            'readings.*.period_start' => 'required|date',
            'readings.*.period_end' => 'required|date',
            'readings.*.previous_reading' => 'required|numeric',
            'readings.*.previous_type' => ['required', Rule::in(ElectricityBillValidator::READ_TYPES)],
            'readings.*.present_reading' => 'required|numeric',
            'readings.*.present_type' => ['required', Rule::in(ElectricityBillValidator::READ_TYPES)],
            'readings.*.multiplier' => 'nullable|numeric',
            'readings.*.kwh' => 'required|numeric',
            'readings.*.is_reversal' => 'nullable|boolean',
        ]);

        $number = fn ($v) => $v === null || $v === '' ? null : (float) $v;

        $data['export_credit_total'] = (float) ($data['export_credit_total'] ?? 0);
        foreach (['net_total', 'vat_total', 'gross_total'] as $field) {
            $data[$field] = (float) $data[$field];
        }
        $data['charges'] = array_map(fn ($c) => [
            'quantity' => $number($c['quantity'] ?? null),
            'rate' => $number($c['rate'] ?? null),
            'vat_rate' => $number($c['vat_rate'] ?? null),
            'net_amount' => (float) $c['net_amount'],
            'unit' => isset($c['unit']) ? strtolower($c['unit']) : null,
        ] + $c, array_values($data['charges'] ?? []));
        $data['readings'] = array_map(fn ($r) => [
            'previous_reading' => (float) $r['previous_reading'],
            'present_reading' => (float) $r['present_reading'],
            'multiplier' => (float) ($r['multiplier'] ?? 1) ?: 1.0,
            'kwh' => (float) $r['kwh'],
            'is_reversal' => (bool) ($r['is_reversal'] ?? false),
        ] + $r, array_values($data['readings'] ?? []));
        $data['mic_kva'] = $bill->supplyPoint?->mic_kva;
        $data['dg_group'] = $bill->supplyPoint?->dg_group;
        $data['warnings'] = $bill->warnings ?? [];

        $clash = ElectricityBill::where('id', '!=', $bill->id)
            ->where('provider', $data['provider'])
            ->where('bill_number', $data['bill_number'])
            ->where('document_type', $data['document_type'])
            ->exists();
        if ($clash) {
            return back()->withInput()->with('error', "Another {$data['provider']} bill already has number {$data['bill_number']}.");
        }

        $this->importer->store($data, 'manual', $bill);

        return redirect()->route('management.electricity.bills.show', $bill)
            ->with('success', 'Changes saved and checks re-run. Confirm the bill when it is right.');
    }

    public function confirm(Request $request, ElectricityBill $bill)
    {
        $this->importer->revalidate($bill);

        if ($bill->hasBlockingFailure() && ! $request->boolean('override')) {
            return back()->with('error', 'Some checks still fail. Correct the figures, or confirm anyway if the bill itself is wrong.');
        }

        $bill->update([
            'status' => ElectricityBill::STATUS_CONFIRMED,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        $next = ElectricityBill::where('status', ElectricityBill::STATUS_NEEDS_REVIEW)->orderBy('issue_date')->first();

        return $next
            ? redirect()->route('management.electricity.bills.show', $next)->with('success', "Bill {$bill->bill_number} confirmed. Next one to review:")
            : redirect()->route('management.electricity.bills.index')->with('success', "Bill {$bill->bill_number} confirmed.");
    }

    public function reread(ElectricityBill $bill)
    {
        try {
            $this->importer->rereadWithAi($bill);
        } catch (\Throwable $e) {
            return back()->with('error', 'AI reading failed: '.$e->getMessage());
        }

        return redirect()->route('management.electricity.bills.show', $bill)
            ->with('success', 'Read again with AI. Check the figures against the PDF and confirm.');
    }

    public function destroy(ElectricityBill $bill)
    {
        if ($bill->file_path) {
            Storage::disk('private')->delete($bill->file_path);
        }
        $number = $bill->bill_number;
        $bill->delete();
        $this->importer->revalidateAll();

        return redirect()->route('management.electricity.bills.index')->with('success', "Bill {$number} removed.");
    }

    public function pdf(ElectricityBill $bill)
    {
        $path = $bill->pdfPath();
        abort_unless($path, 404);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.($bill->bill_number ?: 'bill').'.pdf"',
        ]);
    }
}
