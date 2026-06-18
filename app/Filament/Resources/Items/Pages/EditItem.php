<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use App\Models\ProductItem;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditItem extends EditRecord
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ProductItem $record */
        $record = $this->getRecord();

        return [
            ...$data,
            'product_type' => $data['product_type'] ?? 'single',
            'variation_items' => ItemResource::variationRowsFor($record->variation_id, $record),
            'product_images' => $record->getMedia(ProductItem::PRODUCT_IMAGES_COLLECTION)
                ->map->getPathRelativeToRoot()
                ->values()
                ->all(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ProductItem $record */
        ItemResource::updateRecordWithProductImages($record, $data);

        return $record;
    }
}
