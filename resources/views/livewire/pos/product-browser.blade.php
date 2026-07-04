<section class="pos-products">
    <label
        x-data
        x-init="$nextTick(() => $refs.productSearch?.focus())"
        x-on:pos-focus-search.window="$nextTick(() => $refs.productSearch?.focus())"
        class="pos-search"
    >
        <span class="pos-field__icon">
            <x-filament::icon icon="heroicon-o-magnifying-glass" />
        </span>
        <input
            x-ref="productSearch"
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Scan/Search by Barcode, Product, Category, Brand"
        />
    </label>

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
                wire:click="$dispatch('pos-add-product', { product: @js([
                    "id" => $product->id,
                    "name" => $product->name,
                    "item_code" => $product->item_code,
                    "barcode" => $product->barcode,
                    "sale_price" => (float) $product->sale_price,
                ]) })"
            >
                <span class="pos-price-badge">{{ app_money((float) $product->sale_price) }}</span>

                <span @class(['pos-product-image', 'has-image' => filled($product->first_product_image_url)])>
                    @if ($product->first_product_image_url)
                        <img src="{{ $product->first_product_image_url }}" alt="{{ $product->name }}" loading="lazy" />
                    @else
                        <span>{{ \Illuminate\Support\Str::of($product->name)->substr(0, 2)->upper() }}</span>
                    @endif
                </span>

                <span class="pos-product-details">
                    <span class="pos-product-name">{{ $product->name }}</span>
                    <span class="pos-product-code">Barcode: {{ $product->barcode ?: 'No barcode' }}</span>
                    <span class="pos-product-meta">
                        <span>{{ $product->brand?->name ?: 'No brand' }}</span>
                        <span>{{ $product->category?->name ?: 'No category' }}</span>
                    </span>
                </span>
            </button>
        @empty
            <div class="pos-empty-products">No products found</div>
        @endforelse
    </div>
</section>
