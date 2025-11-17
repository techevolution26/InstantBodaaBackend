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
        Schema::table('wallet_accounts', function (Blueprint $table) {
        $table->decimal('savings_rate', 5, 2)->default(5.00);  // e.g. 5% APR
        $table->decimal('loan_rate',    5, 2)->default(12.00); // e.g. 12% APR
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {        Schema::dropIfExists('wallet_accounts');

    }
};
