<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('bank_accounts', 'ledger_id')) {
                $table->foreignId('ledger_id')->nullable()->after('company_id')->constrained('ledgers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('bank_accounts', 'ledger_id')) {
                $table->dropConstrainedForeignId('ledger_id');
            }
        });
    }
};
