<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'invoices_user_id_status_index');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'invoices_user_id_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_user_id_status_index');
            $table->dropIndex('invoices_user_id_date_index');
        });
    }
};
