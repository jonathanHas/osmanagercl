<?php

namespace App\Services\Electricity\Contracts;

/**
 * Reads one provider's electricity bill into the provider-neutral bill array
 * described in docs/features/electricity.md. A new provider is one new class
 * listed in ElectricityBillImportService::PARSERS.
 */
interface ElectricityBillParser
{
    /** Short provider name stored on the bill, e.g. "Flogas". */
    public function provider(): string;

    /** Whether this parser recognises the bill, given the full page text. */
    public function supports(string $fullText): bool;

    /**
     * Width in points of the left part of each page to extract separately,
     * or null when the full text is enough. Bills that print a side panel
     * beside the tables need the tables read without it.
     */
    public function cropWidth(): ?int;

    /**
     * @param  string  $fullText  pdftotext -layout of every page
     * @param  string|null  $croppedText  the same, limited to cropWidth()
     * @return array the neutral bill array (see ElectricityBillValidator)
     */
    public function parse(string $fullText, ?string $croppedText = null): array;
}
