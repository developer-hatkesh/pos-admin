<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Services\Settings\AppSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

class Settings extends Page
{
    protected static ?string $title = 'Settings';
    protected static ?string $navigationLabel = 'Settings';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;
    protected static string|UnitEnum|null $navigationGroup = 'System';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'settings';

    protected Width|string|null $maxContentWidth = Width::Full;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->settingsData());
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Settings')
                ->tabs([
                    Tab::make('Mail Settings')
                        ->schema([
                            Section::make('Mail Settings')
                                ->schema([
                                    Grid::make(2)->schema([
                                        Select::make('mail_mailer')
                                            ->label('MAIL_MAILER')
                                            ->options([
                                                'smtp' => 'smtp',
                                                'log' => 'log',
                                                'array' => 'array',
                                                'sendmail' => 'sendmail',
                                            ])
                                            ->required(),
                                        TextInput::make('mail_host')
                                            ->label('MAIL_HOST')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('mail_port')
                                            ->label('MAIL_PORT')
                                            ->numeric()
                                            ->required(),
                                        TextInput::make('mail_from_address')
                                            ->label('Sender Email')
                                            ->email()
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('mail_username')
                                            ->label('MAIL_USERNAME')
                                            ->maxLength(255),
                                        TextInput::make('mail_password')
                                            ->label('MAIL_PASSWORD')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(255),
                                        Select::make('mail_encryption')
                                            ->label('MAIL_ENCRYPTION')
                                            ->options([
                                                '' => 'None',
                                                'tls' => 'TLS',
                                                'ssl' => 'SSL',
                                            ]),
                                        TextInput::make('mail_from_name')
                                            ->label('Sender Name')
                                            ->required()
                                            ->maxLength(255),
                                    ]),
                                ])
                                ->columnSpanFull(),
                        ]),
                    Tab::make('Receipt Settings')
                        ->schema([
                            Section::make('Receipt Settings')
                                ->schema([
                                    Grid::make(3)->schema([
                                        Toggle::make('receipt_show_note')->label('Show note'),
                                        Toggle::make('receipt_show_phone')->label('Show Phone'),
                                        Toggle::make('receipt_show_customer')->label('Show Customer'),
                                        Toggle::make('receipt_show_address')->label('Show Address'),
                                        Toggle::make('receipt_show_email')->label('Show Email'),
                                        Toggle::make('receipt_show_discount_shipping')->label('Show Discount & Shipping'),
                                        Toggle::make('receipt_show_barcode')->label('Show barcode in receipt'),
                                        Toggle::make('receipt_show_logo_payment_slip')->label('Show logo in payment slip'),
                                        Toggle::make('receipt_show_product_code')->label('Show Product Code'),
                                        Toggle::make('receipt_show_tax')->label('Show Tax'),
                                    ]),
                                    Grid::make(3)->schema([
                                        Select::make('receipt_labels_font_style')
                                            ->label('Labels Font Style')
                                            ->options($this->fontStyleOptions())
                                            ->required(),
                                        Select::make('receipt_other_font_style')
                                            ->label('Other Font Style')
                                            ->options($this->fontStyleOptions())
                                            ->required(),
                                        Select::make('receipt_paper_size')
                                            ->label('Paper Size')
                                            ->options([
                                                'thermal' => 'Thermal',
                                                'a4' => 'A4',
                                            ])
                                            ->required(),
                                        Select::make('receipt_thermal_paper_size')
                                            ->label('Thermal Paper Size')
                                            ->options([
                                                '58mm' => '58mm',
                                                '80mm' => '80mm',
                                            ])
                                            ->required(),
                                        TextInput::make('receipt_margin')
                                            ->label('Margin')
                                            ->numeric()
                                            ->required(),
                                    ]),
                                    Textarea::make('receipt_note')
                                        ->label('Note')
                                        ->required()
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ]),
                    Tab::make('POS Settings')
                        ->schema([
                            Section::make('POS Settings')
                                ->schema([
                                    Grid::make(3)->schema([
                                        Toggle::make('pos_enable_click_sound')
                                            ->label('Enable POS click sound'),
                                        Toggle::make('pos_auto_refresh_products')
                                            ->label('Auto refresh products'),
                                        Toggle::make('pos_show_out_of_stock_products')
                                            ->label('Show out of stock products in POS'),
                                    ]),
                                    FileUpload::make('pos_sound')
                                        ->label('POS Sound')
                                        ->disk('public')
                                        ->directory('pos/sounds')
                                        ->acceptedFileTypes(['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4'])
                                        ->maxSize(5120)
                                        ->downloadable()
                                        ->openable()
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ]),
                    Tab::make('Currency Settings')
                        ->schema([
                            Section::make('Currency Settings')
                                ->schema([
                                    Grid::make(2)->schema([
                                        Select::make('currency_default')
                                            ->label('Default Currency')
                                            ->options($this->currencyOptions())
                                            ->required()
                                            ->live(),
                                        Select::make('currency_decimal_places')
                                            ->label('Decimal Places')
                                            ->options([
                                                0 => '0 (1234)',
                                                1 => '1 (1234.0)',
                                                2 => '2 (1234.00)',
                                                3 => '3 (1234.000)',
                                            ])
                                            ->required()
                                            ->live(),
                                        Select::make('currency_thousands_separator')
                                            ->label('Thousands Separator')
                                            ->options([
                                                ',' => 'Comma (1,234.00)',
                                                '.' => 'Dot (1.234,00)',
                                                ' ' => 'Space (1 234.00)',
                                                '' => 'None (1234.00)',
                                            ])
                                            ->live(),
                                        Select::make('currency_decimal_separator')
                                            ->label('Decimal Separator')
                                            ->options([
                                                '.' => 'Dot (1234.00)',
                                                ',' => 'Comma (1234,00)',
                                            ])
                                            ->required()
                                            ->live(),
                                    ]),
                                    Toggle::make('currency_symbol_right')
                                        ->label('Currency icon Right side')
                                        ->live(),
                                    Placeholder::make('currency_preview')
                                        ->label('Preview')
                                        ->content(fn (): string => $this->formatCurrencyPreview())
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ]),
                ])
                ->persistTabInQueryString()
                ->id('admin-settings-tabs')
                ->columnSpanFull(),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $this->saveSettings($state);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    public function sendTestEmail(array $data): void
    {
        $state = $this->form->getState();
        $this->saveSettings($state);
        AppSettings::configureMail($this->mailSettingsFromState($state));

        $recipients = $this->parseEmailRecipients((string) ($data['recipients'] ?? ''));

        if ($recipients === []) {
            Notification::make()
                ->title('Enter at least one email address')
                ->danger()
                ->send();

            return;
        }

        try {
            Mail::raw('This is a test email from Perfume POS mail settings.', function ($message) use ($recipients): void {
                $message->to($recipients)->subject('Perfume POS test email');
            });
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Test email failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Test email sent')
            ->body('Sent to '.implode(', ', $recipients).'.')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('settings-form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->key('settings-form-actions'),
            ]);
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('sendTestEmail')
                ->label('Send Test Email')
                ->modalHeading('Send test email')
                ->form([
                    TextInput::make('recipients')
                        ->label('Email addresses')
                        ->helperText('Separate multiple email addresses with commas.')
                        ->placeholder('name@example.com, accounts@example.com')
                        ->required(),
                ])
                ->action('sendTestEmail'),
        ];
    }

    private function settingsData(): array
    {
        return [
            ...$this->defaultMailSettings(),
            ...AppSetting::getValue('mail', []),
            ...$this->defaultReceiptSettings(),
            ...AppSetting::getValue('receipt', []),
            ...$this->defaultPosSettings(),
            ...AppSetting::getValue('pos', []),
            ...$this->defaultCurrencySettings(),
            ...AppSetting::getValue('currency', []),
        ];
    }

    private function saveSettings(array $state): void
    {
        AppSetting::setValue('mail', $this->mailSettingsFromState($state));
        AppSetting::setValue('receipt', $this->receiptSettingsFromState($state));
        AppSetting::setValue('pos', $this->posSettingsFromState($state));
        AppSetting::setValue('currency', $this->currencySettingsFromState($state));
    }

    private function mailSettingsFromState(array $state): array
    {
        return array_intersect_key($state, $this->defaultMailSettings());
    }

    private function receiptSettingsFromState(array $state): array
    {
        return array_intersect_key($state, $this->defaultReceiptSettings());
    }

    private function posSettingsFromState(array $state): array
    {
        return array_intersect_key($state, $this->defaultPosSettings());
    }

    private function currencySettingsFromState(array $state): array
    {
        return array_intersect_key($state, $this->defaultCurrencySettings());
    }

    private function defaultMailSettings(): array
    {
        return [
            'mail_mailer' => config('mail.default', 'smtp'),
            'mail_host' => config('mail.mailers.smtp.host', '127.0.0.1'),
            'mail_port' => config('mail.mailers.smtp.port', 2525),
            'mail_username' => config('mail.mailers.smtp.username'),
            'mail_password' => config('mail.mailers.smtp.password'),
            'mail_encryption' => config('mail.mailers.smtp.scheme'),
            'mail_from_address' => config('mail.from.address', 'hello@example.com'),
            'mail_from_name' => config('mail.from.name', config('app.name', 'Laravel')),
        ];
    }

    private function defaultReceiptSettings(): array
    {
        return [
            'receipt_show_note' => true,
            'receipt_show_phone' => true,
            'receipt_show_customer' => true,
            'receipt_show_address' => false,
            'receipt_show_email' => false,
            'receipt_show_discount_shipping' => true,
            'receipt_show_barcode' => true,
            'receipt_show_logo_payment_slip' => false,
            'receipt_show_product_code' => true,
            'receipt_show_tax' => false,
            'receipt_labels_font_style' => 'bold',
            'receipt_other_font_style' => 'normal',
            'receipt_paper_size' => 'thermal',
            'receipt_thermal_paper_size' => '80mm',
            'receipt_margin' => 0,
            'receipt_note' => 'Thanks for order',
        ];
    }

    private function defaultPosSettings(): array
    {
        return [
            'pos_enable_click_sound' => false,
            'pos_auto_refresh_products' => false,
            'pos_show_out_of_stock_products' => false,
            'pos_sound' => null,
        ];
    }

    private function defaultCurrencySettings(): array
    {
        return [
            'currency_default' => 'GBP',
            'currency_decimal_places' => 2,
            'currency_thousands_separator' => ',',
            'currency_decimal_separator' => '.',
            'currency_symbol_right' => false,
        ];
    }

    private function fontStyleOptions(): array
    {
        return [
            'normal' => 'Normal',
            'bold' => 'Bold',
            'italic' => 'Italic',
        ];
    }

    private function currencyOptions(): array
    {
        return [
            'GBP' => '£',
            'USD' => '$',
            'EUR' => '€',
            'INR' => '₹',
            'AED' => 'د.إ',
        ];
    }

    private function formatCurrencyPreview(): string
    {
        $state = [
            ...$this->defaultCurrencySettings(),
            ...($this->data ?? []),
        ];

        $symbol = $this->currencyOptions()[$state['currency_default']] ?? $state['currency_default'];
        $amount = number_format(
            12345.67,
            (int) $state['currency_decimal_places'],
            (string) $state['currency_decimal_separator'],
            (string) $state['currency_thousands_separator'],
        );

        return $state['currency_symbol_right'] ? "{$amount} {$symbol}" : "{$symbol} {$amount}";
    }

    /**
     * @return array<int, string>
     */
    private function parseEmailRecipients(string $recipients): array
    {
        $emails = array_values(array_filter(array_map(
            static fn (string $email): string => trim($email),
            explode(',', $recipients),
        )));

        $invalidEmails = array_filter(
            $emails,
            static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) === false,
        );

        if ($invalidEmails !== []) {
            Notification::make()
                ->title('Invalid email address')
                ->body(implode(', ', $invalidEmails))
                ->danger()
                ->send();

            return [];
        }

        return array_values(array_unique($emails));
    }

}
