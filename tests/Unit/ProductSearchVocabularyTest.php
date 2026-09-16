<?php

namespace Tests\Unit;

use App\Services\ProductSearch\ProductSearchVocabulary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductSearchVocabularyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('pos')->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->nullable();
        });

        DB::connection('pos')->table('PRODUCTS')->insert([
            ['ID' => 'p1', 'NAME' => 'Chocolatemakers forest fruit milk chocolate 100 gram', 'CODE' => '8721325594341'],
            ['ID' => 'p2', 'NAME' => 'Apple Juice 1L', 'CODE' => '1000001'],
        ]);

        Cache::forget(ProductSearchVocabulary::CACHE_KEY);
    }

    protected function tearDown(): void
    {
        Schema::connection('pos')->dropIfExists('PRODUCTS');

        parent::tearDown();
    }

    public function test_builds_vocabulary_of_words_three_chars_or_longer(): void
    {
        $words = (new ProductSearchVocabulary)->words();

        $this->assertArrayHasKey('chocolatemakers', $words);
        $this->assertArrayHasKey('fruit', $words);
        $this->assertArrayHasKey('100', $words);
        $this->assertArrayNotHasKey('1l', $words);
        $this->assertSame(1, $words['chocolate']);
    }

    public function test_corrects_typos_against_vocabulary(): void
    {
        $vocab = new ProductSearchVocabulary;

        $this->assertSame('chocolatemakers', $vocab->correct('chocolatmakers'));
        $this->assertSame('fruit', $vocab->correct('friut'));
        $this->assertNull($vocab->correct('choc'), 'substring of a real word is a partial, not a typo');
        $this->assertNull($vocab->correct('8721325594341'), 'digits are never corrected');
        $this->assertNull($vocab->correct('xyzzyqq'), 'nothing close enough');
        $this->assertNull($vocab->correct('app'), 'too short to correct');
    }

    public function test_forget_clears_the_cache(): void
    {
        $vocab = new ProductSearchVocabulary;
        $vocab->words();
        $this->assertTrue(Cache::has(ProductSearchVocabulary::CACHE_KEY));

        $vocab->forget();
        $this->assertFalse(Cache::has(ProductSearchVocabulary::CACHE_KEY));
    }
}
