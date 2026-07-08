<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $statuses = implode("','", array_column(InvoiceStatus::cases(), 'value'));

            DB::statement("ALTER TABLE invoice_status_histories MODIFY status ENUM('{$statuses}') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $legacyStatuses = implode("','", [
                InvoiceStatus::PAID->value,
                InvoiceStatus::UNPAID->value,
                InvoiceStatus::PARTIALLY_PAID->value,
            ]);

            DB::statement("ALTER TABLE invoice_status_histories MODIFY status ENUM('{$legacyStatuses}') NOT NULL");
        }
    }
};
