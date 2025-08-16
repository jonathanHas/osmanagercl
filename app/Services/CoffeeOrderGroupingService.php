<?php

namespace App\Services;

use App\Models\CoffeeProductMetadata;

class CoffeeOrderGroupingService
{
    /**
     * Group order items by coffee type with associated options
     *
     * @param  array  $orderItems  Array of order items
     * @return array Grouped items for display
     */
    public function groupOrderItems($orderItems)
    {
        $grouped = [];
        $currentGroup = null;

        // Process items in sequence to maintain order
        foreach ($orderItems as $item) {
            $metadata = CoffeeProductMetadata::where('product_id', $item['product_id'] ?? $item['id'])->first();

            // Determine if this is a coffee type or option
            $isCoffeeType = false;
            if ($metadata) {
                $isCoffeeType = ($metadata->type === 'coffee');
            } else {
                // Without metadata, check if it looks like a main coffee item
                // (this is a fallback - ideally all items should have metadata)
                $name = strtolower($item['product_name'] ?? $item['display_name'] ?? '');
                $isCoffeeType = $this->looksLikeCoffeeType($name);
            }

            if ($isCoffeeType) {
                // Save previous group if exists
                if ($currentGroup !== null) {
                    $grouped[] = $currentGroup;
                }

                // Start new coffee group
                $currentGroup = [
                    'type' => 'grouped',
                    'main_coffee' => [
                        'quantity' => $item['formatted_quantity'] ?? '1',
                        'name' => $metadata ? $metadata->short_name : $this->getShortDisplayName($item),
                        'full_name' => $item['product_name'] ?? $item['display_name'] ?? 'Unknown',
                    ],
                    'options' => [],
                    'notes' => $item['notes'] ?? null,
                ];
            } else {
                // This is an option/modifier
                if ($currentGroup !== null) {
                    // Add to current coffee group
                    $currentGroup['options'][] = [
                        'quantity' => $item['formatted_quantity'] ?? '1',
                        'name' => $metadata ? $metadata->short_name : $this->getShortDisplayName($item),
                        'group' => $metadata ? $metadata->group_name : 'Other',
                    ];
                } else {
                    // No current coffee group - treat as standalone item
                    $grouped[] = [
                        'type' => 'standalone',
                        'quantity' => $item['formatted_quantity'] ?? '1',
                        'name' => $metadata ? $metadata->short_name : $this->getShortDisplayName($item),
                        'full_name' => $item['product_name'] ?? $item['display_name'] ?? 'Unknown',
                        'notes' => $item['notes'] ?? null,
                    ];
                }
            }
        }

        // Add the last group if exists
        if ($currentGroup !== null) {
            $grouped[] = $currentGroup;
        }

        return $grouped;
    }

    /**
     * Distribute options among coffee types
     */
    private function distributeOptions($options, $coffeeCount)
    {
        if ($coffeeCount === 0 || empty($options)) {
            return [];
        }

        // Simple distribution - return options for first coffee
        // You might want to implement more sophisticated logic here
        $optionsPerCoffee = ceil(count($options) / $coffeeCount);

        return array_slice($options, 0, $optionsPerCoffee);
    }

    /**
     * Get remaining options not assigned to any coffee
     */
    private function getRemainingOptions($options, $coffeeCount)
    {
        if ($coffeeCount === 0 || empty($options)) {
            return $options;
        }

        $optionsPerCoffee = ceil(count($options) / $coffeeCount);

        return array_slice($options, $optionsPerCoffee);
    }

    /**
     * Format options for display
     */
    private function formatOptions($optionData)
    {
        $formatted = [];

        foreach ($optionData as $data) {
            $item = $data['item'];
            $meta = $data['metadata'];

            $formatted[] = [
                'quantity' => $item['formatted_quantity'] ?? '1',
                'name' => $meta ? $meta->short_name : $this->getShortDisplayName($item),
                'group' => $meta ? $meta->group_name : 'Other',
            ];
        }

        return $formatted;
    }

    /**
     * Fallback to individual items when grouping isn't possible
     */
    private function fallbackToIndividualItems($orderItems)
    {
        $items = [];

        foreach ($orderItems as $item) {
            $metadata = CoffeeProductMetadata::where('product_id', $item['product_id'] ?? $item['id'])->first();

            $items[] = [
                'type' => 'individual',
                'quantity' => $item['formatted_quantity'] ?? '1',
                'name' => $metadata ? $metadata->short_name : $this->getShortDisplayName($item),
                'full_name' => $item['product_name'] ?? $item['display_name'] ?? 'Unknown',
                'notes' => $item['notes'] ?? null,
            ];
        }

        return $items;
    }

    /**
     * Generate a short display name when metadata isn't available
     */
    private function getShortDisplayName($item)
    {
        $name = $item['product_name'] ?? $item['display_name'] ?? 'Unknown';

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

    /**
     * Check if a product name looks like a main coffee type (fallback when no metadata)
     */
    private function looksLikeCoffeeType($name)
    {
        $coffeeKeywords = [
            'cappuccino', 'latte', 'americano', 'espresso', 'flat white',
            'macchiato', 'cortado', 'mocha', 'coffee', 'brew',
        ];

        foreach ($coffeeKeywords as $keyword) {
            if (strpos($name, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get display format for mobile (compact single line)
     */
    public function getCompactDisplay($groupedItems)
    {
        $lines = [];

        foreach ($groupedItems as $group) {
            if ($group['type'] === 'grouped') {
                $line = $group['main_coffee']['quantity'].'x '.$group['main_coffee']['name'];

                if (! empty($group['options'])) {
                    $optionNames = array_map(function ($opt) {
                        return $opt['name'];
                    }, $group['options']);
                    $line .= ' + '.implode(', ', $optionNames);
                }

                $lines[] = $line;
            } elseif ($group['type'] === 'individual' || $group['type'] === 'standalone') {
                $lines[] = $group['quantity'].'x '.$group['name'];
            }
        }

        return $lines;
    }
}
