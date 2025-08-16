<?php

namespace App\Services;

use App\Models\CoffeeProductMetadata;

class CoffeeOrderGroupingService
{
    /**
     * Group order items by coffee type with associated options
     * 
     * @param array $orderItems Array of order items
     * @return array Grouped items for display
     */
    public function groupOrderItems($orderItems)
    {
        $grouped = [];
        $coffeeTypes = [];
        $options = [];

        // Separate coffee types from options
        foreach ($orderItems as $item) {
            $metadata = CoffeeProductMetadata::where('product_id', $item['product_id'] ?? $item['id'])->first();
            
            if ($metadata && $metadata->type === 'coffee') {
                $coffeeTypes[] = [
                    'item' => $item,
                    'metadata' => $metadata,
                ];
            } else if ($metadata && $metadata->type === 'option') {
                $options[] = [
                    'item' => $item,
                    'metadata' => $metadata,
                ];
            } else {
                // Fallback for items without metadata - treat as coffee
                $coffeeTypes[] = [
                    'item' => $item,
                    'metadata' => null,
                ];
            }
        }

        // If no coffee types but we have options, treat all as individual items
        if (empty($coffeeTypes) && !empty($options)) {
            return $this->fallbackToIndividualItems($orderItems);
        }

        // Group coffee types with their associated options
        foreach ($coffeeTypes as $coffeeData) {
            $coffeeItem = $coffeeData['item'];
            $coffeeMeta = $coffeeData['metadata'];
            
            $group = [
                'type' => 'grouped',
                'main_coffee' => [
                    'quantity' => $coffeeItem['formatted_quantity'] ?? '1',
                    'name' => $coffeeMeta ? $coffeeMeta->short_name : $this->getShortDisplayName($coffeeItem),
                    'full_name' => $coffeeItem['product_name'] ?? $coffeeItem['display_name'] ?? 'Unknown',
                ],
                'options' => [],
                'notes' => $coffeeItem['notes'] ?? null,
            ];

            // Add relevant options (you might want to implement logic to associate options with specific coffees)
            // For now, we'll distribute options evenly among coffee types
            $optionsPerCoffee = $this->distributeOptions($options, count($coffeeTypes));
            $group['options'] = $this->formatOptions($optionsPerCoffee);

            $grouped[] = $group;
        }

        // Add any remaining standalone options
        $remainingOptions = $this->getRemainingOptions($options, count($coffeeTypes));
        foreach ($remainingOptions as $optionData) {
            $optionItem = $optionData['item'];
            $optionMeta = $optionData['metadata'];
            
            $grouped[] = [
                'type' => 'standalone',
                'quantity' => $optionItem['quantity'] ?? $optionItem['formatted_quantity'] ?? '1',
                'name' => $optionMeta ? $optionMeta->short_name : $this->getShortDisplayName($optionItem),
                'full_name' => $optionItem['product_name'] ?? $optionItem['display_name'] ?? 'Unknown',
                'notes' => $optionItem['notes'] ?? null,
            ];
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
            $abbreviated .= ' ' . $words[count($words) - 1];
            
            if (strlen($abbreviated) <= 12) {
                return $abbreviated;
            }
        }

        // Fallback: truncate to 12 characters
        return substr($clean, 0, 12);
    }

    /**
     * Get display format for mobile (compact single line)
     */
    public function getCompactDisplay($groupedItems)
    {
        $lines = [];
        
        foreach ($groupedItems as $group) {
            if ($group['type'] === 'grouped') {
                $line = $group['main_coffee']['quantity'] . 'x ' . $group['main_coffee']['name'];
                
                if (!empty($group['options'])) {
                    $optionNames = array_map(function($opt) {
                        return $opt['name'];
                    }, $group['options']);
                    $line .= ' + ' . implode(', ', $optionNames);
                }
                
                $lines[] = $line;
            } else if ($group['type'] === 'individual' || $group['type'] === 'standalone') {
                $lines[] = $group['quantity'] . 'x ' . $group['name'];
            }
        }
        
        return $lines;
    }
}