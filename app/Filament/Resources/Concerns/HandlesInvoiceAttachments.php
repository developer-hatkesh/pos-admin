<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

trait HandlesInvoiceAttachments
{
    public static function attachmentUploadField(string $directory): FileUpload
    {
        return FileUpload::make('attachment_upload')
            ->label('Attachment')
            ->disk('s3')
            ->directory(fn (?Model $record): string => $record === null ? "{$directory}/tmp" : "{$directory}/{$record->getKey()}/incoming")
            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(10240)
            ->openable()
            ->downloadable()
            ->deleteUploadedFileUsing(fn (): null => null)
            ->dehydrated()
            ->columnSpanFull();
    }

    public static function pullAttachmentPaths(array &$data): array
    {
        $attachmentPaths = Arr::wrap($data['attachment_upload'] ?? []);

        unset($data['attachment_upload']);

        return array_values(array_filter($attachmentPaths, is_string(...)));
    }

    public static function syncAttachment(Model $record, array $selectedPaths, string $collection): void
    {
        $selectedPath = $selectedPaths[0] ?? null;

        if ($selectedPath !== null && Storage::disk('s3')->exists($selectedPath)) {
            $record
                ->addMediaFromDisk($selectedPath, 's3')
                ->toMediaCollection($collection, 's3');
        }

        $record->refresh();

        if (method_exists($record, 'syncAttachmentUrl')) {
            $record->syncAttachmentUrl();
        }
    }
}
