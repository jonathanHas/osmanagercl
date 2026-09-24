<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Shop mode receive delivery: the open-session list and the scan screen.
 *
 * Both sit on the legacy scan sessions staff already use (`deliveriesScan` /
 * `deliveriesScanItems` on the POS connection) and on the legacy endpoints for
 * scanning and correcting quantities. Everything else about a delivery —
 * financials, case units, outer barcodes, translations, completion — stays on
 * the office match page.
 */
class DeliveryController extends Controller
{
    public function index(): View
    {
        $suppliers = DB::connection('pos')
            ->table('suppliers')
            ->orderBy('Supplier')
            ->get();

        $sessions = DB::connection('pos')
            ->table('deliveriesScan')
            ->select(
                'deliveriesScan.ID',
                'deliveriesScan.supID',
                'deliveriesScan.dateUpload',
                'deliveriesScan.status',
                'suppliers.Supplier'
            )
            ->leftJoin('suppliers', 'deliveriesScan.supID', '=', 'suppliers.SupplierID')
            ->orderByDesc('dateUpload')
            ->limit(50)
            ->get();

        $counts = DB::connection('pos')
            ->table('deliveriesScanItems')
            ->select('delID', DB::raw('COUNT(*) as item_count'), DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('delID')
            ->pluck('item_count', 'delID')
            ->toArray();

        $open = $sessions->where('status', 0)->values();
        $completed = $sessions->where('status', 1)->take(10)->values();

        return view('shop.deliveries', compact('suppliers', 'open', 'completed', 'counts'));
    }

    public function scan(Request $request): View
    {
        return view('shop.delivery-scan', ['session' => $this->session($request)]);
    }

    public function summary(Request $request): View
    {
        return view('shop.delivery-summary', ['session' => $this->session($request)]);
    }

    /**
     * The session both screens are about. Rows are fetched client-side from
     * delivery-legacy.items, so this is only the header.
     *
     * @return array{id: string, supplierId: string, supplier: ?string, date: mixed, completed: bool}
     */
    private function session(Request $request): array
    {
        $validated = $request->validate([
            'delID' => 'required|string',
            'supplierID' => 'required|string',
        ]);

        $row = DB::connection('pos')
            ->table('deliveriesScan')
            ->select(
                'deliveriesScan.ID',
                'deliveriesScan.supID',
                'deliveriesScan.dateUpload',
                'deliveriesScan.status',
                'suppliers.Supplier'
            )
            ->leftJoin('suppliers', 'deliveriesScan.supID', '=', 'suppliers.SupplierID')
            ->where('deliveriesScan.ID', $validated['delID'])
            ->first();

        abort_if(! $row, 404);

        return [
            'id' => $row->ID,
            'supplierId' => $validated['supplierID'],
            'supplier' => $row->Supplier,
            'date' => $row->dateUpload,
            'completed' => (int) $row->status === 1,
        ];
    }
}
