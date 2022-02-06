<?php


namespace App\Classes;

/**
 * Class ActiveOrderUnited
 * @package App\Classes
 */
class ActiveOrderUnited
{
    public $uniqueOrderId, $grid_id, $symbol, $price, $amount, $side, $sum;

    /**
     * ActiveOrderUnited constructor.
     * @param $uniqueOrderId
     * @param $grid_id
     * @param $symbol
     * @param $price
     * @param $amount
     * @param $side
     * @param $sum
     */
    public function __construct($uniqueOrderId, $grid_id, $symbol, $price, $amount, $side, $sum)
    {
        $this->uniqueOrderId = $uniqueOrderId;
        $this->grid_id = $grid_id;
        $this->symbol = $symbol;
        $this->price = $price;
        $this->amount = $amount;
        $this->side = $side;
        $this->sum = $sum;
    }
}