<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Enums\InvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\BankAccount;
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
use App\Support\CurrentCompany;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class Cart extends Component
{
    public ?int $selectedCompanyId = null;

    public ?int $selectedCustomerId = null;

    public array $taxRateOptions = [];

    public array $paymentMethodOptions = [];

    public array $bankAccountOptions = [];

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

    public function mount(
        ?int $selectedCompanyId = null,
        ?int $selectedCustomerId = null,
        array $taxRateOptions = [],
        array $paymentMethodOptions = [],
        array $bankAccountOptions = [],
    ): void {
        $this->selectedCompanyId = $selectedCompanyId;
        $this->selectedCustomerId = $selectedCustomerId;
        $this->taxRateOptions = $taxRateOptions;
        $this->paymentMethodOptions = $paymentMethodOptions;
        $this->bankAccountOptions = $bankAccountOptions;
        $this->taxRateId = TaxRate::defaultId();
        $this->taxRate = (string) TaxRate::rateFor($this->taxRateId);
        $this->selectedBankAccountId = $this->activeBankAccounts()->first()?->id;
    }

    public function render(): mixed
    {
        return view('livewire.pos.cart');
    }

    #[On('pos-add-product')]
    public function addProduct(array $product): void
    {
        $productId = (int) ($product['id'] ?? 0);

        if ($productId <= 0) {
            $this->dispatch('pos-focus-search');

            return;
        }

        if (! isset($this->cart[$productId])) {
            $this->cart[$productId] = [
                'id' => $productId,
                'name' => (string) ($product['name'] ?? ''),
                'code' => $product['item_code'] ?? $product['code'] ?? null,
                'barcode' => $product['barcode'] ?? null,
                'price' => (float) ($product['sale_price'] ?? $product['price'] ?? 0),
                'qty' => 0,
            ];
        }

        $this->cart[$productId]['qty']++;
        $this->dispatch('pos-focus-search');
    }

    #[On('pos-customer-selected')]
    public function setSelectedCustomer(?int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->repriceCartForSelectedCustomer();
    }

    #[On('pos-apply-customer-discount')]
    public function applyCustomerDiscount(float $discountPercent): void
    {
        if ($discountPercent <= 0) {
            return;
        }

        $this->discountType = 'percentage';
        $this->discount = number_format($discountPercent, 2, '.', '');
    }

    #[On('pos-open-quick-modal')]
    public function openQuickModal(string $modal): void
    {
        $this->quickModal = $modal;
    }

    public function closeQuickModal(): void
    {
        $this->quickModal = null;
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
        $defaultBankAccountId = $this->activeBankAccounts()->first()?->id;

        foreach ($this->paymentSplits as $index => $split) {
            if (($split['payment_method_id'] ?? null) === 'due') {
                $this->paymentSplits[$index]['bank_account_id'] = null;

                continue;
            }

            if (blank($split['bank_account_id'] ?? null) && $defaultBankAccountId !== null) {
                $this->paymentSplits[$index]['bank_account_id'] = $defaultBankAccountId;
            }
        }
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
        $this->selectedCustomerId = null;
        $this->dispatch('pos-sale-completed');

        Notification::make()
            ->title('Sales invoice '.$invoice->invoice_no.' created')
            ->success()
            ->send();

        if ($print) {
            $this->redirectRoute('pos.sales-invoices.print', ['salesInvoice' => $invoice->id]);
        }
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
            'payment_summary' => $this->registerPaymentSummary(),
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

    public function selectedTaxRate(): float
    {
        $rate = TaxRate::rateFor($this->taxRateId);

        if ($this->taxRateId !== null) {
            return max(0, $rate);
        }

        return max(0, (float) $this->taxRate);
    }

    private function companyQuery(Builder $query): Builder
    {
        $companyId = $this->selectedCompanyId ?? app(CurrentCompany::class)->id();

        return $query->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId));
    }

    private function registerPaymentSummary(): array
    {
        return $this->companyQuery(Voucher::withoutGlobalScopes())
            ->with('bankAccount:id,account_name,bank_name')
            ->where('voucher_type', VoucherType::Receipt->value)
            ->where('status', VoucherStatus::Posted->value)
            ->whereDate('voucher_date', today())
            ->where('reference_no', 'like', 'POS-%')
            ->get(['id', 'bank_account_id', 'amount', 'notes'])
            ->groupBy(fn (Voucher $voucher): string => $this->paymentSummaryKey($voucher))
            ->map(function (Collection $vouchers): array {
                $voucher = $vouchers->first();

                return [
                    'payment_type' => $this->paymentLabelFromVoucher($voucher),
                    'account' => $this->bankAccountLabel($voucher),
                    'amount' => round((float) $vouchers->sum('amount'), 2),
                ];
            })
            ->sortBy(fn (array $row): string => $row['payment_type'].'|'.$row['account'])
            ->values()
            ->all();
    }

    private function paymentSummaryKey(Voucher $voucher): string
    {
        return $this->paymentLabelFromVoucher($voucher).'|'.($voucher->bank_account_id ?? 'none');
    }

    private function paymentLabelFromVoucher(Voucher $voucher): string
    {
        $notes = (string) $voucher->notes;

        if (preg_match('/Payment Type:\s*(.+)$/', $notes, $matches) === 1) {
            return trim($matches[1]) ?: 'Payment';
        }

        if (str_contains($notes, ' - ')) {
            return trim((string) str($notes)->afterLast(' - ')) ?: 'Payment';
        }

        return 'Payment';
    }

    private function bankAccountLabel(Voucher $voucher): string
    {
        if (! $voucher->bankAccount) {
            return 'No bank selected';
        }

        return trim($voucher->bankAccount->account_name.' - '.$voucher->bankAccount->bank_name);
    }

    private function repriceCartForSelectedCustomer(): void
    {
        if ($this->cart === []) {
            return;
        }

        $products = $this->companyQuery(ProductItem::withoutGlobalScopes())
            ->whereKey(array_keys($this->cart))
            ->get(['id', 'sale_price', 'wholesale_price'])
            ->keyBy('id');
        $priceType = $this->selectedCustomerPriceType();

        foreach ($this->cart as $productId => $item) {
            $product = $products->get($productId);

            if (! $product) {
                continue;
            }

            $this->cart[$productId]['price'] = $this->productPrice($product, $priceType);
        }
    }

    private function productPrice(mixed $product, ?string $priceType = null): float
    {
        $retailPrice = (float) data_get($product, 'sale_price', 0);
        $wholesalePrice = (float) data_get($product, 'wholesale_price', 0);

        if (($priceType ?? $this->selectedCustomerPriceType()) === 'wholesale') {
            return $wholesalePrice;
        }

        return $retailPrice;
    }

    private function selectedCustomerPriceType(): string
    {
        if (! $this->selectedCustomerId) {
            return 'retail';
        }

        return $this->companyQuery(Customer::withoutGlobalScopes())
            ->whereKey($this->selectedCustomerId)
            ->value('price_type') === 'wholesale' ? 'wholesale' : 'retail';
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
                    'notes' => trim(($this->paymentNote ?: 'POS sale receipt').' | Payment Type: '.$split['label']),
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
}
