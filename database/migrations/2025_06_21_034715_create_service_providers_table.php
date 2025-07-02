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
         Schema::create('service_providers', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('bike_model')->nullable();
            $table->string('plate_number')->unique()->nullable();
            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->enum('status', ['offline', 'online', 'on_trip'])->default('offline');

            // document & image URLs
            $table->string('license_url')->nullable();
            $table->string('insurance_url')->nullable();
            $table->json('additional_image_urls')->nullable();

            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
   public function down()
    {
        Schema::dropIfExists('service_providers');
    }
};
