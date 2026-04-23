<?php

namespace Tests\Unit;

use App\Services\DynamisMatcherService;
use Tests\TestCase;

class DynamisMatcherServiceTest extends TestCase
{
    public function test_normalize_strips_stopwords_and_singularizes(): void
    {
        $matcher = new DynamisMatcherService;

        $tokens = $matcher->normalize('APPLE ARIANE BIO 14kg 95/115');
        $this->assertContains('APPLE', $tokens);
        $this->assertContains('ARIANE', $tokens);
        $this->assertNotContains('BIO', $tokens);
        $this->assertNotContains('KG', $tokens);
    }

    public function test_normalize_handles_html_display_wrappers(): void
    {
        $matcher = new DynamisMatcherService;

        $tokens = $matcher->normalize('<HTML><center>Apples<br>Ariane<br>Kg');
        $this->assertContains('APPLE', $tokens, 'Plural "Apples" should singularise to "APPLE"');
        $this->assertContains('ARIANE', $tokens);
        $this->assertNotContains('KG', $tokens);
        $this->assertNotContains('HTML', $tokens, 'HTML tags should be stripped before tokenising');
    }

    public function test_normalize_drops_short_and_noise_tokens(): void
    {
        $matcher = new DynamisMatcherService;

        $tokens = $matcher->normalize('  a  BIO  organic  KG ');
        $this->assertSame([], $tokens);
    }
}
