<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\InvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Enums\Status;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\BankAccount;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductItem;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\TaxRate;
use App\Models\Voucher;
use App\Models\VoucherAllocation;
use App\Services\Accounting\SalesPostingService;
use App\Services\Accounting\VoucherPostingService;
use App\Services\Settings\AppSettings;
use App\Support\CurrentCompany;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class PosSales extends Page
{
    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static ?string $title = 'POS Sales';

    protected static ?string $slug = 'pos-sales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'POS Sale';

    protected string $view = 'filament.pages.pos-sales';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $search = '';

    public ?int $selectedCompanyId = null;

    public ?int $selectedCustomerId = null;

    public string $customerSearch = '';

    public ?int $categoryId = null;

    public ?int $brandId = null;

    public array $cart = [];

    public string $taxRate = '0';

    public ?int $taxRateId = null;

    public string $discount = '0';

    public string $discountType = 'fixed';

    public string $shipping = '0';

    public bool $showPaymentModal = false;

    public string $paymentAmount = '0';

    public ?int $paymentMethodId = null;

    public ?int $selectedBankAccountId = null;

    public array $paymentSplits = [];

    public string $paymentNote = '';

    public string $paymentStatus = 'paid';

    public ?string $paymentError = null;

    public ?string $quickModal = null;

    public bool $showCustomerModal = false;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerEmail = '';

    public string $customerAddress = '';

    public string $customerCity = '';

    public string $customerPostcode = '';

    public string $customerCountry = 'UK';

    public array $productAddCache = [];

    public array $productOptions = [];

    public array $categoryOptions = [];

    public array $brandOptions = [];

    public array $taxRateOptions = [];

    public array $paymentMethodOptions = [];

    public array $bankAccountOptions = [];

    protected array $extraBodyAttributes = [
        'class' => 'pos-body',
    ];

    public function mount(): void
    {
        $this->selectedCompanyId = app(CurrentCompany::class)->id()
            ?? $this->companies()->first()?->id;
        $this->taxRateId = TaxRate::defaultId();
        $this->taxRate = (string) TaxRate::rateFor($this->taxRateId);
        $this->loadPosReferenceData();
        $this->loadProductOptions();
        $this->selectedBankAccountId = $this->activeBankAccounts()->first()?->id;
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
        $this->selectedCustomerId = null;
        $this->customerSearch = '';
        $this->cart = [];
        $this->paymentMethodId = null;
        $this->loadPosReferenceData();
        $this->loadProductOptions();
        $this->selectedBankAccountId = $this->activeBankAccounts()->first()?->id;
    }

    public function updatedCustomerSearch(): void
    {
        if ($this->selectedCustomerId && trim($this->customerSearch) !== $this->selectedCustomerName()) {
            $this->selectedCustomerId = null;
        }
    }

    public function updatedPaymentAmount(): void
    {
        if (count($this->paymentSplits) === 1) {
            $this->paymentSplits[0]['amount'] = $this->paymentAmount;
        }
    }

    public function updatedPaymentMethodId(): void
    {
        if (count($this->paymentSplits) === 1) {
            $this->paymentSplits[0]['payment_method_id'] = $this->paymentMethodId;
        }
    }

    public function updatedSelectedBankAccountId(): void
    {
        if (count($this->paymentSplits) === 1) {
            $this->paymentSplits[0]['bank_account_id'] = $this->selectedBankAccountId;
        }
    }

    public function updatedPaymentStatus(): void
    {
        if (count($this->paymentSplits) !== 1) {
            return;
        }

        if ($this->paymentStatus === 'unpaid') {
            $this->paymentSplits[0]['payment_method_id'] = 'due';
            $this->paymentSplits[0]['amount'] = number_format($this->total(), 2, '.', '');
            $this->paymentSplits[0]['bank_account_id'] = null;
        }
    }

    public function updatedPaymentSplits(): void
    {
        foreach ($this->paymentSplits as $index => $split) {
            if (($split['payment_method_id'] ?? null) === 'due') {
                $this->paymentSplits[$index]['bank_account_id'] = null;
            }
        }
    }

    public function selectCustomer(?int $customerId): void
    {
        $this->selectedCustomerId = $customerId;

        if (! $customerId) {
            $this->customerSearch = '';

            return;
        }

        $customer = $this->companyQuery(Customer::withoutGlobalScopes())
            ->whereKey($customerId)
            ->first(['id', 'name', 'discount_percent']);

        if (! $customer) {
            $this->selectedCustomerId = null;
            $this->customerSearch = '';

            return;
        }

        $this->customerSearch = $customer->name;
        $this->applyCustomerDiscount((float) $customer->discount_percent);
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->loadProductOptions();
    }

    public function selectBrand(?int $brandId): void
    {
        $this->brandId = $brandId;
        $this->loadProductOptions();
    }

    public function updatedSearch(): void
    {
        $search = trim($this->search);

        if ($search === '') {
            $this->loadProductOptions();

            return;
        }

        $cachedProduct = collect($this->productAddCache)
            ->first(fn (array $product): bool => in_array($search, array_filter([
                $product['barcode'] ?? null,
                $product['sku'] ?? null,
                $product['item_code'] ?? null,
            ]), true));

        if (is_array($cachedProduct)) {
            $this->addProduct((int) $cachedProduct['id'], true);

            return;
        }

        $exactProducts = $this->exactProductLookupQuery($search)
            ->limit(2)
            ->get(['id']);

        if ($exactProducts->count() === 1) {
            $this->addProduct((int) $exactProducts->first()->id, true);

            return;
        }

        $this->loadProductOptions();
    }

    public function addProduct(int $productId, bool $clearSearch = false): void
    {
        $product = $this->productAddCache[$productId] ?? null;

        if (! $product) {
            $product = $this->productLookupQuery()
                ->whereKey($productId)
                ->first();
        }

        if (! $product) {
            $this->dispatch('pos-focus-search');

            return;
        }

        if (! isset($this->cart[$productId])) {
            $this->cart[$productId] = [
                'id' => (int) data_get($product, 'id'),
                'name' => (string) data_get($product, 'name'),
                'code' => data_get($product, 'item_code'),
                'barcode' => data_get($product, 'barcode'),
                'price' => (float) data_get($product, 'sale_price'),
                'qty' => 0,
            ];
        }

        $this->cart[$productId]['qty']++;

        if ($clearSearch) {
            $this->search = '';
        }

        $this->dispatch('pos-focus-search');
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
            $this->dispatch('pos-focus-search');
        }
    }

    public function updatedCart(mixed $value, string $key): void
    {
        if (! str_ends_with($key, '.qty')) {
            return;
        }

        $productId = (int) str($key)->before('.')->toString();

        if (! isset($this->cart[$productId])) {
            return;
        }

        $qty = max(0, (float) $value);

        if ($qty <= 0) {
            unset($this->cart[$productId]);
            $this->dispatch('pos-focus-search');

            return;
        }

        $this->cart[$productId]['qty'] = $qty;
    }

    public function removeItem(int $productId): void
    {
        unset($this->cart[$productId]);
        $this->dispatch('pos-focus-search');
    }

    public function resetCart(): void
    {
        $this->cart = [];
        $this->taxRateId = TaxRate::defaultId();
        $this->taxRate = (string) TaxRate::rateFor($this->taxRateId);
        $this->discount = '0';
        $this->discountType = 'fixed';
        $this->shipping = '0';
    }

    public function updatedTaxRateId(): void
    {
        $this->taxRate = (string) TaxRate::rateFor($this->taxRateId);
    }

    public function holdSale(): void
    {
        if ($this->totalQty() === 0) {
            Notification::make()
                ->title('Add at least one product before holding the sale')
                ->warning()
                ->send();

            return;
        }

        $heldSales = session()->get($this->heldSalesSessionKey(), []);
        $heldSales[] = [
            'reference' => 'HOLD-'.now()->format('His'),
            'created_at' => now()->format('H:i'),
            'user' => auth()->user()?->name,
            'items' => $this->cart,
            'qty' => $this->totalQty(),
            'total' => $this->total(),
        ];

        session()->put($this->heldSalesSessionKey(), $heldSales);
        $this->resetCart();

        Notification::make()
            ->title('Sale moved to hold list')
            ->success()
            ->send();
    }

    public function openQuickModal(string $modal): void
    {
        $this->quickModal = $modal;
    }

    public function closeQuickModal(): void
    {
        $this->quickModal = null;
    }

    public function openCustomerModal(): void
    {
        $this->resetCustomerForm();
        $this->showCustomerModal = true;
    }

    public function closeCustomerModal(): void
    {
        $this->showCustomerModal = false;
    }

    public function saveCustomer(): void
    {
        $validated = $this->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:255'],
            'customerEmail' => ['nullable', 'email', 'max:255'],
            'customerAddress' => ['nullable', 'string', 'max:255'],
            'customerCity' => ['nullable', 'string', 'max:255'],
            'customerPostcode' => ['nullable', 'string', 'max:255'],
            'customerCountry' => ['nullable', 'string', 'max:255'],
        ], [], [
            'customerName' => 'name',
            'customerPhone' => 'phone',
            'customerEmail' => 'email',
            'customerAddress' => 'address',
            'customerCity' => 'city',
            'customerPostcode' => 'postcode',
            'customerCountry' => 'country',
        ]);

        $companyId = $this->selectedCompanyId ?? app(CurrentCompany::class)->id();

        if (! $companyId) {
            Notification::make()
                ->title('Select a company before adding a customer')
                ->warning()
                ->send();

            return;
        }

        $customer = Customer::query()->create([
            'company_id' => $companyId,
            'name' => $validated['customerName'],
            'phone' => $validated['customerPhone'] ?: null,
            'email' => $validated['customerEmail'] ?: null,
            'address_line1' => $validated['customerAddress'] ?: null,
            'city' => $validated['customerCity'] ?: null,
            'postcode' => $validated['customerPostcode'] ?: null,
            'country' => $validated['customerCountry'] ?: null,
            'status' => Status::Active,
        ]);

        $this->selectedCustomerId = $customer->id;
        $this->customerSearch = $customer->name;
        $this->applyCustomerDiscount();
        $this->showCustomerModal = false;
        $this->resetCustomerForm();

        Notification::make()
            ->title('Customer added')
            ->success()
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
        $this->selectedBankAccountId = $this->selectedBankAccountId ?? $this->activeBankAccounts()->first()?->id;
        $this->paymentSplits = [[
            'amount' => $this->paymentAmount,
            'payment_method_id' => $this->paymentMethodId,
            'bank_account_id' => $this->selectedBankAccountId,
        ]];
        $this->paymentStatus = 'paid';
        $this->paymentNote = '';
        $this->paymentError = null;
        $this->showPaymentModal = true;
    }

    public function addPaymentSplit(): void
    {
        $this->paymentSplits[] = [
            'amount' => '0',
            'payment_method_id' => $this->activePaymentMethods()->first()?->id,
            'bank_account_id' => $this->activeBankAccounts()->first()?->id,
        ];
    }

    public function removePaymentSplit(int $index): void
    {
        unset($this->paymentSplits[$index]);
        $this->paymentSplits = array_values($this->paymentSplits);

        if ($this->paymentSplits === []) {
            $this->addPaymentSplit();
        }
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->paymentError = null;
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

        if (! $this->selectedCustomerId) {
            $this->paymentError = 'Select a customer before creating the invoice.';

            return;
        }

        $paymentSplits = $this->normalizedPaymentSplits();

        if ($paymentSplits === []) {
            $this->paymentError = 'Enter at least one payment or credit/due row.';

            return;
        }

        $requiresAccountSelection = $this->activeBankAccounts()->isNotEmpty();

        foreach ($paymentSplits as $split) {
            if ($split['is_due']) {
                continue;
            }

            if (! $split['payment_method_id']) {
                $this->paymentError = 'Select a payment type for every paid row.';

                return;
            }

            if ($requiresAccountSelection && ! $split['bank_account_id']) {
                $this->paymentError = 'Select an account for every paid row.';

                return;
            }
        }

        if ($this->splitPaidAmount() <= 0 && $this->splitDueAmount() <= 0) {
            $this->paymentError = 'Enter a payment amount or credit/due amount.';

            return;
        }

        $invoice = $this->createSalesInvoice();
        $this->closePaymentModal();
        $this->resetCart();

        Notification::make()
            ->title('Sales invoice '.$invoice->invoice_no.' created')
            ->success()
            ->send();

        if ($print) {
            $this->redirectRoute('pos.sales-invoices.print', ['salesInvoice' => $invoice->id]);
        }
    }

    public function products(): Collection
    {
        return collect($this->productOptions)
            ->map(function (array $product): object {
                $product['brand'] = filled($product['brand_name'] ?? null) ? (object) ['name' => $product['brand_name']] : null;
                $product['category'] = filled($product['category_name'] ?? null) ? (object) ['name' => $product['category_name']] : null;

                return (object) $product;
            });
    }

    public function categories(): Collection
    {
        return collect($this->categoryOptions)->map(fn (array $category): object => (object) $category);
    }

    public function brands(): Collection
    {
        return collect($this->brandOptions)->map(fn (array $brand): object => (object) $brand);
    }

    public function activePaymentMethods(): Collection
    {
        return collect($this->paymentMethodOptions)->map(fn (array $paymentMethod): object => (object) $paymentMethod);
    }

    public function activeBankAccounts(): Collection
    {
        return collect($this->bankAccountOptions)->map(fn (array $bankAccount): object => (object) $bankAccount);
    }

    public function taxRates(): Collection
    {
        return collect($this->taxRateOptions)->map(fn (array $taxRate): object => (object) $taxRate);
    }

    public function customers(): Collection
    {
        return $this->companyQuery(Customer::withoutGlobalScopes())
            ->where('status', Status::Active->value)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function filteredCustomers(): Collection
    {
        $search = trim($this->customerSearch);

        return $this->companyQuery(Customer::withoutGlobalScopes())
            ->where('status', Status::Active->value)
            ->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name']);
    }

    public function selectedCustomerName(): string
    {
        if (! $this->selectedCustomerId) {
            return '';
        }

        return (string) $this->companyQuery(Customer::withoutGlobalScopes())
            ->whereKey($this->selectedCustomerId)
            ->value('name');
    }

    private function applyCustomerDiscount(?float $discountPercent = null): void
    {
        if (! $this->selectedCustomerId) {
            return;
        }

        $discountPercent ??= (float) $this->companyQuery(Customer::withoutGlobalScopes())
            ->whereKey($this->selectedCustomerId)
            ->value('discount_percent');

        if ($discountPercent <= 0) {
            return;
        }

        $this->discountType = 'percentage';
        $this->discount = number_format($discountPercent, 2, '.', '');
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

    public function storeName(): string
    {
        return AppSettings::storeBrandName();
    }

    private function loadPosReferenceData(): void
    {
        $this->categoryOptions = $this->companyQuery(Category::withoutGlobalScopes())
            ->where('status', Status::Active->value)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();

        $this->brandOptions = $this->companyQuery(Brand::withoutGlobalScopes())
            ->where('status', Status::Active->value)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
            ])
            ->all();

        $this->taxRateOptions = TaxRate::query()
            ->orderBy('id')
            ->get(['id', 'name', 'rate'])
            ->map(fn (TaxRate $taxRate): array => [
                'id' => $taxRate->id,
                'name' => $taxRate->name,
                'rate' => $taxRate->rate,
            ])
            ->all();

        $this->paymentMethodOptions = $this->companyQuery(PaymentMethod::withoutGlobalScopes())
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (PaymentMethod $paymentMethod): array => [
                'id' => $paymentMethod->id,
                'name' => $paymentMethod->name,
            ])
            ->all();

        $this->bankAccountOptions = $this->companyQuery(BankAccount::withoutGlobalScopes())
            ->where('status', Status::Active->value)
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'bank_name', 'opening_balance'])
            ->map(fn (BankAccount $bankAccount): array => [
                'id' => $bankAccount->id,
                'account_name' => $bankAccount->account_name,
                'bank_name' => $bankAccount->bank_name,
                'opening_balance' => $bankAccount->opening_balance,
            ])
            ->all();
    }

    public function heldSales(): array
    {
        return array_reverse(session()->get($this->heldSalesSessionKey(), []));
    }

    public function recentSales(): Collection
    {
        return $this->companyQuery(SalesInvoice::withoutGlobalScopes())
            ->with(['customer:id,name', 'paymentMethod:id,name'])
            ->whereDate('invoice_date', today())
            ->latest()
            ->limit(10)
            ->get(['id', 'invoice_no', 'customer_id', 'payment_method_id', 'invoice_date', 'subtotal', 'vat_total', 'discount', 'total', 'status', 'created_at']);
    }

    public function salePaidAmount(SalesInvoice $sale): float
    {
        return round((float) VoucherAllocation::query()
            ->where('sales_invoice_id', $sale->id)
            ->whereHas('voucher', fn (Builder $query): Builder => $query->where('status', VoucherStatus::Posted->value))
            ->sum('amount'), 2);
    }

    public function saleReturnedAmount(SalesInvoice $sale): float
    {
        return round((float) SalesReturn::withoutGlobalScopes()
            ->where('sales_invoice_id', $sale->id)
            ->where('status', SalesReturnStatus::Posted->value)
            ->sum('total'), 2);
    }

    public function saleDueAmount(SalesInvoice $sale): float
    {
        return round(max(0, (float) $sale->total - $this->salePaidAmount($sale) - $this->saleReturnedAmount($sale)), 2);
    }

    public function registerDetails(): array
    {
        $sales = $this->companyQuery(SalesInvoice::withoutGlobalScopes())
            ->whereDate('invoice_date', today());

        return [
            'user' => auth()->user()?->name ?: 'n/a',
            'company' => Company::query()->find($this->selectedCompanyId)?->name ?: 'n/a',
            'date' => today()->format('d M Y'),
            'open_cart_qty' => $this->totalQty(),
            'open_cart_total' => $this->total(),
            'held_count' => count($this->heldSales()),
            'sales_count' => (clone $sales)->count(),
            'sales_total' => (float) (clone $sales)->sum('total'),
        ];
    }

    public function subtotal(): float
    {
        return collect($this->cart)->sum(fn (array $item): float => max(0, (float) $item['qty']) * max(0, (float) $item['price']));
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
        return round($this->selectedTaxRate() * $this->taxableBase() / 100, 2);
    }

    public function shippingAmount(): float
    {
        return max(0, (float) $this->shipping);
    }

    public function total(): float
    {
        return round(max(0, $this->taxableBase() + $this->taxAmount()), 2);
    }

    public function changeReturn(): float
    {
        return max(0, $this->splitPaidAmount() - $this->total());
    }

    public function selectedBankBalance(?int $bankAccountId = null): ?float
    {
        $bankAccount = BankAccount::query()->find($bankAccountId ?? $this->selectedBankAccountId);

        return $bankAccount?->currentBalance();
    }

    public function splitPaidAmount(): float
    {
        return round(collect($this->normalizedPaymentSplits())
            ->reject(fn (array $split): bool => $split['is_due'])
            ->sum('amount'), 2);
    }

    public function splitDueAmount(): float
    {
        $explicitDue = round(collect($this->normalizedPaymentSplits())
            ->filter(fn (array $split): bool => $split['is_due'])
            ->sum('amount'), 2);
        $remaining = round(max(0, $this->total() - $this->splitPaidAmount()), 2);

        return max($explicitDue, $remaining);
    }

    public function splitTotalEntered(): float
    {
        return round(collect($this->normalizedPaymentSplits())->sum('amount'), 2);
    }

    public function requiresBankAccountForPayment(): bool
    {
        return $this->paidAmountForReceipt() > 0 && ! $this->isCashPaymentMethod();
    }

    public function selectedTaxRate(): float
    {
        $rate = TaxRate::rateFor($this->taxRateId);

        if ($this->taxRateId !== null) {
            return max(0, $rate);
        }

        return max(0, (float) $this->taxRate);
    }

    private function baseProductQuery(): Builder
    {
        return $this->companyQuery(ProductItem::withoutGlobalScopes())
            ->where(function (Builder $query): void {
                $query->where('product_type', '!=', 'variation')
                    ->orWhereNotNull('variation_type_id');
            })
            ->where('status', Status::Active->value);
    }

    private function filteredProductQuery(): Builder
    {
        return $this->baseProductQuery()
            ->when($this->categoryId, fn (Builder $query): Builder => $query->where('category_id', $this->categoryId))
            ->when($this->brandId, fn (Builder $query): Builder => $query->where('brand_id', $this->brandId))
            ->when(trim($this->search) !== '', function (Builder $query): Builder {
                $search = trim($this->search);

                return $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('brand', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"));
                });
            });
    }

    private function productCardQuery(): Builder
    {
        return $this->filteredProductQuery()
            ->with(['category:id,name', 'brand:id,name'])
            ->select('product_items.*');
    }

    private function productLookupQuery(): Builder
    {
        return $this->baseProductQuery()
            ->select([
                'product_items.id',
                'product_items.company_id',
                'product_items.item_code',
                'product_items.sku',
                'product_items.barcode',
                'product_items.name',
                'product_items.sale_price',
            ]);
    }

    private function exactProductLookupQuery(string $search): Builder
    {
        return $this->baseProductQuery()
            ->where(function (Builder $query) use ($search): void {
                $query->where('barcode', $search)
                    ->orWhere('sku', $search)
                    ->orWhere('item_code', $search);
            });
    }

    private function loadProductOptions(): void
    {
        $products = $this->productCardQuery()
            ->orderBy('name')
            ->limit(80)
            ->get();

        $this->productOptions = $products
            ->map(fn (ProductItem $product): array => [
                'id' => $product->id,
                'item_code' => $product->item_code,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'sale_price' => $product->sale_price,
                'first_product_image_url' => $product->first_product_image_url,
                'brand_name' => $product->brand?->name,
                'category_name' => $product->category?->name,
            ])
            ->all();

        $this->productAddCache = collect($this->productOptions)
            ->mapWithKeys(fn (array $product): array => [
                $product['id'] => [
                    'id' => $product['id'],
                    'item_code' => $product['item_code'],
                    'sku' => $product['sku'],
                    'barcode' => $product['barcode'],
                    'name' => $product['name'],
                    'sale_price' => $product['sale_price'],
                ],
            ])
            ->all();
    }

    private function companyQuery(Builder $query): Builder
    {
        $companyId = $this->selectedCompanyId ?? app(CurrentCompany::class)->id();

        return $query->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId));
    }

    private function heldSalesSessionKey(): string
    {
        return 'pos_held_sales.'.auth()->id().'.'.($this->selectedCompanyId ?? 'none').'.'.today()->toDateString();
    }

    private function createSalesInvoice(): SalesInvoice
    {
        $companyId = $this->selectedCompanyId ?? app(CurrentCompany::class)->id();

        return DB::transaction(function () use ($companyId): SalesInvoice {
            $customer = Customer::withoutGlobalScopes()
                ->whereKey($this->selectedCustomerId)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $paymentSplits = $this->normalizedPaymentSplits();
            $paidAmount = $this->paidAmountForReceipt();

            $invoice = SalesInvoice::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'invoice_no' => $this->nextInvoiceNumber($companyId),
                'party_id' => null,
                'customer_id' => $customer->id,
                'invoice_date' => today(),
                'due_date' => null,
                'subtotal' => $this->subtotal() + $this->shippingAmount(),
                'discount' => $this->discountAmount(),
                'vat_total' => $this->taxAmount(),
                'total' => $this->total(),
                'status' => InvoiceStatus::Draft,
                'payment_method_id' => $this->primaryPaymentMethodId($paymentSplits),
                'payment_note' => $this->combinedPaymentNote($paymentSplits),
            ]);

            foreach ($this->cart as $item) {
                $lineNet = round(max(0, (float) $item['qty']) * max(0, (float) $item['price']), 2);

                $invoice->items()->create([
                    'product_item_id' => $item['id'],
                    'item_id' => null,
                    'description' => $item['name'],
                    'qty' => max(0, (float) $item['qty']),
                    'rate' => max(0, (float) $item['price']),
                    'vat_rate' => $this->selectedTaxRate(),
                    'tax_rate_id' => $this->taxRateId,
                    'vat_amount' => $this->subtotal() > 0 ? round($this->taxAmount() * ($lineNet / $this->subtotal()), 2) : 0,
                    'line_total' => $lineNet,
                ]);
            }

            if ($this->shippingAmount() > 0) {
                $invoice->items()->create([
                    'product_item_id' => null,
                    'item_id' => null,
                    'description' => 'Shipping',
                    'qty' => 1,
                    'rate' => $this->shippingAmount(),
                    'vat_rate' => $this->selectedTaxRate(),
                    'tax_rate_id' => $this->taxRateId,
                    'vat_amount' => round($this->shippingAmount() * ($this->selectedTaxRate() / 100), 2),
                    'line_total' => $this->shippingAmount(),
                ]);
            }

            $invoice->load(['company', 'customer', 'items.productItem']);

            app(SalesPostingService::class)->post($invoice);
            $invoice->refresh();

            $remainingReceiptAmount = $paidAmount;

            foreach ($paymentSplits as $split) {
                if ($split['is_due'] || $split['amount'] <= 0) {
                    continue;
                }

                $receiptAmount = round(min($split['amount'], $remainingReceiptAmount), 2);

                if ($receiptAmount <= 0) {
                    continue;
                }

                $voucher = Voucher::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'voucher_type' => VoucherType::Receipt,
                    'voucher_date' => today(),
                    'bank_account_id' => $split['bank_account_id'],
                    'customer_id' => $customer->id,
                    'amount' => $receiptAmount,
                    'reference_no' => $invoice->invoice_no,
                    'notes' => trim(($this->paymentNote ?: 'POS sale receipt').' - '.$split['label']),
                    'status' => VoucherStatus::Draft,
                    'created_by' => auth()->id(),
                ]);

                $voucher->allocations()->create([
                    'sales_invoice_id' => $invoice->id,
                    'amount' => $receiptAmount,
                ]);

                app(VoucherPostingService::class)->post($voucher);
                $remainingReceiptAmount = round(max(0, $remainingReceiptAmount - $receiptAmount), 2);
            }

            $invoice->update(['status' => $this->invoiceStatusForPaidAmount($paidAmount)]);

            return $invoice->load(['company', 'customer', 'items.productItem']);
        });
    }

    private function nextInvoiceNumber(int $companyId): string
    {
        $prefix = 'POS-'.today()->format('Ymd').'-';
        $latestInvoiceNo = SalesInvoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('invoice_no', 'like', $prefix.'%')
            ->orderByDesc('invoice_no')
            ->value('invoice_no');

        $nextNumber = $latestInvoiceNo ? ((int) substr($latestInvoiceNo, -4)) + 1 : 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function invoiceStatusForPaidAmount(float $paidAmount): InvoiceStatus
    {
        if ($paidAmount >= round($this->total(), 2) && $this->total() > 0) {
            return InvoiceStatus::Paid;
        }

        if ($paidAmount > 0) {
            return InvoiceStatus::Partial;
        }

        return InvoiceStatus::Posted;
    }

    private function paidAmountForReceipt(): float
    {
        return round(min(max(0, $this->splitPaidAmount()), $this->total()), 2);
    }

    private function normalizedPaymentSplits(): array
    {
        $splits = $this->paymentSplits;

        $defaultAmount = max(0, (float) $this->paymentAmount);

        if ($splits === [] && $defaultAmount <= 0 && $this->paymentStatus !== 'unpaid') {
            $defaultAmount = $this->total();
        }

        if ($splits === [] && ($defaultAmount > 0 || $this->paymentStatus === 'unpaid')) {
            $splits = [[
                'amount' => $this->paymentStatus === 'unpaid' ? $this->total() : $defaultAmount,
                'payment_method_id' => $this->paymentStatus === 'unpaid' ? 'due' : $this->paymentMethodId,
                'bank_account_id' => $this->selectedBankAccountId,
            ]];
        }

        return collect($splits)
            ->filter(fn (mixed $split): bool => is_array($split))
            ->map(function (array $split): array {
                $methodId = $split['payment_method_id'] ?? null;
                $isDue = $methodId === 'due';
                $amount = round(max(0, (float) ($split['amount'] ?? 0)), 2);
                $paymentMethodId = $isDue ? null : (filled($methodId) ? (int) $methodId : null);
                $methodName = $isDue ? 'Credit / Due' : $this->paymentMethodName($paymentMethodId);

                return [
                    'amount' => $amount,
                    'payment_method_id' => $paymentMethodId,
                    'bank_account_id' => $isDue ? null : (filled($split['bank_account_id'] ?? null) ? (int) $split['bank_account_id'] : null),
                    'is_due' => $isDue,
                    'label' => $methodName,
                ];
            })
            ->filter(fn (array $split): bool => $split['amount'] > 0)
            ->values()
            ->all();
    }

    private function paymentMethodName(?int $paymentMethodId): string
    {
        if (! $paymentMethodId) {
            return 'Payment';
        }

        return (string) $this->companyQuery(PaymentMethod::withoutGlobalScopes())
            ->whereKey($paymentMethodId)
            ->value('name') ?: 'Payment';
    }

    private function primaryPaymentMethodId(array $paymentSplits): ?int
    {
        $paidSplits = collect($paymentSplits)->reject(fn (array $split): bool => $split['is_due']);

        if ($paidSplits->count() !== 1) {
            return null;
        }

        return $paidSplits->first()['payment_method_id'];
    }

    private function combinedPaymentNote(array $paymentSplits): ?string
    {
        $lines = collect($paymentSplits)
            ->map(fn (array $split): string => $split['label'].': '.app_money($split['amount']))
            ->values()
            ->all();

        if ($this->paymentNote !== '') {
            array_unshift($lines, $this->paymentNote);
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function taxableBase(): float
    {
        return round(max(0, $this->subtotal() - $this->discountAmount()) + $this->shippingAmount(), 2);
    }

    private function isCashPaymentMethod(): bool
    {
        if (! $this->paymentMethodId) {
            return false;
        }

        $methodName = (string) $this->companyQuery(PaymentMethod::withoutGlobalScopes())
            ->whereKey($this->paymentMethodId)
            ->value('name');

        return strcasecmp(trim($methodName), 'cash') === 0;
    }

    private function resetCustomerForm(): void
    {
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerAddress = '';
        $this->customerCity = '';
        $this->customerPostcode = '';
        $this->customerCountry = 'UK';
    }
}
