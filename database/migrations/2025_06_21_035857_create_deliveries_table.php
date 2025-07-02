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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();         // requester
            $table->unsignedBigInteger('provider_id')->nullable(); // who picks it up
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->decimal('dropoff_lat',10, 7);
            $table->decimal('dropoff_lng',10, 7);
            $table->enum('status',['pending','assigned','in_transit','delivered','cancelled'])
                  ->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('fee_estimate',8,2)->nullable();
            $table->decimal('fee_actual',  8,2)->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('user_id')->on('service_providers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
