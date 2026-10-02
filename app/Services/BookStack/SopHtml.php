<?php

namespace App\Services\BookStack;

use DOMElement;
use DOMXPath;
use Masterminds\HTML5;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Makes a BookStack page's HTML safe to show inside our origin, where the
 * staff session lives, and self-contained: uploaded images are served through
 * our own proxy (BookStack is plain HTTP on an internal name the tablets may
 * not resolve), and other root-relative links point back at BookStack.
 */
class SopHtml
{
    private const IMAGE_PREFIX = '/uploads/images/';

    public function __construct(private BookStackClient $client) {}

    public function clean(string $html): string
    {
        $safe = $this->sanitizer()->sanitize($html);

        return $this->rewriteUrls($safe);
    }

    private function sanitizer(): HtmlSanitizer
    {
        return new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                // BookStack callouts are <p class="callout info">.
                ->allowAttribute('class', '*')
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->forceAttribute('a', 'target', '_blank')
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                // The default (20,000) silently truncates a long procedure.
                ->withMaxInputLength(1_000_000)
        );
    }

    private function rewriteUrls(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $html5 = new HTML5(['disable_html_ns' => true]);
        $fragment = $html5->loadHTMLFragment($html);
        // XPath on a fragment misses its top-level nodes, so query a container.
        $container = $fragment->ownerDocument->createElement('div');
        $container->appendChild($fragment);
        $xpath = new DOMXPath($container->ownerDocument);

        $bookstackHost = strtolower((string) parse_url($this->client->baseUrl(), PHP_URL_HOST));

        foreach ($xpath->query('.//img[@src] | .//a[@href]', $container) as $node) {
            /** @var DOMElement $node */
            $attr = $node->nodeName === 'img' ? 'src' : 'href';
            $url = $node->getAttribute($attr);
            $parts = parse_url($url);
            if ($parts === false) {
                continue;
            }

            $host = strtolower($parts['host'] ?? '');
            $path = $parts['path'] ?? '';

            if (str_starts_with($path, self::IMAGE_PREFIX) && ($host === '' || $host === $bookstackHost)) {
                $node->setAttribute($attr, route('help.image', [
                    'path' => ltrim(rawurldecode($path), '/'),
                ], absolute: false));
            } elseif (str_starts_with($url, '/')) {
                $node->setAttribute($attr, $this->client->baseUrl().$url);
            }
        }

        $out = '';
        foreach ($container->childNodes as $child) {
            $out .= $html5->saveHTML($child);
        }

        return $out;
    }
}
