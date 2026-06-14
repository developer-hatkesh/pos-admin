<?php

declare(strict_types=1);

namespace App\Support\MediaLibrary;

use App\Models\ProductItem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

class ProductItemPathGenerator extends DefaultPathGenerator
{
    public function getPath(Media $media): string
    {
        if ($this->isProductImage($media)) {
            return "products/{$media->model_id}/";
        }

        return parent::getPath($media);
    }

    public function getPathForConversions(Media $media): string
    {
        if ($this->isProductImage($media)) {
            return "products/{$media->model_id}/conversions/";
        }

        return parent::getPathForConversions($media);
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        if ($this->isProductImage($media)) {
            return "products/{$media->model_id}/responsive-images/";
        }

        return parent::getPathForResponsiveImages($media);
    }

    private function isProductImage(Media $media): bool
    {
        return $media->collection_name === ProductItem::PRODUCT_IMAGES_COLLECTION;
    }
}
