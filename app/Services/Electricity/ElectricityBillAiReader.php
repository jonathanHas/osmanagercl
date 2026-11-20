<?php

namespace App\Services\Electricity;

use App\Services\AiSettingsService;
use App\Services\ClaudeAiService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reads an electricity bill PDF with Claude into the same neutral array the
 * provider parsers return. Used for a provider no parser knows, and from the
 * "Re-read with AI" button. Its results always go to review.
 *
 * Settings: AI Diagnostics → "Electricity Bill Reading" (feature key
 * electricity_parsing). Only the Anthropic provider is supported, because the
 * bill is sent as a whole PDF.
 */
class ElectricityBillAiReader
{
    public const FEATURE = 'electricity_parsing';

    public function __construct(private ClaudeAiService $claude) {}

    public function isAvailable(): bool
    {
        return AiSettingsService::get(self::FEATURE, 'provider') === 'anthropic'
            && (bool) AiSettingsService::getApiKey(self::FEATURE);
    }

    public function read(string $pdfPath): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('AI reading needs the Anthropic provider and ANTHROPIC_API_KEY (AI Diagnostics → Electricity Bill Reading).');
        }

        $text = $this->claude->complete(self::FEATURE, $this->prompt(), [
            ['data' => base64_encode(file_get_contents($pdfPath)), 'mime' => 'application/pdf'],
        ]);

        return $this->normalise($this->decode($text));
    }

    /** JSON from the reply, with or without a code fence around it. */
    public function decode(string $text): array
    {
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text)));
        $data = json_decode($text, true);

        if (! is_array($data)) {
            Log::warning('Electricity AI reader: reply was not JSON', ['raw' => substr($text, 0, 500)]);
            throw new RuntimeException('The AI reply could not be read as data.');
        }

        return $data;
    }

    /** Force the reply into the neutral shape: known keys, numbers as numbers, vocabulary lower-case. */
    public function normalise(array $data): array
    {
        $num = fn ($v) => is_numeric($v) ? (float) $v : null;
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        $str = fn ($v) => is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null;
        $word = fn ($v) => is_string($v) ? strtolower(trim($v)) : null;

        return [
            'provider' => $str($data['provider'] ?? null) ?? 'Unknown',
            'mprn' => $str($data['mprn'] ?? null),
            'account_number' => $str($data['account_number'] ?? null),
            'bill_number' => $str($data['bill_number'] ?? null),
            'document_type' => $word($data['document_type'] ?? null) === 'credit_note' ? 'credit_note' : 'invoice',
            'issue_date' => $date($data['issue_date'] ?? null),
            'due_date' => $date($data['due_date'] ?? null),
            'period_start' => $date($data['period_start'] ?? null),
            'period_end' => $date($data['period_end'] ?? null),
            'tariff' => $str($data['tariff'] ?? null),
            'dg_group' => $str($data['dg_group'] ?? null),
            'mic_kva' => is_numeric($data['mic_kva'] ?? null) ? (int) $data['mic_kva'] : null,
            'net_total' => $num($data['net_total'] ?? null),
            'vat_total' => $num($data['vat_total'] ?? null),
            'export_credit_total' => $num($data['export_credit_total'] ?? null) ?? 0.0,
            'gross_total' => $num($data['gross_total'] ?? null),
            'charges' => array_values(array_map(fn ($c) => [
                'category' => $word($c['category'] ?? null) ?? 'other',
                'band' => $word($c['band'] ?? null),
                'description' => $str($c['description'] ?? null) ?? '',
                'quantity' => $num($c['quantity'] ?? null),
                'unit' => $word($c['unit'] ?? null),
                'rate' => $num($c['rate'] ?? null),
                'period_start' => $date($c['period_start'] ?? null),
                'period_end' => $date($c['period_end'] ?? null),
                'net_amount' => $num($c['net_amount'] ?? null) ?? 0.0,
                'vat_rate' => $num($c['vat_rate'] ?? null),
            ], array_filter($data['charges'] ?? [], 'is_array'))),
            'readings' => array_values(array_map(fn ($r) => [
                'meter_serial' => $str($r['meter_serial'] ?? null),
                'band' => $word($r['band'] ?? null) ?? '24h',
                'period_start' => $date($r['period_start'] ?? null),
                'period_end' => $date($r['period_end'] ?? null),
                'previous_reading' => $num($r['previous_reading'] ?? null) ?? 0.0,
                'previous_type' => $word($r['previous_type'] ?? null) ?? 'estimate',
                'present_reading' => $num($r['present_reading'] ?? null) ?? 0.0,
                'present_type' => $word($r['present_type'] ?? null) ?? 'estimate',
                'multiplier' => $num($r['multiplier'] ?? null) ?? 1.0,
                'kwh' => $num($r['kwh'] ?? null) ?? 0.0,
                'is_reversal' => (bool) ($r['is_reversal'] ?? false),
            ], array_filter($data['readings'] ?? [], 'is_array'))),
            'warnings' => array_values(array_filter(array_map($str, (array) ($data['warnings'] ?? [])))),
        ];
    }

    private function prompt(): string
    {
        $categories = implode(' | ', ElectricityBillValidator::CATEGORIES);

        return <<<PROMPT
The attached PDF is a business electricity bill or credit note from an Irish supplier.
Copy its figures into the JSON below. Transcribe exactly what is printed: do not calculate,
round, correct or fill in anything the bill does not show. Use null for anything not printed.
Return ONLY the JSON object, with no commentary and no code fence.

{
  "provider": "supplier's trading name, e.g. Flogas, Electric Ireland, Energia",
  "mprn": "11-digit Meter Point Reference Number",
  "account_number": "string",
  "bill_number": "invoice or credit note number as printed",
  "document_type": "invoice | credit_note",
  "issue_date": "YYYY-MM-DD",
  "due_date": "YYYY-MM-DD",
  "period_start": "YYYY-MM-DD (billing period as stated in the bill header)",
  "period_end": "YYYY-MM-DD",
  "tariff": "tariff name as printed",
  "dg_group": "e.g. DG5",
  "mic_kva": 15,
  "net_total": 0.00,
  "vat_total": 0.00,
  "export_credit_total": 0.00,
  "gross_total": 0.00,
  "charges": [
    {
      "category": "{$categories}",
      "band": "24h | day | night | peak (energy lines only, else null)",
      "description": "the line's name as printed",
      "quantity": 0.0,
      "unit": "kwh | mwh | day | month | kva (the unit of the rate, lower case)",
      "rate": 0.0,
      "period_start": "YYYY-MM-DD (the line's own date range if printed)",
      "period_end": "YYYY-MM-DD",
      "net_amount": 0.00,
      "vat_rate": 9.0
    }
  ],
  "readings": [
    {
      "meter_serial": "meter number as printed",
      "band": "24h | day | night | peak",
      "period_start": "YYYY-MM-DD",
      "period_end": "YYYY-MM-DD (the bill's period end if the row has no end date)",
      "previous_reading": 0,
      "previous_type": "actual | customer | estimate",
      "present_reading": 0,
      "present_type": "actual | customer | estimate",
      "multiplier": 1,
      "kwh": 0,
      "is_reversal": false
    }
  ],
  "warnings": ["anything unclear or unreadable, in plain words"]
}

Rules:
- One "charges" entry per printed charge line, in the order printed. Include lines with a zero amount.
- Categories: energy = charges per kWh of electricity used (unit rates, day/night/peak units);
  standing = standing charge; pso_levy = PSO levy; electricity_tax = electricity tax;
  market = I-SEM, capacity or market charges; network = network, MIC, DUoS, TUoS or power-factor charges;
  export_credit = payment for exported (solar) electricity, with negative quantity and amount as printed;
  adjustment = a lump credit or debit with no quantity; other = anything else (exit fees, late fees).
- net_total is the bill's net amount for this period before VAT and excludes any export credit.
  gross_total is the amount for this bill only ("Current Bill"), not including balances brought forward or payments.
  export_credit_total is negative when a credit is given, 0 when there is none.
- Amounts and quantities keep their printed sign. A credit note's figures are negative.
- Read types: A or "Actual" = actual; C or "Customer" = customer; E, "Estimate" or a supplier estimate = estimate.
- A meter row that reverses consumption billed earlier (often shown with a minus sign, with a note that
  an estimate was reversed and replaced) has "is_reversal": true and a negative kwh.
- Dates as YYYY-MM-DD. Irish bills print day/month/year.
PROMPT;
    }
}
