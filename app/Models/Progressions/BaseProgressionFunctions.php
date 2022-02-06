<?php

namespace common\models\Progression;

use App\Models\Order;

class BaseProgressionFunctions
{
    /**
     * @param $orderPrice float - цена для ордера
     * @param $orderAmount float - обьем для ордера
     * @param $type int - сторона ордера (BUY или SELL)
     * @return Order
     */
    protected function fillOrderForProgression(float $orderPrice, float $orderAmount, int $type)
    {
        $order = new Order();
        $order->ticker = $this->ticker;
        $order->price = $orderPrice;
        $order->amount = $orderAmount;
        $order->side = $type === 1 ? 'SELL' : 'BUY';


        $order->generateAndSetOrderUniqId();

        return $order;
    }
}
