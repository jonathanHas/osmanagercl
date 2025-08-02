<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\LabelTemplate;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        LabelTemplate::create([
            'name' => 'Grid 4x9 (47x31mm)',
            'description' => '4 columns x 9 rows grid layout - 36 labels per A4 sheet',
            'width_mm' => 47.5,
            'height_mm' => 30.8,
            'margin_mm' => 2,
            'font_size_name' => 9,
            'font_size_barcode' => 7,
            'font_size_price' => 12,
            'barcode_height' => 15,
            'layout_config' => [
                'type' => 'grid_4x9',
                'barcode_position' => 'bottom_left',
                'price_position' => 'bottom_right',
                'name_position' => 'top_full_width'
            ],
            'is_default' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        LabelTemplate::where('name', 'Grid 4x9 (47x31mm)')->delete();
    }
};
