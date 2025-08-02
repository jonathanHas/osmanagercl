<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\LabelTemplateSeeder;

class SyncLabelTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'label:sync-templates 
                            {--show : Display current template values}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync label templates from seeder to ensure consistency';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('show')) {
            $this->showCurrentTemplates();
            return Command::SUCCESS;
        }

        $this->info('Syncing label templates...');
        
        try {
            // Run the label template seeder
            $seeder = new LabelTemplateSeeder();
            $seeder->run();
            
            $this->info('✅ Label templates synced successfully!');
            
            // Show what was synced
            $this->showCurrentTemplates();
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to sync label templates: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display current template values
     */
    private function showCurrentTemplates(): void
    {
        $templates = \App\Models\LabelTemplate::all(['name', 'font_size_price', 'font_size_name', 'font_size_barcode']);
        
        if ($templates->isEmpty()) {
            $this->warn('No label templates found.');
            return;
        }
        
        $this->info('Current label templates:');
        $this->table(
            ['Name', 'Price Font (pt)', 'Name Font (pt)', 'Barcode Font (pt)'],
            $templates->map(function ($template) {
                return [
                    $template->name,
                    $template->font_size_price,
                    $template->font_size_name,
                    $template->font_size_barcode,
                ];
            })
        );
    }
}