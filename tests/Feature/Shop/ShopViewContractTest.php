<?php

namespace Tests\Feature\Shop;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Shop screens are the one layer that must stay free of styling decisions:
 * all shop-* class composition and Alpine behaviour lives in the components
 * under resources/views/components/shop, all styling in resources/css/shop.css.
 * That is what makes a later retheme an edit of tokens rather than of screens.
 *
 * Components and layouts are deliberately not scanned — the layout legitimately
 * carries two small scripts.
 */
class ShopViewContractTest extends TestCase
{
    private const SCREENS = 'resources/views/shop';

    /**
     * Tailwind-shaped utility prefixes. A class is allowed through when it
     * starts with "shop".
     */
    private const UTILITY_PATTERN = '/^(bg-|text-|p-|px-|py-|pt-|pb-|pl-|pr-|m-|mx-|my-|mt-|mb-|ml-|mr-|w-|h-|min-|max-|rounded|border|shadow|gap-|flex|grid|items-|justify-|font-|space-|hidden|block|inline|dark:|sm:|md:|lg:|xl:|hover:|focus:)/';

    /**
     * Data providers run before the application boots, so base_path() is not
     * available here.
     */
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function screenProvider(): array
    {
        $root = self::projectRoot().'/'.self::SCREENS;

        if (! is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = [str_replace(self::projectRoot().'/', '', $file->getPathname())];
            }
        }

        sort($files);

        return $files;
    }

    public function test_there_are_screens_to_check(): void
    {
        $this->assertNotEmpty(
            self::screenProvider(),
            'No Blade files found under '.self::SCREENS.'; the contract scan would pass vacuously.'
        );
    }

    #[DataProvider('screenProvider')]
    public function test_screen_carries_no_styling_or_behaviour(string $path): void
    {
        $contents = file_get_contents(base_path($path));

        $this->assertStringNotContainsString('<style', $contents, "{$path} must not contain a <style> block; styling belongs in resources/css/shop.css.");
        $this->assertStringNotContainsString('<script', $contents, "{$path} must not contain a <script> block; behaviour belongs in resources/js/shop.js.");

        preg_match_all('/class="([^"]*)"/', $contents, $matches);

        foreach ($matches[1] as $attribute) {
            foreach (preg_split('/\s+/', $attribute, -1, PREG_SPLIT_NO_EMPTY) as $token) {
                if (str_starts_with($token, 'shop')) {
                    continue;
                }

                $this->assertDoesNotMatchRegularExpression(
                    self::UTILITY_PATTERN,
                    $token,
                    "{$path} uses the utility class \"{$token}\"; shop screens may only use shop-* classes."
                );
            }
        }
    }
}
