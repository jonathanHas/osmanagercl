<?php

namespace App\Console\Commands;

use App\Models\SalesDailySummary;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Console\Command;

class CreateTestSalesData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:create-test-data {--days=30 : Number of days to create data for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test sales data for demonstration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $faker = Faker::create();
        $days = (int) $this->option('days');

        $this->info("Creating test sales data for {$days} days...");

        $products = [
            ['id' => 'PROD001', 'code' => 'APPLE001', 'name' => 'Organic Apples', 'category' => 'SUB1'],
            ['id' => 'PROD002', 'code' => 'BANANA001', 'name' => 'Fair Trade Bananas', 'category' => 'SUB1'],
            ['id' => 'PROD003', 'code' => 'CARROT001', 'name' => 'Organic Carrots', 'category' => 'SUB2'],
            ['id' => 'PROD004', 'code' => 'SPINACH001', 'name' => 'Baby Spinach', 'category' => 'SUB2'],
            ['id' => 'PROD005', 'code' => 'TOMATO001', 'name' => 'Cherry Tomatoes', 'category' => 'SUB3'],
        ];

        $createdCount = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($i);

            foreach ($products as $product) {
                // Only create data for some products on some days
                if ($faker->boolean(80)) { // 80% chance of sales
                    SalesDailySummary::create([
                        'product_id' => $product['id'],
                        'product_code' => $product['code'],
                        'product_name' => $product['name'],
                        'category_id' => $product['category'],
                        'sale_date' => $date,
                        'total_units' => $faker->randomFloat(2, 1, 50),
                        'total_revenue' => $faker->randomFloat(2, 10, 200),
                        'transaction_count' => $faker->numberBetween(1, 20),
                        'avg_price' => $faker->randomFloat(2, 2, 15),
                    ]);
                    $createdCount++;
                }
            }
        }

        $this->info("Created {$createdCount} test sales records!");

        return 0;
    }
}
