<?php

namespace App\Services\Cash;

use App\Models\Till;
use App\Services\Shop\IdentityVerifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Builds the data both till-count pages (Shop and office) render: which till and
 * close are selected, the form state, the people who can identify themselves,
 * and the current "counting as".
 */
class TillCountPage
{
    public function __construct(
        private readonly TillCountService $service,
        private readonly IdentityVerifier $verifier,
        private readonly CountIdentity $identity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $tills = $this->service->tills();

        $date = $this->date($request);
        $statuses = $this->service->tillStatuses($date);

        $till = $this->till($request, $tills, $statuses);

        $sessions = $till ? $this->service->sessionsOn($till, $date) : collect();
        $session = null;
        if ($till && $sessions->isNotEmpty()) {
            $wanted = $request->query('session');
            $session = $sessions->firstWhere('MONEY', $wanted) ?? $sessions->last();
        }

        $state = ($till && $session) ? $this->service->stateFor($till, $session) : null;
        $open = $till && ! $session && $this->service->openSession($till) !== null;

        $counter = $this->identity->current();

        return [
            'tills' => $tills,
            'till' => $till,
            'date' => $date,
            'sessions' => $sessions,
            'session' => $session,
            'state' => $state,
            'statuses' => $statuses,
            'open' => $open,
            'adjacent' => $till ? $this->service->adjacentDates($till, $date) : ['prev' => null, 'next' => null],
            'people' => $this->verifier->people($request),
            'identity' => $counter ? [
                'id' => $counter->id,
                'name' => $counter->name,
                'first_name' => IdentityVerifier::firstName($counter),
                'expires_at' => $this->identity->expiresAt()?->toIso8601String(),
            ] : null,
            'suppliers' => $this->service->suppliers()
                ->map(fn ($name, $id) => ['id' => (string) $id, 'name' => $name])
                ->values()
                ->all(),
        ];
    }

    /**
     * What the Alpine component reads from data-state.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public function clientState(array $page): array
    {
        return [
            'state' => $page['state'],
            'people' => $page['people'],
            'identity' => $page['identity'],
            'suppliers' => $page['suppliers'],
            'till_id' => $page['till']?->id,
        ];
    }

    private function date(Request $request): Carbon
    {
        try {
            return $request->filled('date')
                ? Carbon::parse($request->query('date'))->startOfDay()
                : Carbon::today();
        } catch (\Throwable $e) {
            return Carbon::today();
        }
    }

    /**
     * The requested till (`till` or the older `till_id`), else the first till with
     * a close on the date that nobody has counted, else the first till.
     *
     * @param  Collection<int, Till>  $tills
     * @param  array<int, array<string, mixed>>  $statuses
     */
    private function till(Request $request, Collection $tills, array $statuses): ?Till
    {
        $wanted = $request->query('till', $request->query('till_id'));
        if ($wanted !== null && ($till = $tills->firstWhere('id', (int) $wanted))) {
            return $till;
        }

        foreach ($statuses as $status) {
            if ($status['session_id'] && ! $status['counted']) {
                return $tills->firstWhere('id', $status['id']);
            }
        }

        return $tills->first();
    }
}
