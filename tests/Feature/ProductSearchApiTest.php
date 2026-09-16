<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductSearch\ProductSearchVocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesProductSearchPosTables;
use Tests\TestCase;

class ProductSearchApiTest extends TestCase
{
    use CreatesProductSearchPosTables;
    use RefreshDatabase;

    /** @var array{P1: string, P2: string, P3: string, P4: string, P5: string} */
    protected array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createProductSearchPosTables();
        $this->ids = $this->seedProductSearchFixture();
        Cache::forget(ProductSearchVocabulary::CACHE_KEY);
    }

    protected function tearDown(): void
    {
        $this->dropProductSearchPosTables();

        parent::tearDown();
    }

    protected function search(array $params): TestResponse
    {
        return $this->actingAs(User::factory()->create())
            ->getJson(route('api.products.search', $params));
    }

    /** @return string[] */
    protected function idsOf(TestResponse $response): array
    {
        return array_column($response->json('data'), 'id');
    }

    public function test_words_match_in_any_order(): void
    {
        $response = $this->search(['q' => 'chocolatemakers fruit'])->assertOk();

        $this->assertSame([$this->ids['P1']], $this->idsOf($response));
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_ranking_prefers_exact_code_then_prefix(): void
    {
        $response = $this->search(['q' => '8721325594341'])->assertOk();
        $this->assertSame($this->ids['P1'], $response->json('data.0.id'));
        $this->assertSame(0, $response->json('data.0.match_rank'));

        $response = $this->search(['q' => 'milk', 'stocked' => 0])->assertOk();
        $this->assertSame([$this->ids['P3'], $this->ids['P1']], $this->idsOf($response));
        $this->assertSame(1, $response->json('data.0.match_rank'));
        $this->assertSame(2, $response->json('data.1.match_rank'));
    }

    public function test_stocked_is_default_and_toggle_includes_unstocked(): void
    {
        $response = $this->search(['q' => 'milk'])->assertOk();
        $this->assertSame([$this->ids['P1']], $this->idsOf($response));
        $this->assertTrue($response->json('meta.stocked'));
        $this->assertTrue($response->json('data.0.is_stocked'));

        $response = $this->search(['q' => 'milk', 'stocked' => 0])->assertOk();
        $this->assertEqualsCanonicalizing([$this->ids['P1'], $this->ids['P3']], $this->idsOf($response));
        $this->assertFalse($response->json('meta.stocked'));

        $unstocked = collect($response->json('data'))->firstWhere('id', $this->ids['P3']);
        $this->assertFalse($unstocked['is_stocked']);
    }

    public function test_supplier_code_matches(): void
    {
        $response = $this->search(['q' => '6001397'])->assertOk();
        $this->assertSame([$this->ids['P1']], $this->idsOf($response));

        $response = $this->search(['q' => 'ind-a'])->assertOk();
        $this->assertSame([$this->ids['P4']], $this->idsOf($response));
    }

    public function test_typo_correction_reports_corrected_query(): void
    {
        $response = $this->search(['q' => 'chocolatmakers friut'])->assertOk();

        $this->assertSame([$this->ids['P1']], $this->idsOf($response));
        $this->assertSame('chocolatemakers fruit', $response->json('meta.corrected_query'));
        $this->assertSame('chocolatmakers friut', $response->json('meta.query'));
    }

    public function test_no_correction_when_results_exist(): void
    {
        $response = $this->search(['q' => 'choc'])->assertOk();

        $this->assertNull($response->json('meta.corrected_query'));
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_image_url_prefers_blob_then_udea_cdn(): void
    {
        $response = $this->search(['q' => '', 'stocked' => 0])->assertOk();
        $byId = collect($response->json('data'))->keyBy('id');

        $this->assertStringEndsWith('/products/'.$this->ids['P4'].'/image', $byId[$this->ids['P4']]['image_url']);
        $this->assertTrue($byId[$this->ids['P4']]['has_image']);

        $this->assertStringContainsString('8721325594341.jpg', $byId[$this->ids['P1']]['image_url']);
        $this->assertFalse($byId[$this->ids['P1']]['has_image']);

        $this->assertNull($byId[$this->ids['P3']]['image_url']);
    }

    public function test_response_carries_supplier_price_and_stock_details(): void
    {
        $response = $this->search(['q' => 'chocolatemakers fruit'])->assertOk();
        $item = $response->json('data.0');

        $this->assertSame('8721325594341', $item['code']);
        $this->assertSame('Chocolate', $item['category_name']);
        $this->assertSame(['id' => '5', 'name' => 'Udea', 'code' => '6001397', 'website_url' => 'https://www.udea.nl/search/?qry=6001397'], $item['supplier']);
        $this->assertSame(5.08, $item['price_sell']);
        $this->assertSame(6.25, $item['price_with_vat']);
        $this->assertSame('23.0%', $item['vat_label']);
        $this->assertEquals(12.0, $item['stock_units']);
        $this->assertTrue($item['has_stock_record']);
        $this->assertFalse($item['is_service']);
        $this->assertSame('/products/'.$this->ids['P1'].'/edit', $item['edit_url']);

        $unlinked = collect($this->search(['q' => 'milk', 'stocked' => 0])->json('data'))->firstWhere('id', $this->ids['P3']);
        $this->assertNull($unlinked['supplier']);
        $this->assertFalse($unlinked['has_stock_record']);
        $this->assertEquals(0.0, $unlinked['stock_units']);

        // Natural Medicine has no website_search template: supplier is present, link is null.
        $noWebsite = collect($this->search(['q' => 'delisted', 'stocked' => 0])->json('data'))->firstWhere('id', $this->ids['P5']);
        $this->assertSame('Natural Medicine', $noWebsite['supplier']['name']);
        $this->assertSame('NM-1', $noWebsite['supplier']['code']);
        $this->assertNull($noWebsite['supplier']['website_url']);
    }

    public function test_exclude_and_supplier_filter(): void
    {
        $response = $this->search(['exclude' => $this->ids['P1'], 'q' => 'chocolatemakers'])->assertOk();
        $this->assertSame([$this->ids['P2']], $this->idsOf($response));

        $response = $this->search(['supplier_id' => '37', 'q' => '', 'stocked' => 1])->assertOk();
        $this->assertSame([$this->ids['P4']], $this->idsOf($response));

        $response = $this->search(['category_id' => 'cat-drinks', 'q' => '', 'stocked' => 0])->assertOk();
        $this->assertEqualsCanonicalizing([$this->ids['P4'], $this->ids['P5']], $this->idsOf($response));
    }

    public function test_pagination_meta_is_accurate(): void
    {
        $response = $this->search(['q' => '', 'stocked' => 0, 'per_page' => 2])->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(5, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));

        $response = $this->search(['q' => '', 'stocked' => 0, 'per_page' => 2, 'page' => 3])->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(3, $response->json('meta.page'));
    }

    public function test_per_page_is_capped_at_50_and_requires_auth(): void
    {
        $this->getJson(route('api.products.search', ['q' => 'milk']))->assertStatus(401);
        $this->get(route('api.products.search', ['q' => 'milk']))->assertRedirect('/login');

        $this->search(['q' => '', 'per_page' => 51])->assertStatus(422);
        $this->search(['q' => '', 'per_page' => 50])->assertOk()->assertJsonPath('meta.per_page', 50);
        $this->search(['q' => str_repeat('x', 101)])->assertStatus(422);
    }
}
