<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('date');
            $table->timestamp('overdue_reminded_at')->nullable()->after('sent_at');
        });

        $dueDateExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "date(date, '+30 days')",
            default => 'date_add(date, interval 30 day)',
        };

        DB::table('invoices')
            ->whereNull('due_date')
            ->update([
                'due_date' => DB::raw($dueDateExpression),
            ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'overdue_reminded_at']);
        });
    }
};
