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

        // Open sessions are never windowed. Taking the most recent N and then
        // filtering hid an open session older than the window while the Home badge
        // (which counts them all) still showed it — found in the live walkthrough
        // on 2026-09-24 with 1,201 sessions. There are only ever a handful open.
        $open = $this->sessions()->where('deliveriesScan.status', 0)->get();

        $completed = $this->sessions()->where('deliveriesScan.status', 1)->limit(10)->get();

        $counts = DB::connection('pos')
            ->table('deliveriesScanItems')
            ->select('delID', DB::raw('COUNT(*) as item_count'), DB::raw('SUM(quantity) as total_qty'))
            ->whereIn('delID', $open->pluck('ID')->merge($completed->pluck('ID'))->all())
            ->groupBy('delID')
            ->pluck('item_count', 'delID')
            ->toArray();

        return view('shop.deliveries', compact('suppliers', 'open', 'completed', 'counts'));
    }

    /**
     * Sessions with their supplier, newest first. The caller decides which status
     * it wants and whether to limit.
     */
    private function sessions(): \Illuminate\Database\Query\Builder
    {
        return DB::connection('pos')
            ->table('deliveriesScan')
            ->select(
                'deliveriesScan.ID',
                'deliveriesScan.supID',
                'deliveriesScan.dateUpload',
                'deliveriesScan.status',
                'suppliers.Supplier'
            )
            ->leftJoin('suppliers', 'deliveriesScan.supID', '=', 'suppliers.SupplierID')
            ->orderByDesc('dateUpload');
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
