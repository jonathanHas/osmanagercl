<?php

namespace App\Http\Controllers;

use App\Services\VoucherAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin changeover tools on the voucher list (vouchers cycle 4): bulk make for
 * sale, deactivate, reactivate, delete and restore. Admin only (routes sit in
 * `role:admin`), and off entirely when `vouchers.admin_tools` is false.
 * Removable with VoucherAdminService, the list partial and the five routes.
 */
class VoucherAdminToolsController extends Controller
{
    public function __construct(
        protected VoucherAdminService $admin,
    ) {}

    public function deactivate(Request $request): RedirectResponse
    {
        return $this->run($request, 'deactivate', 'Deactivated', ['nullable', 'string', 'max:500']);
    }

    public function reactivate(Request $request): RedirectResponse
    {
        return $this->run($request, 'reactivate', 'Reactivated', ['nullable', 'string', 'max:500']);
    }

    public function delete(Request $request): RedirectResponse
    {
        return $this->run($request, 'delete', 'Deleted', ['required', 'string', 'min:3', 'max:500']);
    }

    public function restore(Request $request): RedirectResponse
    {
        return $this->run($request, 'restore', 'Restored', ['nullable', 'string', 'max:500']);
    }

    public function forSale(Request $request): RedirectResponse
    {
        return $this->run($request, 'makeForSale', 'Made for sale:', ['nullable', 'string', 'max:500']);
    }

    /**
     * @param  array<int, string>  $noteRules
     */
    private function run(Request $request, string $method, string $verb, array $noteRules): RedirectResponse
    {
        abort_unless(config('vouchers.admin_tools'), 404);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'note' => $noteRules,
        ]);

        $result = $this->admin->{$method}($data['ids'], $data['note'] ?? null, $request->user());

        return back()->with('status', $this->summary($verb, $result));
    }

    /**
     * e.g. "Deactivated 36 vouchers. Skipped 1: GVXXXXXXXXXX (not active)."
     *
     * @param  array{done: int, skipped: array<int, array{code: string, reason: string}>}  $result
     */
    private function summary(string $verb, array $result): string
    {
        $done = $result['done'];
        $text = "{$verb} {$done} ".($done === 1 ? 'voucher' : 'vouchers').'.';

        $skipped = $result['skipped'];
        if ($skipped) {
            $shown = array_map(fn ($s) => "{$s['code']} ({$s['reason']})", array_slice($skipped, 0, 10));
            $text .= ' Skipped '.count($skipped).': '.implode(', ', $shown);
            if (count($skipped) > 10) {
                $text .= ' and '.(count($skipped) - 10).' more';
            }
            $text .= '.';
        }

        return $text;
    }
}
