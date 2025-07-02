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
        Schema::create('wallet_accounts', function (Blueprint $t) {
  $t->id();
  $t->foreignId('user_id')->constrained()->unique();
  $t->decimal('balance', 15, 2)->default(0);
  $t->decimal('savings_balance', 15,2)->default(0);
  $t->decimal('loan_balance', 15,2)->default(0);
  $t->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('wallet_accounts');
    }
};
