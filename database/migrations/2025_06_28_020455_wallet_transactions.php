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
        //
        Schema::create('wallet_transactions', function (Blueprint $t) {
  $t->id();
  $t->foreignId('account_id')->constrained('wallet_accounts');
  $t->enum('type', ['deposit','withdraw','loan_disbursement','loan_repayment','interest_credit','interest_charge']);
  $t->decimal('amount', 15,2);
  $t->string('description')->nullable();
  $t->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('wallet_transactions');
    }
};
