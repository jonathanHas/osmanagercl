<?php

namespace Database\Seeders;

use App\Models\LabelTemplate;
use Illuminate\Database\Seeder;

class LabelTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Standard (58x40mm)',
                'description' => 'Standard label size, good for most products',
                'width_mm' => 58,
                'height_mm' => 40,
                'margin_mm' => 4,
                'font_size_name' => 11,
                'font_size_barcode' => 10,
                'font_size_price' => 16,
                'barcode_height' => 20,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Grid 4x9 (47x31mm)',
                'description' => '4 columns x 9 rows grid layout - 36 labels per A4 sheet',
                'width_mm' => 47,
                'height_mm' => 31,
                'margin_mm' => 2,
                'font_size_name' => 12,
                'font_size_barcode' => 7,
                'font_size_price' => 26,  // Maximum 26pt as specified
                'barcode_height' => 15,
                'layout_config' => [
                    'type' => 'grid_4x9',
                    'barcode_position' => 'bottom_left',
                    'price_position' => 'bottom_right',
                    'name_position' => 'top_full_width',
                ],
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Small (38x21mm)',
                'description' => 'Compact labels for small items or tight spaces',
                'width_mm' => 38,
                'height_mm' => 21,
                'margin_mm' => 2,
                'font_size_name' => 8,
                'font_size_barcode' => 7,
                'font_size_price' => 10,
                'barcode_height' => 12,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Mini (25x15mm)',
                'description' => 'Very small labels for tiny products',
                'width_mm' => 25,
                'height_mm' => 15,
                'margin_mm' => 1,
                'font_size_name' => 6,
                'font_size_barcode' => 5,
                'font_size_price' => 8,
                'barcode_height' => 8,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Large (70x50mm)',
                'description' => 'Large labels with extra space for information',
                'width_mm' => 70,
                'height_mm' => 50,
                'margin_mm' => 5,
                'font_size_name' => 14,
                'font_size_barcode' => 12,
                'font_size_price' => 20,
                'barcode_height' => 25,
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            LabelTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }
    }
}
