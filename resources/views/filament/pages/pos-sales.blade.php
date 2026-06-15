<div class="pos-shell">
    <header class="pos-app-header">
        <div class="pos-app-title">
            <a href="{{ url('/admin') }}" class="pos-back-link" aria-label="Back to admin">
                <x-filament::icon icon="heroicon-o-arrow-left" />
            </a>
            <div>
                <h1>POS Sales</h1>
                <p>Fast checkout workspace</p>
            </div>
        </div>

        <div class="pos-quick-actions">
            <a href="{{ url('/admin') }}" class="pos-quick-button" title="Admin Panel" aria-label="Admin Panel">
                <x-filament::icon icon="heroicon-o-squares-2x2" />
            </a>
            <button type="button" class="pos-quick-button" wire:click="openQuickModal('holds')" title="Hold List" aria-label="Hold List">
                <x-filament::icon icon="heroicon-o-list-bullet" />
            </button>
            <button type="button" class="pos-quick-button" wire:click="openQuickModal('recent-sales')" title="Recent Sales" aria-label="Recent Sales">
                <x-filament::icon icon="heroicon-o-clock" />
            </button>
            <button type="button" class="pos-quick-button" wire:click="openQuickModal('register')" title="Register Detail" aria-label="Register Detail">
                <x-filament::icon icon="heroicon-o-clipboard-document-list" />
            </button>
            <button type="button" class="pos-quick-button" onclick="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()" title="Full Screen" aria-label="Full Screen">
                <x-filament::icon icon="heroicon-o-arrows-pointing-out" />
            </button>
            <button type="button" class="pos-quick-button" wire:click="openQuickModal('calculator')" title="Calculator" aria-label="Calculator">
                <x-filament::icon icon="heroicon-o-calculator" />
            </button>
        </div>
    </header>

    <main class="pos-app-main">
        <div class="pos-toolbar">
            <div class="pos-customer-picker">
                <label class="pos-field pos-field--customer">
                    <span class="pos-field__icon">
                        <x-filament::icon icon="heroicon-o-user" />
                    </span>
                    <select wire:model.live="selectedCustomerId" aria-label="Customer">
                        <option value="">N/A</option>
                        @foreach ($this->customers() as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="button" class="pos-add-customer-button" wire:click="openCustomerModal" title="Add Customer" aria-label="Add Customer">
                    <x-filament::icon icon="heroicon-o-plus" />
                </button>
            </div>

            <label class="pos-field pos-field--warehouse">
                <span class="pos-field__icon">
                    <x-filament::icon icon="heroicon-o-home" />
                </span>
                <select wire:model.live="selectedCompanyId">
                    @foreach ($this->companies() as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="pos-search">
                <span class="pos-field__icon">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" />
                </span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Scan/Search by Barcode, Product, Category, Brand"
                />
            </label>
        </div>

        <div class="pos-workspace">
            <section class="pos-products">
                <div class="pos-filter-row">
                    <button
                        type="button"
                        @class(['pos-chip', 'is-active' => $categoryId === null])
                        wire:click="selectCategory(null)"
                    >
                        All Categories
                    </button>
                    @foreach ($this->categories() as $category)
                        <button
                            type="button"
                            @class(['pos-chip', 'is-active' => $categoryId === $category->id])
                            wire:key="category-{{ $category->id }}"
                            wire:click="selectCategory({{ $category->id }})"
                        >
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>

                <div class="pos-filter-row">
                    <button
                        type="button"
                        @class(['pos-chip', 'is-active' => $brandId === null])
                        wire:click="selectBrand(null)"
                    >
                        All Brands
                    </button>
                    @foreach ($this->brands() as $brand)
                        <button
                            type="button"
                            @class(['pos-chip', 'is-active' => $brandId === $brand->id])
                            wire:key="brand-{{ $brand->id }}"
                            wire:click="selectBrand({{ $brand->id }})"
                        >
                            {{ $brand->name }}
                        </button>
                    @endforeach
                </div>

                <div class="pos-product-grid">
                    @forelse ($this->products() as $product)
                        <button
                            type="button"
                            class="pos-product-card"
                            wire:key="product-{{ $product->id }}"
                            wire:click="addProduct({{ $product->id }})"
                        >
                            <span class="pos-price-badge">{{ \Illuminate\Support\Number::currency((float) $product->sale_price, 'GBP') }}</span>
                            <span class="pos-stock-badge">{{ rtrim(rtrim(number_format((float) $product->opening_stock, 3), '0'), '.') }} Pcs</span>

                            <span class="pos-product-image">
                                <span>{{ \Illuminate\Support\Str::of($product->name)->substr(0, 2)->upper() }}</span>
                            </span>

                            <span class="pos-product-name">{{ $product->name }}</span>
                            <span class="pos-product-code">Code: {{ $product->item_code ?: 'No code' }}</span>
                            <span class="pos-product-code">Barcode: {{ $product->barcode ?: 'No barcode' }}</span>
                            <span class="pos-product-meta">
                                <span>{{ $product->brand?->name ?: 'No brand' }}</span>
                                <span>{{ $product->category?->name ?: 'No category' }}</span>
                            </span>
                        </button>
                    @empty
                        <div class="pos-empty-products">No products found</div>
                    @endforelse
                </div>
            </section>

            <section class="pos-sale">
                <div class="pos-cart-table">
                    <div class="pos-cart-head">
                        <span>Product</span>
                        <span>Qty</span>
                        <span>Price</span>
                        <span>Sub Total</span>
                    </div>

                    <div class="pos-cart-body">
                        @forelse ($this->cart as $item)
                            <div class="pos-cart-row" wire:key="cart-{{ $item['id'] }}">
                                <div class="pos-cart-product">
                                    <strong>{{ $item['name'] }}</strong>
                                    <small>Code: {{ $item['code'] ?: 'No code' }}</small>
                                    <small>Barcode: {{ $item['barcode'] ?? null ?: 'No barcode' }}</small>
                                </div>

                                <div class="pos-qty">
                                    <button type="button" wire:click="decrementItem({{ $item['id'] }})" aria-label="Decrease {{ $item['name'] }}">
                                        <x-filament::icon icon="heroicon-o-minus" />
                                    </button>
                                    <span>{{ $item['qty'] }}</span>
                                    <button type="button" wire:click="incrementItem({{ $item['id'] }})" aria-label="Increase {{ $item['name'] }}">
                                        <x-filament::icon icon="heroicon-o-plus" />
                                    </button>
                                </div>

                                <label class="pos-price-override">
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        wire:model.live.debounce.300ms="cart.{{ $item['id'] }}.price"
                                        aria-label="Override price for {{ $item['name'] }}"
                                    />
                                </label>
                                <div class="pos-cart-subtotal">
                                    <span>{{ \Illuminate\Support\Number::currency($item['qty'] * $item['price'], 'GBP') }}</span>
                                    <button type="button" wire:click="removeItem({{ $item['id'] }})" aria-label="Remove {{ $item['name'] }}">
                                        <x-filament::icon icon="heroicon-o-trash" />
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="pos-empty-cart">No Data Available</div>
                        @endforelse
                    </div>
                </div>

                <div class="pos-sale-footer">
                    <label class="pos-input">
                        <span>Tax</span>
                        <input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="taxRate" />
                        <small>%</small>
                    </label>

                    <div class="pos-discount-type">
                        <span>Discount</span>
                        <label>
                            <input type="radio" wire:model.live="discountType" value="fixed" />
                            Fixed
                        </label>
                        <label>
                            <input type="radio" wire:model.live="discountType" value="percentage" />
                            Percentage
                        </label>
                    </div>

                    <label class="pos-input pos-input--discount">
                        <span>Discount</span>
                        <input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="discount" />
                        <small>{{ $discountType === 'percentage' ? '%' : '£' }}</small>
                    </label>

                    <label class="pos-input pos-input--shipping">
                        <span>Shipping</span>
                        <input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="shipping" />
                        <small>£</small>
                    </label>

                    <div class="pos-totals">
                        <div>
                            <span>Total QTY</span>
                            <strong>{{ $this->totalQty() }}</strong>
                        </div>
                        <div>
                            <span>Sub Total</span>
                            <strong>{{ \Illuminate\Support\Number::currency($this->subtotal(), 'GBP') }}</strong>
                        </div>
                        <div>
                            <span>Discount</span>
                            <strong>{{ \Illuminate\Support\Number::currency($this->discountAmount(), 'GBP') }}</strong>
                        </div>
                        <div>
                            <span>Tax</span>
                            <strong>{{ \Illuminate\Support\Number::currency($this->taxAmount(), 'GBP') }}</strong>
                        </div>
                        <div>
                            <span>Shipping</span>
                            <strong>{{ \Illuminate\Support\Number::currency($this->shippingAmount(), 'GBP') }}</strong>
                        </div>
                        <div class="pos-total">
                            <span>Total</span>
                            <strong>{{ \Illuminate\Support\Number::currency($this->total(), 'GBP') }}</strong>
                        </div>
                    </div>

                    <div class="pos-actions">
                        <button type="button" class="pos-action pos-action--hold" wire:click="holdSale">
                            Hold
                            <x-filament::icon icon="heroicon-o-hand-raised" />
                        </button>
                        <button type="button" class="pos-action pos-action--reset" wire:click="resetCart">
                            Reset
                            <x-filament::icon icon="heroicon-o-arrow-path" />
                        </button>
                        <button type="button" class="pos-action pos-action--pay" wire:click="payNow">
                            Pay Now
                            <x-filament::icon icon="heroicon-o-banknotes" />
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </main>

    @if ($quickModal)
        <div class="pos-payment-overlay" role="dialog" aria-modal="true" aria-labelledby="pos-quick-title">
            <div @class(['pos-quick-modal', 'pos-quick-modal--recent-sales' => $quickModal === 'recent-sales'])>
                <div class="pos-payment-header">
                    <h2 id="pos-quick-title">
                        @if ($quickModal === 'holds')
                            Hold List
                        @elseif ($quickModal === 'recent-sales')
                            Recent Sales
                        @elseif ($quickModal === 'register')
                            Register Detail
                        @else
                            Calculator
                        @endif
                    </h2>
                    <button type="button" wire:click="closeQuickModal" aria-label="Close">
                        <x-filament::icon icon="heroicon-o-x-mark" />
                    </button>
                </div>

                <div class="pos-quick-content">
                    @if ($quickModal === 'holds')
                        <div class="pos-list-table">
                            <div class="pos-list-head">
                                <span>Reference</span>
                                <span>Time</span>
                                <span>Qty</span>
                                <span>Total</span>
                            </div>
                            @forelse ($this->heldSales() as $heldSale)
                                <div class="pos-list-row">
                                    <span>{{ $heldSale['reference'] }}</span>
                                    <span>{{ $heldSale['created_at'] }}</span>
                                    <span>{{ $heldSale['qty'] }}</span>
                                    <strong>{{ \Illuminate\Support\Number::currency($heldSale['total'], 'GBP') }}</strong>
                                </div>
                            @empty
                                <div class="pos-list-empty">No held sales for today</div>
                            @endforelse
                        </div>
                    @elseif ($quickModal === 'recent-sales')
                        <div class="pos-list-table pos-list-table--recent-sales">
                            <div class="pos-list-head pos-list-head--recent-sales">
                                <span>Date</span>
                                <span>Reference</span>
                                <span>Customer</span>
                                <span>Grand Total</span>
                                <span>Payment Status</span>
                                <span>Payment Type</span>
                                <span>Action</span>
                            </div>
                            @forelse ($this->recentSales() as $sale)
                                @php($saleStatus = $sale->status instanceof \BackedEnum ? $sale->status->value : (string) $sale->status)
                                <div class="pos-list-row pos-list-row--recent-sales">
                                    <span>{{ $sale->created_at?->format('Y-m-d g:i A') ?: $sale->invoice_date?->format('Y-m-d') }}</span>
                                    <span>{{ $sale->invoice_no }}</span>
                                    <span>{{ $sale->customer?->name ?: 'n/a' }}</span>
                                    <strong>{{ \Illuminate\Support\Number::currency((float) $sale->total, 'GBP') }}</strong>
                                    <span>
                                        <span @class(['pos-status-badge', 'is-paid' => $saleStatus === 'paid', 'is-partial' => $saleStatus === 'partial', 'is-unpaid' => in_array($saleStatus, ['draft', 'unpaid'], true)])>
                                            {{ ucfirst($saleStatus === 'draft' ? 'unpaid' : $saleStatus) }}
                                        </span>
                                    </span>
                                    <span>
                                        <span class="pos-payment-badge">{{ $sale->paymentMethod?->name ?: 'N/A' }}</span>
                                    </span>
                                    <span>
                                        <a href="{{ route('pos.sales-invoices.print', $sale) }}" class="pos-row-action" title="Print invoice" aria-label="Print {{ $sale->invoice_no }}">
                                            <x-filament::icon icon="heroicon-o-printer" />
                                        </a>
                                    </span>
                                </div>
                            @empty
                                <div class="pos-list-empty">No recent sales for today</div>
                            @endforelse
                        </div>
                    @elseif ($quickModal === 'register')
                        @php($register = $this->registerDetails())
                        <div class="pos-register-grid">
                            <div><span>User</span><strong>{{ $register['user'] }}</strong></div>
                            <div><span>Company</span><strong>{{ $register['company'] }}</strong></div>
                            <div><span>Date</span><strong>{{ $register['date'] }}</strong></div>
                            <div><span>Open Cart Qty</span><strong>{{ $register['open_cart_qty'] }}</strong></div>
                            <div><span>Open Cart Total</span><strong>{{ \Illuminate\Support\Number::currency($register['open_cart_total'], 'GBP') }}</strong></div>
                            <div><span>Held Sales</span><strong>{{ $register['held_count'] }}</strong></div>
                            <div><span>Today Sales</span><strong>{{ $register['sales_count'] }}</strong></div>
                            <div><span>Today Total</span><strong>{{ \Illuminate\Support\Number::currency($register['sales_total'], 'GBP') }}</strong></div>
                        </div>
                    @else
                        <div class="pos-calculator" x-data="{ display: '0', press(value) { if (value === 'C') { this.display = '0'; return; } if (value === 'DEL') { this.display = this.display.length > 1 ? this.display.slice(0, -1) : '0'; return; } if (value === '=') { try { const expression = this.display.replace(/[^0-9+\-*/.()]/g, ''); this.display = String(Function('return (' + expression + ')')() ?? 0); } catch (e) { this.display = '0'; } return; } this.display = this.display === '0' ? value : this.display + value; } }">
                            <input type="text" x-model="display" readonly />
                            <div class="pos-calculator-grid">
                                @foreach (['7','8','9','/','4','5','6','*','1','2','3','-','0','.','=','+','DEL','C'] as $key)
                                    <button type="button" x-on:click="press('{{ $key }}')">{{ $key }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($showCustomerModal)
        <div class="pos-payment-overlay" role="dialog" aria-modal="true" aria-labelledby="pos-customer-title">
            <div class="pos-customer-modal">
                <div class="pos-payment-header">
                    <h2 id="pos-customer-title">Add Customer</h2>
                    <button type="button" wire:click="closeCustomerModal" aria-label="Close customer form">
                        <x-filament::icon icon="heroicon-o-x-mark" />
                    </button>
                </div>

                <div class="pos-customer-form">
                    <label class="pos-payment-field">
                        <span>Name:<strong>*</strong></span>
                        <input type="text" wire:model.live.debounce.300ms="customerName" />
                        @error('customerName')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="pos-payment-field">
                        <span>Phone:</span>
                        <input type="text" wire:model.live.debounce.300ms="customerPhone" />
                        @error('customerPhone')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="pos-payment-field">
                        <span>Email:</span>
                        <input type="email" wire:model.live.debounce.300ms="customerEmail" />
                        @error('customerEmail')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="pos-payment-field">
                        <span>Address:</span>
                        <input type="text" wire:model.live.debounce.300ms="customerAddress" />
                        @error('customerAddress')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="pos-payment-field">
                        <span>City:</span>
                        <input type="text" wire:model.live.debounce.300ms="customerCity" />
                        @error('customerCity')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="pos-payment-field">
                        <span>Postcode:</span>
                        <input type="text" wire:model.live.debounce.300ms="customerPostcode" />
                        @error('customerPostcode')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="pos-payment-field">
                        <span>Country:</span>
                        <input type="text" wire:model.live.debounce.300ms="customerCountry" />
                        @error('customerCountry')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>
                </div>

                <div class="pos-payment-actions">
                    <button type="button" class="pos-payment-submit" wire:click="saveCustomer">Save Customer</button>
                    <button type="button" class="pos-payment-cancel" wire:click="closeCustomerModal">Cancel</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showPaymentModal)
        <div class="pos-payment-overlay" role="dialog" aria-modal="true" aria-labelledby="pos-payment-title">
            <div class="pos-payment-modal">
                <div class="pos-payment-header">
                    <h2 id="pos-payment-title">Make Payment</h2>
                    <button type="button" wire:click="closePaymentModal" aria-label="Close payment screen">
                        <x-filament::icon icon="heroicon-o-x-mark" />
                    </button>
                </div>

                <div class="pos-payment-content">
                    <div class="pos-payment-form">
                        <div class="pos-payment-row">
                            <label class="pos-payment-field">
                                <span>Amount:</span>
                                <input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="paymentAmount" />
                            </label>

                            <label class="pos-payment-field">
                                <span>Payment Type:<strong>*</strong></span>
                                <select wire:model.live="paymentMethodId" @disabled($paymentStatus === 'unpaid')>
                                    <option value="">Select payment type</option>
                                    @foreach ($this->activePaymentMethods() as $paymentMethod)
                                        <option value="{{ $paymentMethod->id }}">{{ $paymentMethod->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <label class="pos-payment-field pos-payment-field--wide">
                            <span>Note:</span>
                            <textarea rows="4" wire:model.live.debounce.300ms="paymentNote" placeholder="Enter Note"></textarea>
                        </label>

                        <label class="pos-payment-field pos-payment-field--wide">
                            <span>Payment Status:<strong>*</strong></span>
                            <select wire:model.live="paymentStatus">
                                <option value="paid">Paid</option>
                                <option value="partial">Partial</option>
                                <option value="unpaid">Unpaid</option>
                            </select>
                        </label>
                    </div>

                    <div class="pos-payment-summary">
                        <div>
                            <span>Total Products</span>
                            <strong class="pos-summary-badge">{{ number_format($this->totalQty(), 2) }}</strong>
                        </div>
                        <div>
                            <span>Total Amount</span>
                            <strong>{{ \Illuminate\Support\Number::currency($this->subtotal(), 'GBP') }}</strong>
                        </div>
                        <div>
                            <span>Order Tax</span>
                            <strong>{{ \Illuminate\Support\Number::currency($this->taxAmount(), 'GBP') }} ({{ number_format((float) $taxRate, 2) }}%)</strong>
                        </div>
                        <div>
                            <span>Discount</span>
                            <strong>{{ \Illuminate\Support\Number::currency($this->discountAmount(), 'GBP') }}</strong>
                        </div>
                        <div>
                            <span>Shipping</span>
                            <strong>{{ \Illuminate\Support\Number::currency($this->shippingAmount(), 'GBP') }}</strong>
                        </div>
                        <div>
                            <span>Grand Total</span>
                            <strong>{{ \Illuminate\Support\Number::currency($this->total(), 'GBP') }}</strong>
                        </div>
                        <div>
                            <span>Change Return</span>
                            <strong>{{ \Illuminate\Support\Number::currency($this->changeReturn(), 'GBP') }}</strong>
                        </div>
                    </div>
                </div>

                <div class="pos-payment-actions">
                    <button type="button" class="pos-payment-submit" wire:click="submitPayment(false)">Submit</button>
                    <button type="button" class="pos-payment-submit" wire:click="submitPayment(true)">Submit & Print</button>
                    <button type="button" class="pos-payment-cancel" wire:click="closePaymentModal">Cancel</button>
                </div>
            </div>
        </div>
    @endif
</div>
