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
       Schema::create('address_cache', function (Blueprint $table) {
            $table->id();
            // We store coords as strings so floats match exactly
            $table->string('lat', 20);
            $table->string('lon', 20);
            $table->string('formatted');        // the human address
            $table->json  ('components')->nullable(); // optional breakdown
            $table->timestamps();

            // Prevent duplicates
            $table->unique(['lat','lon']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('address_cache');
    }
};
