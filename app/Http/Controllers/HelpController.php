<?php

namespace App\Http\Controllers;

use App\Services\BookStack\BookStackClient;
use App\Services\BookStack\ScreenHelp;
use App\Support\UiMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Procedures (SOPs) written in BookStack, read and shown inside our own
 * layouts: Shop mode on the tablets and phones, the office layout on PCs.
 * Only pages tagged for a screen are served. See docs/features/sops-bookstack.md.
 */
class HelpController extends Controller
{
    /** Image types the proxy passes on. SVG is refused: opened directly it would run script in our origin. */
    private const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public function __construct(private ScreenHelp $help) {}

    public function show(Request $request, string $screen): View
    {
        abort_unless($this->help->isConfigured(), 404);

        $pages = $this->help->pagesFor($screen);

        if (count($pages) === 1) {
            return $this->renderPage($request, $pages[0]['id'], $screen);
        }

        return $this->reader($request, [
            'state' => $pages === [] ? 'empty' : 'list',
            'screen' => $screen,
            'pages' => $pages,
        ]);
    }

    public function page(Request $request, int $id): View
    {
        abort_unless($this->help->isConfigured() && $this->help->knows($id), 404);

        return $this->renderPage($request, $id);
    }

    public function image(BookStackClient $client, string $path): Response
    {
        abort_unless($client->isConfigured(), 404);
        abort_if(in_array('..', explode('/', $path), true), 404);

        try {
            $image = $client->image($path);
        } catch (Throwable $e) {
            Log::warning('BookStack image could not be fetched: '.$e->getMessage(), ['exception' => get_class($e)]);
            $image = null;
        }

        $type = $image ? strtolower(trim(explode(';', $image['type'])[0])) : null;
        abort_unless($image && in_array($type, self::IMAGE_TYPES, true), 404);

        return response($image['body'], 200, [
            'Content-Type' => $type,
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function refresh(): RedirectResponse
    {
        $this->help->refresh();

        return back()->with('success', 'Procedures refreshed.');
    }

    private function renderPage(Request $request, int $id, ?string $screen = null): View
    {
        try {
            $page = $this->help->page($id);
        } catch (Throwable $e) {
            Log::warning('BookStack page could not be loaded: '.$e->getMessage(), [
                'exception' => get_class($e),
                'page_id' => $id,
            ]);

            return $this->reader($request, ['state' => 'unavailable', 'screen' => $screen]);
        }

        abort_if($page === null, 404);

        return $this->reader($request, ['state' => 'page', 'screen' => $screen, 'page' => $page]);
    }

    private function reader(Request $request, array $data): View
    {
        $user = $request->user();
        $mode = app(UiMode::class);

        return view($mode->isShop() ? 'shop.help' : 'help.show', $data + [
            'screen' => null,
            'page' => null,
            'pages' => [],
            'back' => $this->backUrl($request, $mode),
            'bookstackUrl' => rtrim((string) config('services.bookstack.url'), '/'),
            'canManage' => $user->isManager() || $user->isAdmin(),
        ]);
    }

    /**
     * The `back` query parameter when it is a local path, else the mode's home.
     */
    private function backUrl(Request $request, UiMode $mode): string
    {
        $back = (string) $request->query('back', '');

        // "//host" and "/\host" are protocol-relative to a browser: not local.
        if (str_starts_with($back, '/') && ! in_array(substr($back, 1, 1), ['/', '\\'], true)) {
            return $back;
        }

        return route($mode->homeRoute());
    }
}
