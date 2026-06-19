<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductItem;
use Illuminate\Console\Command;

class AttachDefaultProductImage extends Command
{
    protected $signature = 'products:attach-default-image
        {path=public/data/default.png : Local image path relative to the project root}
        {--disk= : Media disk to store the image on. Defaults to MEDIA_DISK/config media-library disk_name}
        {--force : Attach even when a product already has images}';

    protected $description = 'Attach a default image to existing product items.';

    public function handle(): int
    {
        $path = base_path($this->argument('path'));

        if (! is_file($path)) {
            $this->error("Image file not found: {$path}");

            return self::FAILURE;
        }

        $query = ProductItem::query()->withoutGlobalScopes();
        $disk = $this->option('disk') ?: config('media-library.disk_name', 'public');

        if (! $this->option('force')) {
            $query->whereDoesntHave('media', fn ($query) => $query->where('collection_name', ProductItem::PRODUCT_IMAGES_COLLECTION));
        }

        $attached = 0;

        $query->chunkById(100, function ($items) use ($path, $disk, &$attached): void {
            foreach ($items as $item) {
                $item
                    ->addMedia($path)
                    ->preservingOriginal()
                    ->toMediaCollection(ProductItem::PRODUCT_IMAGES_COLLECTION, $disk);

                $item->syncProductImageUrls();
                $attached++;
            }
        });

        $this->info("Attached default image to {$attached} product item(s) on disk [{$disk}].");

        return self::SUCCESS;
    }
}
