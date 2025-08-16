<?php

namespace Database\Seeders;

use App\Models\CoffeeProductMetadata;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoffeeProductMetadataSeeder extends Seeder
{
    public function run()
    {
        // First, get all current coffee products from POS
        $products = DB::connection('pos')
            ->table('PRODUCTS')
            ->where('CATEGORY', '081')
            ->select('ID', 'NAME')
            ->get();

        $coffeeMetadata = [
            // Coffee Types (main drinks)
            'Americano' => ['type' => 'coffee', 'short_name' => 'Americano', 'display_order' => 1],
            'Latte' => ['type' => 'coffee', 'short_name' => 'Latte', 'display_order' => 2],
            'Cappuccino' => ['type' => 'coffee', 'short_name' => 'Cappuccino', 'display_order' => 3],
            'Flat White' => ['type' => 'coffee', 'short_name' => 'Flat White', 'display_order' => 4],
            'Mocha' => ['type' => 'coffee', 'short_name' => 'Mocha', 'display_order' => 5],
            'Espresso' => ['type' => 'coffee', 'short_name' => 'Espresso', 'display_order' => 6],
            'Iced Coffee' => ['type' => 'coffee', 'short_name' => 'Iced Coffee', 'display_order' => 7],
            'Chai Latte' => ['type' => 'coffee', 'short_name' => 'Chai Latte', 'display_order' => 8],
            'Hot Chocolate Large' => ['type' => 'coffee', 'short_name' => 'Hot Choc L', 'display_order' => 9],
            'Hot Chocolate Small' => ['type' => 'coffee', 'short_name' => 'Hot Choc S', 'display_order' => 10],
            'Tea - Regular' => ['type' => 'coffee', 'short_name' => 'Tea', 'display_order' => 11],
            'Tea - Herbal' => ['type' => 'coffee', 'short_name' => 'Herbal Tea', 'display_order' => 12],

            // Options grouped by category
            'Milk Alternative' => ['type' => 'option', 'short_name' => 'Alt Milk', 'group_name' => 'Milk', 'display_order' => 1],
            'Milk Alt OAT' => ['type' => 'option', 'short_name' => 'Oat', 'group_name' => 'Milk', 'display_order' => 2],

            'Syrup' => ['type' => 'option', 'short_name' => 'Syrup', 'group_name' => 'Syrups', 'display_order' => 1],

            'Espresso Extra Shot' => ['type' => 'option', 'short_name' => 'Extra Shot', 'group_name' => 'Coffee', 'display_order' => 1],

            'Take Away' => ['type' => 'option', 'short_name' => 'Takeaway', 'group_name' => 'Service', 'display_order' => 1],
            '2GoCup Cup' => ['type' => 'option', 'short_name' => '2Go Cup', 'group_name' => 'Service', 'display_order' => 2],
            '2GoCup Cup Return' => ['type' => 'option', 'short_name' => '2Go Return', 'group_name' => 'Service', 'display_order' => 3],
            'Cup Discount' => ['type' => 'option', 'short_name' => 'Cup Disc', 'group_name' => 'Service', 'display_order' => 4],

            // Special items (treat as coffee for now, can be adjusted)
            'Happy Hour Coffee & Sausage Roll' => ['type' => 'coffee', 'short_name' => 'Coffee Deal', 'display_order' => 99],
        ];

        foreach ($products as $product) {
            $productName = $product->NAME;

            // Check if we have metadata for this product
            if (isset($coffeeMetadata[$productName])) {
                $metadata = $coffeeMetadata[$productName];

                CoffeeProductMetadata::updateOrCreate(
                    ['product_id' => $product->ID],
                    [
                        'product_name' => $productName,
                        'type' => $metadata['type'],
                        'short_name' => $metadata['short_name'],
                        'group_name' => $metadata['group_name'] ?? null,
                        'display_order' => $metadata['display_order'],
                        'is_active' => true,
                    ]
                );
            } else {
                // Create default entry for unknown products
                CoffeeProductMetadata::updateOrCreate(
                    ['product_id' => $product->ID],
                    [
                        'product_name' => $productName,
                        'type' => 'coffee', // Default to coffee type
                        'short_name' => $this->generateShortName($productName),
                        'group_name' => null,
                        'display_order' => 999,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('Coffee product metadata seeded successfully.');
        $this->command->info('Total products processed: '.$products->count());
    }

    /**
     * Generate a short name from the full product name
     */
    private function generateShortName($name)
    {
        // Remove HTML tags and clean up
        $clean = strip_tags($name);
        $clean = str_replace(['<br>', '<center>'], ' ', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        // If it's short enough, use as-is
        if (strlen($clean) <= 12) {
            return $clean;
        }

        // Try to abbreviate
        $words = explode(' ', $clean);
        if (count($words) > 1) {
            // Take first letter of each word except the last
            $abbreviated = '';
            for ($i = 0; $i < count($words) - 1; $i++) {
                $abbreviated .= substr($words[$i], 0, 1);
            }
            $abbreviated .= ' '.$words[count($words) - 1];

            if (strlen($abbreviated) <= 12) {
                return $abbreviated;
            }
        }

        // Fallback: truncate to 12 characters
        return substr($clean, 0, 12);
    }
}
