<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Till;
use App\Repositories\CashReconciliationRepository;
use App\Services\Cash\TillCountPage;
use App\Services\Cash\TillCountService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

/**
 * The office side of the till count: the same count form as the Shop screen,
 * with navigation, history, the legacy re-import and the CSV export around it.
 */
class CashReconciliationController extends Controller
{
    public function __construct(
        private readonly TillCountPage $page,
        private readonly TillCountService $service,
        private readonly CashReconciliationRepository $repository,
    ) {}

    public function index(Request $request)
    {
        $page = $this->page->build($request);

        return view('management.cash-reconciliation.index', $page + [
            'clientState' => $this->page->clientState($page),
            'history' => $this->service->history(28),
        ]);
    }

    /**
     * Re-import the old system's count for a till close. Only for days before the
     * cut-over to the new count; overwrites whatever the row holds.
     */
    public function sync(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'till_id' => ['required', 'integer', 'exists:App\Models\Till,id'],
            'session' => ['nullable', 'string', 'max:64'],
        ]);

        $date = Carbon::parse($data['date']);
        $till = Till::findOrFail($data['till_id']);
        $redirect = redirect()->route('cash-reconciliation.index', ['date' => $date->toDateString(), 'till' => $till->id]);

        try {
            $sessions = $this->service->sessionsOn($till, $date);
            $session = $sessions->firstWhere('MONEY', $data['session'] ?? null) ?? $sessions->last();

            if (! $session) {
                return $redirect->with('error', "{$till->name} did not close on {$date->toDateString()}.");
            }

            $result = $this->service->importFromLegacy($session, $till, overwrite: true, actor: $request->user());

            return $result
                ? $redirect->with('success', 'Count re-imported from the old system.')
                : $redirect->with('error', 'The old system has no count for this close.');
        } catch (\Throwable $e) {
            Log::error('Cash reconciliation sync error: '.$e->getMessage());

            return $redirect->with('error', 'Failed to sync from the old system: '.$e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'till_id' => 'nullable|integer',
        ]);

        try {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));

            $csv = $this->repository->exportToCsv($startDate, $endDate, $request->input('till_id'));

            $filename = sprintf('cash-reconciliation-%s-to-%s.csv', $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));

            return Response::make($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (\Throwable $e) {
            Log::error('Export error: '.$e->getMessage());

            return back()->with('error', 'Failed to export data: '.$e->getMessage());
        }
    }
}
