<?php

namespace Tests\Unit;

use App\Services\UdeaScrapingService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class UdeaScrapingServiceTest extends TestCase
{
    private UdeaScrapingService $service;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock configuration
        Config::set('services.udea', [
            'base_uri' => 'https://test.udea.nl',
            'username' => 'test_user',
            'password' => 'test_pass',
            'timeout' => 30,
            'rate_limit_delay' => 0, // No delay in tests
            'cache_ttl' => 3600,
        ]);

        // Create mock handler
        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);

        // Create service with mocked client
        $this->service = new UdeaScrapingService;

        // Use reflection to replace the client with our mocked version
        $reflection = new \ReflectionClass($this->service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->service, new Client([
            'handler' => $handlerStack,
            'base_uri' => 'https://test.udea.nl',
        ]));
    }

    public function test_get_product_data_returns_cached_result()
    {
        $productCode = '5014415';
        $cachedData = [
            'product_code' => $productCode,
            'price' => '€25.99',
            'units_per_case' => '12',
        ];

        Cache::shouldReceive('remember')
            ->once()
            ->with("udea_product_{$productCode}", 3600, \Closure::class)
            ->andReturn($cachedData);

        $result = $this->service->getProductData($productCode);

        $this->assertEquals($cachedData, $result);
    }

    public function test_successful_product_scraping()
    {
        $productCode = '5014415';
        $loginPageHtml = '<input name="_token" value="csrf123">';
        $productPageHtml = '
            <span class="product-price">€25.99</span>
            <span class="units-per-case">12 units</span>
            <span class="product-description">Test Product</span>
        ';

        // Mock login page request
        $this->mockHandler->append(new Response(200, [], $loginPageHtml));

        // Mock login form submission (redirect indicates success)
        $this->mockHandler->append(new Response(302, ['Location' => '/dashboard']));

        // Mock product search request
        $this->mockHandler->append(new Response(200, [], $productPageHtml));

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        Log::shouldReceive('info')->twice();

        $result = $this->service->getProductData($productCode);

        $this->assertIsArray($result);
        $this->assertEquals($productCode, $result['product_code']);
        $this->assertEquals('€25.99', $result['price']);
        $this->assertEquals('12 units', $result['units_per_case']);
        $this->assertEquals('Test Product', $result['description']);
        $this->assertArrayHasKey('scraped_at', $result);
    }

    public function test_failed_authentication_returns_null()
    {
        $productCode = '5014415';
        $loginPageHtml = '<input name="_token" value="csrf123">';

        // Mock login page request
        $this->mockHandler->append(new Response(200, [], $loginPageHtml));

        // Mock failed login (no redirect)
        $this->mockHandler->append(new Response(200, [], 'Login failed'));

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        Log::shouldReceive('warning')->twice();

        $result = $this->service->getProductData($productCode);

        $this->assertNull($result);
    }

    public function test_network_error_returns_null()
    {
        $productCode = '5014415';

        // Mock network error
        $this->mockHandler->append(new ConnectException(
            'Connection timeout',
            new Request('GET', '/login')
        ));

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        Log::shouldReceive('error')->twice();

        $result = $this->service->getProductData($productCode);

        $this->assertNull($result);
    }

    public function test_no_product_data_found_returns_null()
    {
        $productCode = '5014415';
        $loginPageHtml = '<input name="_token" value="csrf123">';
        $emptyProductPage = '<div>No product found</div>';

        // Mock successful login flow
        $this->mockHandler->append(new Response(200, [], $loginPageHtml));
        $this->mockHandler->append(new Response(302, ['Location' => '/dashboard']));
        $this->mockHandler->append(new Response(200, [], $emptyProductPage));

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        Log::shouldReceive('info')->once(); // Authentication success
        Log::shouldReceive('warning')->once(); // No data found

        $result = $this->service->getProductData($productCode);

        $this->assertNull($result);
    }

    public function test_test_connection_success()
    {
        // Mock successful connection
        $this->mockHandler->append(new Response(200, [], 'OK'));
        $this->mockHandler->append(new Response(200, [], 'OK')); // For response time measurement

        // Mock successful authentication
        $loginPageHtml = '<input name="_token" value="csrf123">';
        $this->mockHandler->append(new Response(200, [], $loginPageHtml));
        $this->mockHandler->append(new Response(302, ['Location' => '/dashboard']));

        Log::shouldReceive('info')->once();

        $result = $this->service->testConnection();

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status_code']);
        $this->assertArrayHasKey('response_time', $result);
        $this->assertTrue($result['authenticated']);
    }

    public function test_test_connection_failure()
    {
        // Mock connection error
        $this->mockHandler->append(new ConnectException(
            'Connection refused',
            new Request('GET', '/')
        ));

        $result = $this->service->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Connection refused', $result['error']);
        $this->assertFalse($result['authenticated']);
    }

    public function test_clear_cache_specific_product()
    {
        $productCode = '5014415';

        Cache::shouldReceive('forget')
            ->once()
            ->with("udea_product_{$productCode}");

        $this->service->clearCache($productCode);
    }

    public function test_clear_cache_all()
    {
        Cache::shouldReceive('flush')->once();

        $this->service->clearCache();
    }

    public function test_csrf_token_extraction()
    {
        $html = '<input name="_token" value="csrf123">';
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractCsrfToken');
        $method->setAccessible(true);

        $token = $method->invoke($this->service, $html);

        $this->assertEquals('csrf123', $token);
    }

    public function test_csrf_token_extraction_meta_tag()
    {
        $html = '<meta name="csrf-token" content="meta-csrf456">';
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractCsrfToken');
        $method->setAccessible(true);

        $token = $method->invoke($this->service, $html);

        $this->assertEquals('meta-csrf456', $token);
    }

    public function test_csrf_token_extraction_fails()
    {
        $html = '<div>No token here</div>';
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractCsrfToken');
        $method->setAccessible(true);

        $token = $method->invoke($this->service, $html);

        $this->assertNull($token);
    }

    public function test_parse_product_data()
    {
        $html = '
            <span class="product-price">€29.99</span>
            <span class="units-per-case">24 units</span>
            <span class="product-description">Premium Product</span>
            <span class="availability">In Stock</span>
        ';
        $productCode = '1234567';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('parseProductData');
        $method->setAccessible(true);

        Log::shouldReceive('info')->once();

        $result = $method->invoke($this->service, $html, $productCode);

        $this->assertIsArray($result);
        $this->assertEquals($productCode, $result['product_code']);
        $this->assertEquals('€29.99', $result['price']);
        $this->assertEquals('24 units', $result['units_per_case']);
        $this->assertEquals('Premium Product', $result['description']);
        $this->assertEquals('In Stock', $result['availability']);
        $this->assertArrayHasKey('scraped_at', $result);
    }
}
