<?php

namespace App\Console\Commands;

use App\Models\LabelLog;
use App\Models\Product;
use App\Models\ProductMetadata;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillProductMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:backfill-metadata {--dry-run : Show what would be done without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill product metadata for existing products using LabelLog data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No data will be modified');
        }

        $this->info('🔄 Starting product metadata backfill...');

        // Get all products that don't have metadata yet
        // We need to use a subquery since Product is on 'pos' connection and ProductMetadata is on default connection
        $existingProductIds = ProductMetadata::pluck('product_id')->toArray();
        $productsWithoutMetadata = Product::whereNotIn('ID', $existingProductIds)->get();

        $this->info("📊 Found {$productsWithoutMetadata->count()} products without metadata");

        if ($productsWithoutMetadata->isEmpty()) {
            $this->info('✅ All products already have metadata!');
            return self::SUCCESS;
        }

        $created = 0;
        $withCreationLog = 0;
        $withoutCreationLog = 0;
        
        $progressBar = $this->output->createProgressBar($productsWithoutMetadata->count());
        $progressBar->start();

        foreach ($productsWithoutMetadata as $product) {
            // Try to find creation log from LabelLog
            $creationLog = LabelLog::where('barcode', $product->CODE)
                ->where('event_type', LabelLog::EVENT_NEW_PRODUCT)
                ->oldest()
                ->first();

            $createdAt = $creationLog?->created_at ?? now()->subYears(10);
            $createdBy = $creationLog?->user_id;
            
            if ($creationLog) {
                $withCreationLog++;
            } else {
                $withoutCreationLog++;
            }

            if (!$isDryRun) {
                // Create metadata record with proper timestamps
                $metadata = new ProductMetadata([
                    'product_id' => $product->ID,
                    'product_code' => $product->CODE,
                    'created_by' => $createdBy,
                    'metadata' => [
                        'source' => 'backfill',
                        'has_creation_log' => $creationLog !== null,
                        'backfilled_at' => now()->toISOString(),
                    ],
                ]);
                
                // Set custom timestamps
                $metadata->created_at = $createdAt;
                $metadata->updated_at = $createdAt;
                $metadata->save();
            }
            
            $created++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("📈 Backfill Summary:");
        $this->line("  • Products processed: {$productsWithoutMetadata->count()}");
        $this->line("  • With creation logs: {$withCreationLog}");
        $this->line("  • Without creation logs: {$withoutCreationLog}");
        
        if ($isDryRun) {
            $this->info("🔍 DRY RUN: {$created} metadata records would be created");
            $this->line("Run without --dry-run to actually create the records");
        } else {
            $this->info("✅ Created {$created} metadata records successfully!");
        }

        // Show some examples
        if (!$isDryRun && $created > 0) {
            $this->newLine();
            $this->info("📋 Latest products by creation date:");
            
            $latestMetadata = ProductMetadata::with('product')
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->get();
            
            $latestProducts = $latestMetadata->map(function ($metadata) {
                return (object) [
                    'CODE' => $metadata->product_code,
                    'NAME' => $metadata->product?->NAME ?? 'Product not found',
                    'metadata_created_at' => $metadata->created_at,
                ];
            });
            
            foreach ($latestProducts as $product) {
                $createdDate = $product->metadata_created_at 
                    ? \Carbon\Carbon::parse($product->metadata_created_at)->format('Y-m-d H:i:s')
                    : 'No metadata';
                $this->line("  • {$product->CODE}: {$product->NAME} (Created: {$createdDate})");
            }
        }

        return self::SUCCESS;
    }
}