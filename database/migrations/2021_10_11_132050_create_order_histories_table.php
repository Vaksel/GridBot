<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            $table->string('uniqueOrderId');
            $table->unsignedBigInteger('grid_id');
            $table->string('symbol');
            $table->double('price');
            $table->double('amount');
            $table->string('side');
            $table->double('profit')->nullable();
            $table->timestamps();

            $table->foreign('grid_id')->on('grids')->onUpdate('cascade')->onDelete('cascade')->references('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_histories');
    }
}
