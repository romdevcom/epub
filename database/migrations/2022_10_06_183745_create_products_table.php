<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('ref');
            $table->string('name');
            $table->string('slug');
            $table->string('isbn')->nullable()->default(null);
            $table->float('price')->nullable()->default(null);
            $table->float('sale_price')->nullable()->default(null);
            $table->string('types')->nullable()->default(null);
            $table->string('languages')->nullable()->default(null);
            $table->string('image')->nullable()->default(null);
            $table->string('image_date')->nullable()->default(null);
            $table->string('status')->nullable()->default('active');
            $table->integer('order')->nullable()->default(999);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
};
