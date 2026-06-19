<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\InvoiceStatus;
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
use App\Models\TaxRate;
use App\Models\Voucher;
use App\Services\Accounting\SalesPostingService;
use App\Services\Accounting\VoucherPostingService;
use App\Services\Settings\AppSettings;
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

    protected static string|UnitEnum|null $navigationGroup = 'POS / Sales';

    protected static ?int $navigationSort = 1;

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

    public string $paymentNote = '';

    public string $paymentStatus = 'paid';

    public ?string $quickModal = null;

    public bool $showCustomerModal = false;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerEmail = '';

    public string $customerAddress = '';

    public string $customerCity = '';

    public string $customerPostcode = '';

    public string $customerCountry = 'UK';

    protected array $extraBodyAttributes = [
        'class' => 'pos-body',
    ];

    public function mount(): void
    {
        $this->selectedCompanyId = auth()->user()?->company_id
            ?? $this->companies()->first()?->id;
        $this->taxRateId = TaxRate::defaultId();
        $this->taxRate = (string) TaxRate::rateFor($this->taxRateId);
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
        $this->selectedBankAccountId = $this->activeBankAccounts()->first()?->id;
    }

    public function updatedCustomerSearch(): void
    {
        if ($this->selectedCustomerId && trim($this->customerSearch) !== $this->selectedCustomerName()) {
            $this->selectedCustomerId = null;
        }
    }

    public function selectCustomer(?int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->customerSearch = $this->selectedCustomerName();
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function selectBrand(?int $brandId): void
    {
        $this->brandId = $brandId;
    }

    public function updatedSearch(): void
    {
        if (trim($this->search) === '') {
            return;
        }

        $products = $this->filteredProductQuery()
            ->limit(2)
            ->get(['id']);

        if ($products->count() !== 1) {
            return;
        }

        $this->addProduct((int) $products->first()->id, true);
    }

    public function addProduct(int $productId, bool $clearSearch = false): void
    {
        $product = $this->baseProductQuery()
            ->whereKey($productId)
            ->first();

        if (! $product) {
            Notification::make()
                ->title('Product is not available')
                ->danger()
                ->send();

            $this->dispatch('pos-focus-search');

            return;
        }

        if (! isset($this->cart[$productId])) {
            $this->cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->item_code,
                'barcode' => $product->barcode,
                'price' => (float) $product->sale_price,
                'qty' => 0,
                'stock' => (float) $product->current_stock,
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

        $companyId = $this->selectedCompanyId ?? auth()->user()?->company_id;

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

        if (! $this->selectedCustomerId) {
            Notification::make()
                ->title('Select a customer before creating the invoice')
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

        if ($this->paidAmountForReceipt() > 0 && ! $this->selectedBankAccountId) {
            Notification::make()
                ->title('Select a bank account for the payment')
                ->warning()
                ->send();

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
        return $this->filteredProductQuery()
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

    public function activeBankAccounts(): Collection
    {
        return $this->companyQuery(BankAccount::withoutGlobalScopes())
            ->where('status', Status::Active->value)
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'bank_name', 'opening_balance']);
    }

    public function taxRates(): Collection
    {
        return TaxRate::query()->orderBy('id')->get(['id', 'name', 'rate']);
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
        return $this->selectedTaxRate() * max(0, $this->subtotal() - $this->discountAmount()) / 100;
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

    public function selectedBankBalance(): ?float
    {
        $bankAccount = BankAccount::query()->find($this->selectedBankAccountId);

        return $bankAccount?->currentBalance();
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
            ->with(['category:id,name', 'brand:id,name', 'media'])
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

    private function companyQuery(Builder $query): Builder
    {
        $companyId = $this->selectedCompanyId ?? auth()->user()?->company_id;

        return $query->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId));
    }

    private function heldSalesSessionKey(): string
    {
        return 'pos_held_sales.'.auth()->id().'.'.($this->selectedCompanyId ?? 'none').'.'.today()->toDateString();
    }

    private function createSalesInvoice(): SalesInvoice
    {
        $companyId = $this->selectedCompanyId ?? auth()->user()?->company_id;

        return DB::transaction(function () use ($companyId): SalesInvoice {
            $customer = Customer::withoutGlobalScopes()
                ->whereKey($this->selectedCustomerId)
                ->where('company_id', $companyId)
                ->firstOrFail();

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
                'payment_method_id' => $this->paymentStatus === 'unpaid' ? null : $this->paymentMethodId,
                'payment_note' => $this->paymentNote ?: null,
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
                    'vat_rate' => 0,
                    'tax_rate_id' => TaxRate::idForRate(0),
                    'vat_amount' => 0,
                    'line_total' => $this->shippingAmount(),
                ]);
            }

            $invoice->load(['company', 'customer', 'items.productItem']);

            app(SalesPostingService::class)->post($invoice);
            $invoice->refresh();

            $paidAmount = $this->paidAmountForReceipt();

            if ($paidAmount > 0) {
                $voucher = Voucher::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'voucher_type' => VoucherType::Receipt,
                    'voucher_date' => today(),
                    'bank_account_id' => $this->selectedBankAccountId,
                    'customer_id' => $customer->id,
                    'amount' => $paidAmount,
                    'reference_no' => $invoice->invoice_no,
                    'notes' => $this->paymentNote ?: 'POS sale receipt',
                    'status' => VoucherStatus::Draft,
                    'created_by' => auth()->id(),
                ]);

                $voucher->allocations()->create([
                    'sales_invoice_id' => $invoice->id,
                    'amount' => $paidAmount,
                ]);

                app(VoucherPostingService::class)->post($voucher);
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
        if ($this->paymentStatus === 'unpaid') {
            return 0.0;
        }

        return round(min(max(0, (float) $this->paymentAmount), $this->total()), 2);
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
