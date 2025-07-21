<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UdeaScrapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TestScraperControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Mock the UdeaScrapingService
        $this->mockService = Mockery::mock(UdeaScrapingService::class);
        $this->app->instance(UdeaScrapingService::class, $this->mockService);
    }

    public function test_guzzle_test_page_displays_with_data()
    {
        $mockData = [
            'product_code' => '5014415',
            'price' => '€25.99',
            'units_per_case' => '12',
            'description' => 'Test Product',
            'scraped_at' => now()->toISOString(),
        ];

        $this->mockService
            ->shouldReceive('getProductData')
            ->once()
            ->with('5014415')
            ->andReturn($mockData);

        $response = $this->actingAs($this->user)
            ->get(route('tests.guzzle'));

        $response->assertStatus(200);
        $response->assertViewIs('tests.guzzle');
        $response->assertViewHas('product_code', '5014415');
        $response->assertViewHas('data', $mockData);
        $response->assertViewHas('success', true);
        $response->assertSee('€25.99');
        $response->assertSee('12');
    }

    public function test_guzzle_test_page_displays_without_data()
    {
        $this->mockService
            ->shouldReceive('getProductData')
            ->once()
            ->with('5014415')
            ->andReturn(null);

        $response = $this->actingAs($this->user)
            ->get(route('tests.guzzle'));

        $response->assertStatus(200);
        $response->assertViewIs('tests.guzzle');
        $response->assertViewHas('success', false);
        $response->assertSee('Failed to retrieve data');
    }

    public function test_client_test_page_displays()
    {
        $response = $this->actingAs($this->user)
            ->get(route('tests.client'));

        $response->assertStatus(200);
        $response->assertViewIs('tests.client');
        $response->assertViewHas('product_code', '5014415');
        $response->assertSee('Client-side API Test');
    }

    public function test_dashboard_displays_connection_status()
    {
        $connectionStatus = [
            'success' => true,
            'status_code' => 200,
            'response_time' => 150.5,
            'authenticated' => true,
        ];

        $this->mockService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn($connectionStatus);

        $response = $this->actingAs($this->user)
            ->get(route('tests.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('tests.dashboard');
        $response->assertViewHas('connection_status', $connectionStatus);
        $response->assertSee('Connected');
        $response->assertSee('200');
        $response->assertSee('150.5ms');
    }

    public function test_proxy_product_data_api_returns_success()
    {
        $mockData = [
            'product_code' => '1234567',
            'price' => '€30.00',
            'units_per_case' => '6',
        ];

        $this->mockService
            ->shouldReceive('getProductData')
            ->once()
            ->with('1234567')
            ->andReturn($mockData);

        $response = $this->actingAs($this->user)
            ->postJson('/api/test-scraper/product-data', [
                'product_code' => '1234567',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => $mockData,
        ]);
    }

    public function test_proxy_product_data_api_returns_error()
    {
        $this->mockService
            ->shouldReceive('getProductData')
            ->once()
            ->with('invalid123')
            ->andReturn(null);

        $response = $this->actingAs($this->user)
            ->postJson('/api/test-scraper/product-data', [
                'product_code' => 'invalid123',
            ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'error' => 'Product data could not be retrieved',
        ]);
    }

    public function test_proxy_product_data_api_validates_input()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/test-scraper/product-data', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_code']);
    }

    public function test_proxy_product_data_api_validates_product_code_length()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/test-scraper/product-data', [
                'product_code' => str_repeat('a', 51), // Too long
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_code']);
    }

    public function test_test_connection_api_returns_success()
    {
        $connectionStatus = [
            'success' => true,
            'status_code' => 200,
            'response_time' => 100.0,
            'authenticated' => true,
        ];

        $this->mockService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn($connectionStatus);

        $response = $this->actingAs($this->user)
            ->get('/api/test-scraper/connection-test');

        $response->assertStatus(200);
        $response->assertJson($connectionStatus);
    }

    public function test_test_connection_api_returns_error()
    {
        $connectionStatus = [
            'success' => false,
            'error' => 'Connection failed',
            'authenticated' => false,
        ];

        $this->mockService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn($connectionStatus);

        $response = $this->actingAs($this->user)
            ->get('/api/test-scraper/connection-test');

        $response->assertStatus(500);
        $response->assertJson($connectionStatus);
    }

    public function test_clear_cache_api_with_specific_product()
    {
        $this->mockService
            ->shouldReceive('clearCache')
            ->once()
            ->with('5014415');

        $response = $this->actingAs($this->user)
            ->postJson('/api/test-scraper/clear-cache', [
                'product_code' => '5014415',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Cache cleared for product: 5014415',
        ]);
    }

    public function test_clear_cache_api_clears_all_cache()
    {
        $this->mockService
            ->shouldReceive('clearCache')
            ->once()
            ->with(null);

        $response = $this->actingAs($this->user)
            ->postJson('/api/test-scraper/clear-cache', []);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'All cache cleared',
        ]);
    }

    public function test_routes_require_authentication()
    {
        $routes = [
            ['GET', route('tests.guzzle')],
            ['GET', route('tests.client')],
            ['GET', route('tests.dashboard')],
            ['POST', '/api/test-scraper/product-data'],
            ['GET', '/api/test-scraper/connection-test'],
            ['POST', '/api/test-scraper/clear-cache'],
        ];

        foreach ($routes as [$method, $route]) {
            $response = $this->call($method, $route);
            $this->assertContains($response->getStatusCode(), [302, 401],
                "Route {$method} {$route} should require authentication");
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
