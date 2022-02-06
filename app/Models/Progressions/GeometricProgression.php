<?php

class GeometricProgression extends BaseProgressionFunctions
{
    protected $tickerMinAmountInDecimalPlaces;
    protected $tickerMinPriceInDecimalPlaces;
    protected $tickerMinAmount;
    protected $sellFirstOrderPrice;
    protected $buyFirstOrderPrice;
    protected $sellOrderStopPrice;
    protected $buyOrderStopPrice;
    protected $totalOrdersAmount;
    protected $halfInvestment;

    public function __construct($tickerInfo)
    {
        if(!empty($tickerInfo['tickerMinAmountInDecimalPlaces']))
        {
            $this->tickerMinAmountInDecimalPlaces = $tickerInfo['tickerMinAmountInDecimalPlaces'];
        }

        if(!empty($tickerInfo['tickerMinPriceInDecimalPlaces']))
        {
            $this->tickerMinPriceInDecimalPlaces = $tickerInfo['tickerMinPriceInDecimalPlaces'];
        }

        if(!empty($tickerInfo['tickerMinAmount']))
        {
            $this->tickerMinAmount = $tickerInfo['tickerMinAmount'];
        }

        if(!empty($tickerInfo['sellFirstOrderPrice']))
        {
            $this->sellFirstOrderPrice = $tickerInfo['sellFirstOrderPrice'];
        }

        if(!empty($tickerInfo['buyFirstOrderPrice']))
        {
            $this->buyFirstOrderPrice = $tickerInfo['buyFirstOrderPrice'];
        }

        if(!empty($tickerInfo['sellOrderStopPrice']))
        {
            $this->sellOrderStopPrice = $tickerInfo['sellOrderStopPrice'];
        }

        if(!empty($tickerInfo['buyOrderStopPrice']))
        {
            $this->buyOrderStopPrice = $tickerInfo['buyOrderStopPrice'];
        }

        if(!empty($tickerInfo['totalOrdersAmount']))
        {
            $this->totalOrdersAmount = $tickerInfo['totalOrdersAmount'];
        }

        if(!empty($tickerInfo['halfInvestment']))
        {
            $this->halfInvestment = $tickerInfo['halfInvestment'];
        }
    }

    public function getOrders()
    {
        $geometricProgression = new GeometricProgression($this->getTickerInfoForCreatingOrders());

        $sellOrders = $this->fillOrdersByType(1,$tickerInfo['sellFirstOrderPrice'],
            $middleAmount, $tickerInfo['tickerMinPriceInDecimalPlaces'], $tickerInfo['tickerMinAmountInDecimalPlaces'],
            $tickerInfo['sellOrderStopPrice']);

        $buyOrders = $this->fillOrdersByType(-1,$tickerInfo['buyFirstOrderPrice'],
            $middleAmount, $tickerInfo['tickerMinPriceInDecimalPlaces'], $tickerInfo['tickerMinAmountInDecimalPlaces'],
            $tickerInfo['buyOrderStopPrice']);

        $buyOrders = array_reverse($buyOrders);


        $orders = array_merge($sellOrders, $buyOrders);
        $finalOrders = [];

        $ordersLength = count($orders);

        for($i = 0; $i < $ordersLength; $i++)
        {
            if(empty($buyOrders[$i]) && empty($sellOrders[$i]))
            {
                break;
            }

            if(!empty($buyOrders[$i]))
            {
                $finalOrders[] = $buyOrders[$i];
            }

            if(!empty($sellOrders[$i]))
            {
                $finalOrders[] = $sellOrders[$i];
            }
        }

        return $finalOrders;
    }

    protected function fillOrdersByType($type)
    {
        $tickerMinAmountInDecimalPlaces =
            (float)number_format($middleAmount, $tickerMinAmountInDecimalPlaces);

        $orders[0] = $this->fillOrderForArithmeticProgression(
            number_format($firstOrderPrice, $tickerMinPriceInDecimalPlaces),
            $tickerMinAmountInDecimalPlaces,
            $type);

        $i = 0;

        while ($orders[$i]->price + $this->priceInterval < $orderStopPrice) {
            $price = $orders[$i]->price + $this->priceInterval;

            $orders[] = $this->fillOrderForArithmeticProgression(
                number_format($price, $tickerMinPriceInDecimalPlaces),
                $tickerMinAmountInDecimalPlaces, $type);

            $i++;
        }
    }

    protected
}