<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActiveOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('active_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uniqueOrderId');
            $table->unsignedBigInteger('grid_id');
            $table->double('amount');
            $table->double('price');
            $table->double('sum');
            $table->string('side');
            $table->boolean('is_active');

            $table->foreign('grid_id')->onDelete('cascade')->onUpdate('cascade')->on('grids')->references('id');
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
        Schema::dropIfExists('active_orders');
    }
}
