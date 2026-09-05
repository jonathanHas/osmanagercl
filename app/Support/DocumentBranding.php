<?php

namespace App\Support;

/**
 * Branding block shared by customer-facing documents (statement PDF, statement
 * print view, invoice PDF).
 *
 * Lives in PHP rather than a Blade partial because Blade @include has its own
 * variable scope — values assigned in an included partial are not visible to the
 * including view, so the old duplicated @php preamble could not simply be
 * extracted into a partial.
 *
 * Dompdf cannot fetch remote assets, so the organic cert image is base64-inlined.
 *
 * @return array<string, mixed>
 */
class DocumentBranding
{
    /**
     * @return array<string, mixed>
     */
    public static function context(): array
    {
        $business = config('app.business', []);

        $certPath = ! empty($business['organic_cert_image'])
            ? public_path($business['organic_cert_image'])
            : null;
        $certData = ($certPath && is_file($certPath))
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($certPath))
            : null;

        $footerAddress = implode(', ', array_filter([
            $business['address_line1'] ?? null,
            $business['address_line2'] ?? null,
            $business['county'] ?? null,
        ]));
        if (! empty($business['postcode'])) {
            $footerAddress .= '. '.$business['postcode'];
        }

        $contactLine = implode(' · ', array_filter([
            ! empty($business['phone']) ? 'Tel: '.$business['phone'] : null,
            $business['email'] ?? null,
            ! empty($business['vat']) ? 'VAT: '.$business['vat'] : null,
        ]));

        $businessLine2 = implode(', ', array_filter([
            $business['address_line2'] ?? null,
            $business['county'] ?? null,
        ]));
        if (! empty($business['postcode'])) {
            $businessLine2 .= '. '.$business['postcode'];
        }

        $contactParts = array_filter([
            ! empty($business['phone']) ? 'Phone: '.$business['phone'] : null,
            ! empty($business['email']) ? 'Email: '.$business['email'] : null,
        ]);

        return compact(
            'business', 'certData', 'footerAddress', 'contactLine', 'businessLine2', 'contactParts'
        );
    }
}
