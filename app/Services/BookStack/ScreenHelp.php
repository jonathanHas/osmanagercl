<?php

namespace App\Services\BookStack;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Which procedures (SOPs) belong to which screen.
 *
 * A BookStack page is linked to a screen by a tag named `screen` (config
 * `services.bookstack.screen_tag`) whose value is the route name. Only pages
 * in that index are ever served: BookStack may hold pages that are not for
 * shop-floor staff, and the API token may be able to see them.
 *
 * BookStack being down or unconfigured never breaks a page: the index falls
 * back to the last good copy, or to an empty index.
 */
class ScreenHelp
{
    public const INDEX_KEY = 'bookstack.screen-index';

    public const LAST_GOOD_KEY = 'bookstack.screen-index.last-good';

    private const INDEX_TTL = 300;

    private const PAGE_TTL = 600;

    private ?array $index = null;

    public function __construct(
        private BookStackClient $client,
        private SopHtml $html,
    ) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return array{screens: array<string, array<int, array{id: int, title: string, url: string}>>, ids: array<int, true>}
     */
    public function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        if (! $this->client->isConfigured()) {
            return $this->index = self::emptyIndex();
        }

        try {
            $index = Cache::remember(self::INDEX_KEY, self::INDEX_TTL, function () {
                $built = $this->build();
                // Written only when freshly built, so a warm cache costs one read.
                Cache::forever(self::LAST_GOOD_KEY, $built);

                return $built;
            });
        } catch (Throwable $e) {
            Log::warning('BookStack screen index could not be refreshed: '.$e->getMessage(), [
                'exception' => get_class($e),
            ]);
            $index = Cache::get(self::LAST_GOOD_KEY) ?? self::emptyIndex();
        }

        return $this->index = $index;
    }

    /**
     * @return array<int, array{id: int, title: string, url: string}>
     */
    public function pagesFor(string $screen): array
    {
        return $this->index()['screens'][$screen] ?? [];
    }

    public function knows(int $id): bool
    {
        return isset($this->index()['ids'][$id]);
    }

    /**
     * A tagged page with sanitised HTML, or null when it is not in the index.
     * Throws when the fetch fails and nothing is cached; a failure is never cached.
     *
     * @return array{id: int, title: string, html: string, updated_at: ?string, url: string}|null
     */
    public function page(int $id): ?array
    {
        if (! $this->knows($id)) {
            return null;
        }

        return Cache::remember("bookstack.page.$id", self::PAGE_TTL, function () use ($id) {
            $page = $this->client->page($id);
            $page['html'] = $this->html->clean($page['html']);
            $page['url'] = $this->urlFor($id);

            return $page;
        });
    }

    /**
     * Help for the current screen, for the help buttons.
     *
     * @return array{screen: string, pages: array}|null
     */
    public function current(): ?array
    {
        $user = auth()->user();
        $screen = request()->route()?->getName();

        if (! $this->client->isConfigured() || ! $user || ! $screen || str_starts_with($screen, 'help.')) {
            return null;
        }

        $pages = $this->pagesFor($screen);

        // Managers see the button everywhere so they can find the screen's tag value.
        if ($pages === [] && ! ($user->isManager() || $user->isAdmin())) {
            return null;
        }

        return ['screen' => $screen, 'pages' => $pages];
    }

    /**
     * Drop the cached index and pages so the next request refetches them.
     * The last-good index is kept as the fallback.
     */
    public function refresh(): void
    {
        $ids = array_keys(
            (Cache::get(self::INDEX_KEY)['ids'] ?? []) + (Cache::get(self::LAST_GOOD_KEY)['ids'] ?? [])
        );

        Cache::forget(self::INDEX_KEY);
        foreach ($ids as $id) {
            Cache::forget("bookstack.page.$id");
        }

        $this->index = null;
    }

    private function build(): array
    {
        $index = self::emptyIndex();

        foreach ($this->client->taggedPages() as $page) {
            $entry = ['id' => $page['id'], 'title' => $page['title'], 'url' => $page['url']];
            foreach ($page['screens'] as $screen) {
                $index['screens'][$screen][] = $entry;
            }
            $index['ids'][$page['id']] = true;
        }

        foreach ($index['screens'] as &$pages) {
            usort($pages, fn ($a, $b) => strcasecmp($a['title'], $b['title']));
        }
        unset($pages);

        return $index;
    }

    private function urlFor(int $id): string
    {
        foreach ($this->index()['screens'] as $pages) {
            foreach ($pages as $page) {
                if ($page['id'] === $id) {
                    return $page['url'];
                }
            }
        }

        return '';
    }

    private static function emptyIndex(): array
    {
        return ['screens' => [], 'ids' => []];
    }
}
