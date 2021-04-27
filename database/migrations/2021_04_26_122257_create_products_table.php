<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
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
            $table->unsignedBigInteger('category0')->nullable();
            $table->unsignedBigInteger('category1')->nullable();
            $table->unsignedBigInteger('category2')->nullable();
            $table->string('manufacturer');
            $table->string('model_code')->unique();
            $table->string('description');
            $table->integer('price');
            $table->integer('warranty');
            $table->string('available');
            $table->integer('debug_key'); // номер строки з Excel файлу

            $table->foreign('category0')->references('id')->on('category0');
            $table->foreign('category1')->references('id')->on('category1');
            $table->foreign('category2')->references('id')->on('category2');
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
}
