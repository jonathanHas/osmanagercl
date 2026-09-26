<?php

namespace App\Console\Commands;

use App\Services\ProductThumbnailService;
use Illuminate\Console\Command;

/**
 * Remove cached fruit & veg thumbnails that no longer match a product photo.
 *
 * `ProductThumbnailService::jpeg()` already clears a product's older files when it
 * writes a new one, so this only matters for a product whose photo will never
 * change again — its orphan would otherwise stay forever.
 *
 * The cache folder is created by the web server, so deleting from it needs that
 * user. Run from a shell as anyone else and the deletes fail; the command says so
 * plainly and exits non-zero rather than pretending it worked.
 */
class PruneFruitVegThumbnails extends Command
{
    protected $signature = 'fruit-veg:prune-thumbnails';

    protected $description = 'Delete cached F&V product thumbnails that no longer match a product photo';

    public function handle(ProductThumbnailService $thumbnails): int
    {
        $result = $thumbnails->prune(ProductThumbnailService::currentPhotoProducts());

        $this->info("Kept {$result['kept']}, deleted {$result['deleted']}.");

        if ($result['failed'] === []) {
            return self::SUCCESS;
        }

        $this->error(sprintf(
            'Could not delete %d files (permission?): run this as the web server user, '
            .'e.g. sudo -u www-data php artisan fruit-veg:prune-thumbnails, '
            .'or use the Tidy thumbnail cache button on the F&V manage page.',
            count($result['failed'])
        ));

        return self::FAILURE;
    }
}
