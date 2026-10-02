<?php

namespace Tests\Feature\Help;

use App\Models\Permission;
use App\Models\Role;
use App\Models\ShopDevice;
use App\Models\User;
use App\Services\BookStack\ScreenHelp;
use App\Services\BookStack\SopHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Procedures (SOPs) read from BookStack: the screen index, the help buttons,
 * the reader in both layouts, the image proxy, and failure behaviour.
 * Every BookStack response here is faked; the suite never touches the network.
 */
class HelpTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'http://bookstack.test';

    private const DEVICE_TOKEN = 'device-token-for-tests-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Cache::flush();
    }

    private function configure(): void
    {
        config([
            'services.bookstack.url' => self::BASE,
            'services.bookstack.token_id' => 'id',
            'services.bookstack.token_secret' => 'secret',
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $roleName, array $permissions = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name, 'module' => 'Shop']
            ));
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => 'Maya Jensen']);
    }

    /**
     * A real PIN sign-in, as in ConfinePinSessionTest.
     */
    private function pinUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        $user = User::factory()->withPin()->create(['role_id' => $role->id, 'name' => 'Tom Byrne']);

        ShopDevice::factory()->token(self::DEVICE_TOKEN)->create();
        $this->withCookies([config('shop.device_cookie') => self::DEVICE_TOKEN]);

        $this->post(route('shop.switch.authenticate', $user), ['pin' => '2580'])
            ->assertRedirect(route('shop.home'));

        return $user;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: string}>  $pages  [id, title, screen]
     */
    private function searchHits(array $pages, bool $withTags = true): array
    {
        return array_map(function ($p) use ($withTags) {
            $hit = [
                'id' => $p[0],
                'name' => $p[1],
                'type' => 'page',
                'url' => self::BASE.'/books/shop/page/'.$p[0],
            ];
            if ($withTags) {
                $hit['tags'] = [['name' => 'screen', 'value' => $p[2], 'order' => 0]];
            }

            return $hit;
        }, $pages);
    }

    private function pageBody(int $id, string $title, string $screen, string $html = '<p>Step one.</p>'): array
    {
        return [
            'id' => $id,
            'name' => $title,
            'html' => $html,
            'updated_at' => '2026-09-30T10:00:00.000000Z',
            'tags' => [['name' => 'screen', 'value' => $screen, 'order' => 0]],
        ];
    }

    /**
     * Fake a BookStack holding the given tagged pages.
     *
     * @param  array<int, array{0: int, 1: string, 2: string}>  $pages  [id, title, screen]
     * @param  array<int, string>  $html  page id => html
     */
    private function fakeBookStack(array $pages, array $html = [], bool $searchCarriesTags = true): void
    {
        $bodies = [];
        foreach ($pages as $p) {
            $bodies[$p[0]] = $this->pageBody($p[0], $p[1], $p[2], $html[$p[0]] ?? '<p>Step one.</p>');
        }

        Http::fake([
            self::BASE.'/api/search*' => Http::response([
                'data' => $this->searchHits($pages, $searchCarriesTags),
                'total' => count($pages),
            ]),
            self::BASE.'/api/pages/*' => function (Request $request) use ($bodies) {
                $id = (int) basename(parse_url($request->url(), PHP_URL_PATH));

                return isset($bodies[$id]) ? Http::response($bodies[$id]) : Http::response(['error' => ['code' => 404]], 404);
            },
        ]);
    }

    private function searchCount(): int
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), '/api/search'))->count();
    }

    private function pageRequestsFor(int $id): int
    {
        return Http::recorded(fn (Request $r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/api/pages/'.$id))->count();
    }

    // 1
    public function test_unconfigured_feature_sends_nothing_and_shows_no_button(): void
    {
        Http::fake();
        $this->actingAs($this->userWith('employee'));

        $this->get(route('shop.home'))->assertOk()->assertDontSee('/help/', false);
        $this->get(route('help.show', 'shop.home'))->assertNotFound();

        Http::assertNothingSent();
    }

    // 2
    public function test_the_index_is_built_from_tagged_search_results(): void
    {
        $this->configure();
        $this->fakeBookStack([[12, 'Receiving a delivery', 'shop.deliveries.scan'], [13, 'Opening up', 'shop.home']]);

        $index = app(ScreenHelp::class)->index();

        $this->assertSame([12 => true, 13 => true], $index['ids']);
        $this->assertSame('Receiving a delivery', $index['screens']['shop.deliveries.scan'][0]['title']);
        $this->assertSame(13, $index['screens']['shop.home'][0]['id']);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/search')
            && $r->hasHeader('Authorization', 'Token id:secret')
            && str_contains($r['query'], '[screen]'));
    }

    // 3
    public function test_search_results_without_tags_fall_back_to_reading_each_page(): void
    {
        $this->configure();
        $this->fakeBookStack([[12, 'Receiving a delivery', 'shop.deliveries.scan'], [13, 'Opening up', 'shop.home']], [], false);

        $index = app(ScreenHelp::class)->index();

        $this->assertSame([12 => true, 13 => true], $index['ids']);
        $this->assertSame(12, $index['screens']['shop.deliveries.scan'][0]['id']);
        $this->assertSame(1, $this->pageRequestsFor(12));
        $this->assertSame(1, $this->pageRequestsFor(13));
    }

    // 4
    public function test_an_employee_sees_the_button_on_a_screen_with_a_procedure(): void
    {
        $this->configure();
        $this->fakeBookStack([[13, 'Opening up', 'shop.home']]);
        $this->actingAs($this->userWith('employee'));

        $this->get(route('shop.home'))->assertOk()
            ->assertSee('aria-label="How to do this"', false)
            ->assertSee('/help/shop.home', false);
    }

    // 4
    public function test_an_employee_sees_no_button_on_a_screen_without_a_procedure(): void
    {
        $this->configure();
        $this->fakeBookStack([[12, 'Receiving a delivery', 'shop.deliveries.scan']]);
        $this->actingAs($this->userWith('employee'));

        $this->get(route('shop.home'))->assertOk()
            ->assertDontSee('aria-label="How to do this"', false)
            ->assertDontSee('/help/', false);
    }

    // 5
    public function test_a_manager_sees_the_button_everywhere_and_the_tag_to_add(): void
    {
        $this->configure();
        $this->fakeBookStack([[12, 'Receiving a delivery', 'shop.deliveries.scan']]);

        $this->actingAs($this->userWith('manager'))
            ->withCookie('ui_mode', 'shop')
            ->get(route('shop.home'))->assertOk()
            ->assertSee('aria-label="How to do this"', false);

        $this->get(route('help.show', 'shop.home'))->assertOk()
            ->assertSee('There is no written procedure for this screen yet.')
            ->assertSee('<span class="shop-code">screen</span>', false)
            ->assertSee('<span class="shop-code">shop.home</span>', false)
            ->assertSee(route('help.refresh'), false);

        $this->actingAs($this->userWith('employee'))
            ->get(route('help.show', 'shop.home'))->assertOk()
            ->assertSee('There is no written procedure for this screen yet.')
            ->assertDontSee('shop.home</span>', false)
            ->assertDontSee(route('help.refresh'), false);
    }

    // 6
    public function test_a_single_tagged_page_renders_its_sanitised_html(): void
    {
        $this->configure();
        $this->fakeBookStack([[13, 'Opening up', 'shop.home']], [
            13 => '<p class="callout info">Unlock the door first.</p><p onclick="steal()">Then the till.</p><script>alert(1)</script>',
        ]);
        $this->actingAs($this->userWith('employee'));

        $this->get(route('help.show', 'shop.home'))->assertOk()
            ->assertSee('Opening up')
            ->assertSee('class="callout info"', false)
            ->assertSee('Then the till.')
            ->assertDontSee('<script>alert', false)
            ->assertDontSee('onclick', false)
            ->assertSee('Updated 30 Sep 2026');
    }

    // 7
    public function test_images_and_links_are_rewritten_and_long_pages_are_not_truncated(): void
    {
        config([
            'services.bookstack.url' => 'http://bookstack.internal',
            'services.bookstack.token_id' => 'id',
            'services.bookstack.token_secret' => 'secret',
        ]);

        $long = str_repeat('x', 30_000);
        $html = app(SopHtml::class)->clean(
            '<a href="http://bookstack.internal/uploads/images/gallery/2026-09/a.png"><img src="http://bookstack.internal/uploads/images/gallery/2026-09/a-scaled.png"></a>'
            .'<a href="/books/x/page/y">Other procedure</a>'
            .'<img src="/uploads/images/gallery/2026-09/b.png">'
            .'<a href="https://example.com/">Outside</a>'
            .'<p>'.$long.'END</p>'
        );

        $this->assertStringContainsString('href="/help/image/uploads/images/gallery/2026-09/a.png"', $html);
        $this->assertStringContainsString('src="/help/image/uploads/images/gallery/2026-09/a-scaled.png"', $html);
        $this->assertStringContainsString('src="/help/image/uploads/images/gallery/2026-09/b.png"', $html);
        $this->assertStringContainsString('href="http://bookstack.internal/books/x/page/y"', $html);
        $this->assertStringContainsString('href="https://example.com/"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString($long.'END', $html);
    }

    // 8
    public function test_a_page_that_is_not_in_the_index_is_not_found(): void
    {
        $this->configure();
        $this->fakeBookStack([[13, 'Opening up', 'shop.home']]);
        $this->actingAs($this->userWith('employee'));

        $this->get(route('help.page', 99))->assertNotFound();

        $this->assertSame(0, $this->pageRequestsFor(99));
    }

    // 9
    public function test_two_pages_for_one_screen_are_listed(): void
    {
        $this->configure();
        $this->fakeBookStack([[14, 'Closing the till', 'shop.home'], [13, 'Cashing up', 'shop.home']]);
        $this->actingAs($this->userWith('employee'));

        $this->get(route('help.show', 'shop.home'))->assertOk()
            ->assertSeeInOrder(['Cashing up', 'Closing the till'])
            ->assertSee('/help/page/13', false)
            ->assertSee('/help/page/14', false);

        $this->assertSame(0, $this->pageRequestsFor(13));
    }

    // 10
    public function test_bookstack_failing_never_breaks_a_page(): void
    {
        $this->configure();
        $this->actingAs($this->userWith('employee'));

        Http::fake([self::BASE.'/*' => Http::response('Server error', 500)]);
        $this->get(route('shop.home'))->assertOk()->assertDontSee('/help/', false);

        Http::fake([self::BASE.'/*' => fn () => throw new ConnectionException('Connection refused')]);
        app()->forgetScopedInstances();
        $this->get(route('shop.home'))->assertOk()->assertDontSee('/help/', false);
    }

    // 10
    public function test_a_failing_refetch_serves_the_last_good_index(): void
    {
        $this->configure();
        $this->fakeBookStack([[13, 'Opening up', 'shop.home']]);
        $this->assertTrue(app(ScreenHelp::class)->knows(13));

        Cache::forget(ScreenHelp::INDEX_KEY);
        app()->forgetScopedInstances();
        Http::fake([self::BASE.'/*' => fn () => throw new ConnectionException('Connection refused')]);

        $this->assertTrue(app(ScreenHelp::class)->knows(13));
        $this->assertSame('Opening up', app(ScreenHelp::class)->pagesFor('shop.home')[0]['title']);
    }

    // 11
    public function test_a_page_that_fails_to_load_says_so_and_is_not_cached(): void
    {
        $this->configure();
        $bodies = [13 => $this->pageBody(13, 'Opening up', 'shop.home', '<p>Unlock the door.</p>')];
        $pageFails = true;

        Http::fake([
            self::BASE.'/api/search*' => Http::response(['data' => $this->searchHits([[13, 'Opening up', 'shop.home']]), 'total' => 1]),
            self::BASE.'/api/pages/*' => function () use (&$pageFails, $bodies) {
                return $pageFails ? Http::response('Server error', 500) : Http::response($bodies[13]);
            },
        ]);
        $this->actingAs($this->userWith('employee'));

        $this->get(route('help.page', 13))->assertOk()
            ->assertSee('This procedure could not be loaded just now. Try again in a minute.');

        $pageFails = false;
        app()->forgetScopedInstances();

        $this->get(route('help.page', 13))->assertOk()
            ->assertSee('Unlock the door.')
            ->assertDontSee('could not be loaded');
    }

    // 12
    public function test_the_image_proxy_passes_images_only(): void
    {
        $this->configure();
        Http::fake([
            self::BASE.'/uploads/images/gallery/2026-09/a.png' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
            self::BASE.'/uploads/images/gallery/2026-09/x.svg' => Http::response('<svg onload="alert(1)"/>', 200, ['Content-Type' => 'image/svg+xml']),
        ]);
        $this->actingAs($this->userWith('employee'));

        $response = $this->get('/help/image/uploads/images/gallery/2026-09/a.png')->assertOk();
        $this->assertSame('PNGDATA', $response->getContent());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=604800', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/uploads/images/gallery/2026-09/a.png')
            && ! $r->hasHeader('Authorization'));

        $this->get('/help/image/uploads/images/gallery/2026-09/x.svg')->assertNotFound();
        $this->get('/help/image/uploads/images/../../.env')->assertNotFound();
        $this->get('/help/image/books/x')->assertNotFound();
    }

    // 13
    public function test_a_pin_session_reaches_the_reader(): void
    {
        $this->configure();
        $this->fakeBookStack([[13, 'Opening up', 'shop.home']]);
        $this->pinUser();

        $this->get(route('help.show', 'shop.home'))->assertOk()
            ->assertSee('data-shell="shop"', false)
            ->assertSee('Opening up');
    }

    // 14
    public function test_refresh_is_for_managers_and_refetches_the_index(): void
    {
        $this->configure();
        $this->fakeBookStack([[12, 'Receiving a delivery', 'shop.deliveries.scan']]);

        $this->actingAs($this->userWith('employee'))
            ->post(route('help.refresh'))->assertForbidden();

        $manager = $this->userWith('manager');
        $this->actingAs($manager)->get(route('help.show', 'dashboard'))->assertOk();
        $this->assertSame(1, $this->searchCount());

        $this->from(route('help.show', 'dashboard'))
            ->post(route('help.refresh'))
            ->assertRedirect(route('help.show', 'dashboard'))
            ->assertSessionHas('success', 'Procedures refreshed.');

        app()->forgetScopedInstances();
        $this->get(route('help.show', 'dashboard'))->assertOk()->assertSee('Procedures refreshed.');
        $this->assertSame(2, $this->searchCount());
    }

    // 15
    public function test_office_mode_uses_the_admin_layout(): void
    {
        $this->configure();
        $this->fakeBookStack([[20, 'Month end', 'dashboard']]);

        $this->actingAs($this->userWith('manager'))
            ->get(route('help.show', 'dashboard'))->assertOk()
            ->assertDontSee('data-shell="shop"', false)
            ->assertSee('data-shell="admin"', false)
            ->assertSee('sop-body', false)
            ->assertSee('Edit in BookStack');
    }

    // 16
    public function test_an_off_site_back_link_is_ignored(): void
    {
        $this->configure();
        $this->fakeBookStack([[13, 'Opening up', 'shop.home']]);
        $this->actingAs($this->userWith('employee'));

        foreach (['https://evil.test/', '//evil.test', '/\\evil.test'] as $back) {
            $this->get(route('help.show', ['screen' => 'shop.home', 'back' => $back]))->assertOk()
                // The layout's stale-session redirect echoes the whole request URI
                // into route('login', ['redirect' => …]); only the back link matters here.
                ->assertDontSee('href="'.e($back).'"', false)
                ->assertSee('href="'.route('shop.home').'" aria-label="Back"', false);
        }

        $this->get(route('help.show', ['screen' => 'shop.home', 'back' => '/shop/labels']))->assertOk()
            ->assertSee('href="/shop/labels" aria-label="Back"', false);
    }

    // 17
    public function test_guests_are_sent_to_login(): void
    {
        $this->configure();
        Http::fake();

        $this->get(route('help.show', 'shop.home'))->assertRedirect(route('login'));
        $this->get(route('help.page', 13))->assertRedirect(route('login'));
        $this->get('/help/image/uploads/images/gallery/2026-09/a.png')->assertRedirect(route('login'));

        Http::assertNothingSent();
    }
}
