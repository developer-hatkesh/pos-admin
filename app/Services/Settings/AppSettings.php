<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AppSettings
{
    public const DEFAULT_STORE_NAME = 'Welcome to POS';

    public static function applyMailSettings(): void
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return;
            }

            $settings = AppSetting::getValue('mail');
        } catch (Throwable) {
            return;
        }

        if ($settings === []) {
            return;
        }

        static::configureMail($settings);
    }

    public static function configureMail(array $settings): void
    {
        $scheme = static::mailScheme($settings['mail_encryption'] ?? null);

        config([
            'mail.default' => $settings['mail_mailer'] ?? config('mail.default'),
            'mail.mailers.smtp.host' => $settings['mail_host'] ?? config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => (int) ($settings['mail_port'] ?? config('mail.mailers.smtp.port')),
            'mail.mailers.smtp.username' => ($settings['mail_username'] ?? null) ?: null,
            'mail.mailers.smtp.password' => ($settings['mail_password'] ?? null) ?: null,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.from.address' => $settings['mail_from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $settings['mail_from_name'] ?? config('mail.from.name'),
        ]);

        app('mail.manager')->forgetMailers();
    }

    private static function mailScheme(mixed $encryption): ?string
    {
        return match (strtolower(trim((string) $encryption))) {
            'tls', 'starttls', 'smtp' => 'smtp',
            'ssl', 'smtps' => 'smtps',
            default => null,
        };
    }

    public static function storeSettings(): array
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return [];
            }

            return AppSetting::getValue('store');
        } catch (Throwable) {
            return [];
        }
    }

    public static function storeBrandName(): string
    {
        //$name = trim((string) (static::storeSettings()['store_name'] ?? ''));

        return static::DEFAULT_STORE_NAME;
    }

    public static function storeLogoUrl(): ?string
    {
        $logo = static::storeSettings()['store_logo'] ?? null;

        if (! is_string($logo) || trim($logo) === '') {
            return null;
        }

        return Storage::disk('public')->url($logo);
    }

    public static function receiptSettings(): array
    {
        $defaults = [
            'receipt_show_note' => true,
            'receipt_show_phone' => true,
            'receipt_show_customer' => true,
            'receipt_show_address' => false,
            'receipt_show_email' => false,
            'receipt_show_discount_shipping' => true,
            'receipt_show_product_code' => true,
            'receipt_show_tax' => false,
            'receipt_labels_font_style' => 'bold',
            'receipt_other_font_style' => 'normal',
            'receipt_note' => 'Thanks for order',
        ];

        try {
            if (! Schema::hasTable('app_settings')) {
                return $defaults;
            }

            return [...$defaults, ...AppSetting::getValue('receipt')];
        } catch (Throwable) {
            return $defaults;
        }
    }
}
