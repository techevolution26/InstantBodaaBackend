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
        Schema::create('provider_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')
                  ->constrained('service_providers', 'user_id')
                  ->onDelete('cascade');
            $table->decimal('latitude',  10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('online')->default(false);
            $table->float('accuracy')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->index(['latitude','longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('provider_locations');
    }
};
