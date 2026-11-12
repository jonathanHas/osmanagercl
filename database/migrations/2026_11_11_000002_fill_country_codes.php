<?php

use App\Models\Country;
use Illuminate\Database\Migrations\Migration;

/**
 * ISO two-letter codes for the countries the shop already lists, so a
 * supplier invoice's origin column ("FR", "ES") can be tied to one of them.
 * Only empty codes are filled; Corsica has no ISO code of its own and is left.
 */
return new class extends Migration
{
    private const CODES = [
        'Ireland' => 'IE', 'France' => 'FR', 'Netherlands' => 'NL', 'Spain' => 'ES',
        'Dominican Republic' => 'DO', 'Argentina' => 'AR', 'Portugal' => 'PT', 'Italy' => 'IT',
        'Peru' => 'PE', 'Egypt' => 'EG', 'Brazil' => 'BR', 'Costa Rica' => 'CR', 'Burundi' => 'BI',
        'Morocco' => 'MA', 'Chile' => 'CL', 'Ivory Coast' => 'CI', 'Colombia' => 'CO',
        'South Africa' => 'ZA', 'Burkina Faso' => 'BF', 'New Zealand' => 'NZ', 'Senegal' => 'SN',
        'America' => 'US', 'Mexico' => 'MX', 'Togo' => 'TG', 'Austria' => 'AT', 'Belgium' => 'BE',
        'Réunion' => 'RE', 'Ethiopia' => 'ET', 'Greece' => 'GR', 'Ecuador' => 'EC', 'Germany' => 'DE',
        'Jordan' => 'JO', 'Kenya' => 'KE', 'Poland' => 'PL', 'Madagascar' => 'MG', 'Turkey' => 'TR',
    ];

    public function up(): void
    {
        foreach (self::CODES as $name => $code) {
            Country::where('name', $name)->whereNull('code')->update(['code' => $code]);
        }
    }

    public function down(): void
    {
        Country::whereIn('code', array_values(self::CODES))->update(['code' => null]);
    }
};
