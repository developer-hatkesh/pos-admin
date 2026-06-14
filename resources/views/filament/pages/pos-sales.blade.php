<div class="pos-shell">
    <header class="pos-app-header">
        <a href="{{ url('/admin') }}" class="pos-back-link" aria-label="Back to admin">
            <x-filament::icon icon="heroicon-o-arrow-left" />
        </a>
        <div>
            <h1>POS Sales</h1>
            <p>Fast checkout workspace</p>
        </div>
    </header>

    <main class="pos-app-main">
        <div class="pos-toolbar">
            <div class="pos-field pos-field--customer">
                <span class="pos-field__icon">
                    <x-filament::icon icon="heroicon-o-user" />
                </span>
                <span>{{ auth()->user()?->name ?: 'n/a' }}</span>
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
                            <span class="pos-product-code">{{ $product->item_code ?: 'No code' }}</span>
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
                                    <small>{{ $item['code'] ?: 'No code' }}</small>
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

                                <span>{{ \Illuminate\Support\Number::currency($item['price'], 'GBP') }}</span>
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

                    <label class="pos-input">
                        <span>Discount</span>
                        <input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="discount" />
                        <small>{{ $discountType === 'percentage' ? '%' : '£' }}</small>
                    </label>

                    <label class="pos-input">
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
</div>
