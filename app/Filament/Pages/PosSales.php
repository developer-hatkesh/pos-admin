<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Status;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\ProductItem;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class PosSales extends Page
{
    protected static string $layout = 'filament-panels::components.layout.simple';
    protected static ?string $title = 'POS Sales';
    protected static ?string $slug = 'pos-sales';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;
    protected static string|UnitEnum|null $navigationGroup = 'POS / Sales';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.pos-sales';
    protected Width|string|null $maxContentWidth = Width::Full;

    public string $search = '';
    public ?int $selectedCompanyId = null;
    public ?int $categoryId = null;
    public ?int $brandId = null;
    public array $cart = [];
    public string $taxRate = '0';
    public string $discount = '0';
    public string $discountType = 'fixed';
    public string $shipping = '0';
    public bool $showPaymentModal = false;
    public string $paymentAmount = '0';
    public ?int $paymentMethodId = null;
    public string $paymentNote = '';
    public string $paymentStatus = 'paid';

    protected array $extraBodyAttributes = [
        'class' => 'pos-body',
    ];

    public function mount(): void
    {
        $this->selectedCompanyId = auth()->user()?->company_id
            ?? $this->companies()->first()?->id;
    }

    protected function getLayoutData(): array
    {
        return [
            'hasTopbar' => false,
        ];
    }

    public function updatedSelectedCompanyId(): void
    {
        $this->selectedCompanyId = filled($this->selectedCompanyId) ? (int) $this->selectedCompanyId : null;
        $this->categoryId = null;
        $this->brandId = null;
        $this->cart = [];
        $this->paymentMethodId = null;
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function selectBrand(?int $brandId): void
    {
        $this->brandId = $brandId;
    }

    public function addProduct(int $productId): void
    {
        $product = $this->baseProductQuery()
            ->whereKey($productId)
            ->first();

        if (! $product) {
            Notification::make()
                ->title('Product is not available')
                ->danger()
                ->send();

            return;
        }

        if (! isset($this->cart[$productId])) {
            $this->cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->item_code,
                'price' => (float) $product->sale_price,
                'qty' => 0,
                'stock' => (float) $product->opening_stock,
            ];
        }

        $this->cart[$productId]['qty']++;
    }

    public function incrementItem(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['qty']++;
    }

    public function decrementItem(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['qty']--;

        if ($this->cart[$productId]['qty'] <= 0) {
            unset($this->cart[$productId]);
        }
    }

    public function removeItem(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    public function resetCart(): void
    {
        $this->cart = [];
        $this->taxRate = '0';
        $this->discount = '0';
        $this->discountType = 'fixed';
        $this->shipping = '0';
    }

    public function holdSale(): void
    {
        Notification::make()
            ->title('Hold sale is ready for the next workflow step')
            ->info()
            ->send();
    }

    public function payNow(): void
    {
        if ($this->totalQty() === 0) {
            Notification::make()
                ->title('Add at least one product before payment')
                ->warning()
                ->send();

            return;
        }

        $this->openPaymentModal();
    }

    public function openPaymentModal(): void
    {
        $this->paymentAmount = number_format($this->total(), 2, '.', '');
        $this->paymentMethodId = $this->paymentMethodId ?? $this->activePaymentMethods()->first()?->id;
        $this->paymentStatus = 'paid';
        $this->paymentNote = '';
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
    }

    public function submitPayment(bool $print = false): void
    {
        if ($this->totalQty() === 0) {
            $this->closePaymentModal();

            Notification::make()
                ->title('Add at least one product before payment')
                ->warning()
                ->send();

            return;
        }

        if (! $this->paymentMethodId && $this->paymentStatus !== 'unpaid') {
            Notification::make()
                ->title('Select a payment type')
                ->warning()
                ->send();

            return;
        }

        $this->closePaymentModal();

        Notification::make()
            ->title($print ? 'Payment submitted and ready to print' : 'Payment submitted')
            ->success()
            ->send();
    }

    public function products(): Collection
    {
        return $this->baseProductQuery()
            ->when($this->categoryId, fn (Builder $query): Builder => $query->where('category_id', $this->categoryId))
            ->when($this->brandId, fn (Builder $query): Builder => $query->where('brand_id', $this->brandId))
            ->when(trim($this->search) !== '', function (Builder $query): Builder {
                $search = trim($this->search);

                return $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('brand', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->limit(80)
            ->get();
    }

    public function categories(): Collection
    {
        return $this->companyQuery(Category::withoutGlobalScopes())
            ->where('status', Status::Active->value)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function brands(): Collection
    {
        return $this->companyQuery(Brand::withoutGlobalScopes())
            ->where('status', Status::Active->value)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function activePaymentMethods(): Collection
    {
        return $this->companyQuery(PaymentMethod::withoutGlobalScopes())
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function companies(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return Company::query()->orderBy('name')->get(['id', 'name']);
        }

        return Company::query()
            ->whereKey($user->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function subtotal(): float
    {
        return collect($this->cart)->sum(fn (array $item): float => $item['qty'] * $item['price']);
    }

    public function totalQty(): int
    {
        return (int) collect($this->cart)->sum('qty');
    }

    public function discountAmount(): float
    {
        $discount = max(0, (float) $this->discount);

        if ($this->discountType === 'percentage') {
            return $this->subtotal() * min($discount, 100) / 100;
        }

        return min($discount, $this->subtotal());
    }

    public function taxAmount(): float
    {
        return max(0, (float) $this->taxRate) * max(0, $this->subtotal() - $this->discountAmount()) / 100;
    }

    public function shippingAmount(): float
    {
        return max(0, (float) $this->shipping);
    }

    public function total(): float
    {
        return max(0, $this->subtotal() - $this->discountAmount() + $this->taxAmount() + $this->shippingAmount());
    }

    public function changeReturn(): float
    {
        if ($this->paymentStatus !== 'paid') {
            return 0;
        }

        return max(0, (float) $this->paymentAmount - $this->total());
    }

    private function baseProductQuery(): Builder
    {
        return $this->companyQuery(ProductItem::withoutGlobalScopes())
            ->with(['category:id,name', 'brand:id,name'])
            ->where('status', Status::Active->value);
    }

    private function companyQuery(Builder $query): Builder
    {
        $companyId = $this->selectedCompanyId ?? auth()->user()?->company_id;

        return $query->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId));
    }
}
