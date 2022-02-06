<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTablesGrid extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tables_grid', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');      //Id пользователя
            $table->unsignedBigInteger('exchange_id');  //Id биржи для торговли
            $table->string('ticker');                   //Пара
            $table->string('bot_name');                 //Имя бота
            $table->double('lowest_price');             //Нижняя граница для цены
            $table->double('highest_price');            //Верхняя граница для цены
            $table->double('priceInterval')->nullable();                 //Интервал по которому будет выставляться обьем в ордере
            $table->double('investments');              //Сумма инвестиции
            $table->integer('order_qty');               //К-во ордеров
            $table->string('currency_used');            //Используемая монета для торговли
            $table->bigInteger('arbitrage')->default(0);            //К-во сделок бота
            $table->double('profit');                   //Общая прибыль
            $table->double('grid_profit');              //Прибыль с сетки
            $table->double('year_profit');              //Годовая прибыль
            $table->double('stop_price');               //Стоп-цена
            $table->double('start_price');              //Цена для входа
            $table->integer('minAmountInDecimalPlaces');
            $table->double('costPerOrder');
            $table->boolean('is_active')->default(1);
            $table->boolean('is_deferred')->default(0);


            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('exchange_id')->references('id')->on('exchanges');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tables_grid');
    }
}
