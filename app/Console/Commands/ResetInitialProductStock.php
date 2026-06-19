<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProductType;
use App\Enums\Status;
use App\Enums\StockMovementType;
use App\Models\ProductItem;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetInitialProductStock extends Command
{
    private const REFERENCE_TYPE = 'initial_stock_reset';

    protected $signature = 'inventory:reset-initial-stock
        {quantity=100 : Initial stock quantity to set for each product}
        {--date= : Movement date, defaults to today}
        {--include-inactive : Include inactive products}';

    protected $description = 'Reset existing products to an initial stock movement quantity.';

    public function handle(): int
    {
        $quantity = (float) $this->argument('quantity');

        if ($quantity < 0) {
            $this->error('Quantity must be zero or greater.');

            return self::FAILURE;
        }

        $movementDate = $this->option('date') ?: now()->toDateString();

        $products = ProductItem::query()
            ->withoutGlobalScopes()
            ->when(! $this->option('include-inactive'), fn ($query) => $query->where('status', Status::Active->value))
            ->where('product_type', '!=', ProductType::Service->value)
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            $this->warn('No products found.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($products, $quantity, $movementDate): void {
            StockMovement::query()
                ->withoutGlobalScopes()
                ->where('reference_type', self::REFERENCE_TYPE)
                ->whereIn('product_item_id', $products->pluck('id'))
                ->delete();

            foreach ($products as $product) {
                $product->forceFill([
                    'opening_stock' => 0,
                    'stock_enabled' => true,
                ])->save();

                StockMovement::query()->create([
                    'company_id' => $product->company_id,
                    'item_id' => null,
                    'product_item_id' => $product->id,
                    'type' => StockMovementType::AdjustmentIn,
                    'quantity' => $quantity,
                    'rate' => $product->purchase_price ?? 0,
                    'reference_type' => self::REFERENCE_TYPE,
                    'reference_id' => null,
                    'movement_date' => $movementDate,
                    'created_at' => now(),
                ]);
            }
        });

        $this->info("Initial stock reset to {$quantity} for {$products->count()} products.");

        return self::SUCCESS;
    }
}
