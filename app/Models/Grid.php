<?php

namespace App\Models;

use App\Exceptions\EmptyPropertyException;
use App\Helpers\NumHelper;
use App\Jobs\OrderChangeHandlerBinance;
use App\Jobs\OrderChangeHandlerKucoin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Lin\Binance\Binance;
use Lin\Ku\Kucoin;

/**
 * Class Grid
 * @package App\Models
 */
class Grid extends Model
{
    use HasFactory;

    const ASSET_BASE_TYPE = 1;

    const ASSET_QUOTE_TYPE = 0;

    public $exchangeConnection;

    protected $guarded = [];

    protected $halfOrderQty, $amountInterval;

    protected $orders;

    public const ARITHMETIC_PROGRESSION = 0;
    public const GEOMETRIC_PROGRESSION = 1;


    public function initConnection()
    {
        $exchange = $this->exchange;

        $exchange->initConnection();

//        $this->connection = $exchange->connection;
        $this->exchangeConnection = $exchange;
    }

    protected function checkExchangeConnection()
    {
        if (empty($this->exchangeConnection) || !($this->exchangeConnection instanceof Exchange)) {
            throw new EmptyPropertyException('Не обнаружено соединение с биржей! Проинициализируйте свойство connection обьектом с типом Exchange', 404);
        }
    }

    protected function setStartPrice()
    {
        if (empty($this->start_price)) {
            $this->start_price = $this->exchangeConnection->getTickerCurrentPrice($this->ticker);
            return true;
        } else {
            return false;
        }
    }

    //Проверяем есть ли соединение для этой биржи в списке KucoinElderConnections
    //Если нету - добавляем соединение в список KucoinConnections
    //Если же есть, значит просто идем дальше
    protected function checkAndWriteNewConnectionToKucoinSockets()
    {
        $redis = Redis::connection('default');

        $exchangeId = $this->exchangeConnection->id;

        $exchangeIsSubscribed = $redis->command('hexists', ['kucoin_socket_connections_elder',
            $exchangeId]);

        if (!$exchangeIsSubscribed) {
            $exGlobalFunction = new ExchangeGlobalFunctionsKucoin($this->exchangeConnection);

            $socketUrl = $exGlobalFunction->getKucoinSocketConnection();

            $redis = Redis::connection('default');

            $redis->command('lpush', ['kucoin_socket_connections', $socketUrl . '++' . $exchangeId]);

            Log::write('debug', 'Новое сокет соединение записано',
                [
                    'el' => 'laravel_database_kucoin_socket_connections' . $exchangeId,
                    'socketUrl' => $socketUrl
                ]);

            return true;
        }

        Log::write('debug', 'Сокет соединение уже установлено', ['exchangeId' => $exchangeId]);

        return false;
    }

    protected function refreshCoinGetting()
    {
        try {
            if ($this->exchangeConnection->exchangeIsBinance()) {
                $listenKeyIsAvailable = $this->checkListenKeyAvailability();

                if (!$listenKeyIsAvailable) {
                    $this->refreshTickerSubscription();

                    return true;
                }

                Log::write('debug', 'Подписка не требуется');
            }

            if ($this->exchangeConnection->exchangeIsKucoin()) {
                $this->checkAndWriteNewConnectionToKucoinSockets();

                return true;
            }

            Log::write('debug', 'Подписка не требуется');

            return false;
        } catch (\Exception $e) {
            Log::write('debug', 'При попытке обновить подписку на получение данных через сокеты произошла ошибка', ['Ошибка' => $e]);
            return false;
        }
    }

    /**
     * @param $isLazyLoading - true / false
     * @param $side - SELL / BUY
     * @return string[]
     *
     * Производим операции из начальным к-вом валюты - покупает или продает нужное к-во
     * монеты для последующей работы, также может отличаться тип ордера, может быть как
     * market так и limit ордер
     */
    protected function coinsProcessing($side, $isLazyLoading, $assetsNeedle = null)
    {
//        $coinsGettingResult = $this->getCoinsForFurtherProcessing($side,$isLazyLoading, $assetsNeedle);

        $coinsGettingResult['status'] = 'success';

        if ($coinsGettingResult['status'] === 'success') {
            if ($isLazyLoading) {
                $this->refreshCoinGetting();

                $this->saveLazyProcessingRecord($coinsGettingResult['order']);
            } else {
                $this->bookOrders();
            }
        } else {
            $this->delete();

            return ['status' => 'attention', 'title' => 'Ошибка', 'msg' => 'Ошибка, невозможно купить монеты для последующей продажи'];
        }
    }

    /**
     * @param $side - BUY / SELL
     * @param $isLimit - true / false
     * @param $assetsNeedle
     * @return array|string[]
     */
    protected function getCoinsForFurtherProcessing($side, $isLimit, $assetsNeedle = null)
    {
        try {

            $order = new Order();

            if (empty($assetsNeedle)) {
                $order->amount = $this->calculateVolumeForFirstTrade($this->orders);
            } else {
                $order->amount = $assetsNeedle;
            }
            Log::write('debug', 'firstTradeAmount', ['firstTradeAmount' => $order->amount]);
            $order->ticker = $this->ticker;
            $order->side = $side;
            if (!$isLimit) {
                $order->type = Order::IS_MARKET_TYPE;
            } else {
                $order->price = $this->start_price;
                $order->type = Order::IS_LIMIT_TYPE;
            }

            $order->generateAndSetOrderUniqId();

            if (!$isLimit) {
                $bookResponse = $this->exchangeConnection->bookMarketOrder($order);
            } else {
                $bookResponse = $this->exchangeConnection->bookOrder($order);
            }

            if ($bookResponse['status'] === 'success') {
                return ['status' => 'success', 'msg' => 'Продажные ордера были успешно куплены', 'order' => $order];
            } else {
                Log::write('debug', 'Монеты для продажного ордера не были куплены, что-то помешало');

                return ['status' => 'fail', 'msg' => 'Продажные ордера не были куплены'];
            }

        } catch (\Exception $e) {
            Log::write('debug', 'Монеты для продажного ордера не были куплены, произошла ошибка', ['error' => $e]);

            return ['status' => 'fail', 'msg' => 'Продажные ордера не были куплены'];
        }
    }

    protected function getTotalAmount($firstOrderPrice, $orderStopPrice)
    {
        $totalAmount = 0;
        $i = 0;

        $priceArray = [$firstOrderPrice];
        while ($priceArray[$i] + $this->priceInterval < $orderStopPrice) {
            $price = $priceArray[$i] + $this->priceInterval;

            $totalAmount += $price;

            $i++;

            $priceArray[$i] = $price;
        }

        return $totalAmount;
    }

    protected function investmentsNotValid($recalculatedInvestments)
    {
        $minPriceInDecimalPlaces = $this->exchangeConnection->getMinPriceForSymbolInDecimalPlaces($this->ticker);

        if (empty($minPriceInDecimalPlaces)) {
            $minPriceInDecimalPlaces = 5;
        }
        Log::write('critical', 'Во время 
        выставления сетки произошел перерасчет минимальной суммы для 
        инвестирования, теперь она составляет: ' .
            number_format($recalculatedInvestments, $minPriceInDecimalPlaces));

        $this->delete();

        return ['status' => 'attention', 'title' => 'Внимание', 'msg' =>
            'Во время выставления сетки произошел перерасчет минимальной суммы 
            для инвестирования, теперь она составляет: ' .
            number_format($recalculatedInvestments, $minPriceInDecimalPlaces)];
    }

    protected function getUserBalanceForChosenAssets()
    {
        $exchangeInfo = json_decode($this->exchangeConnection->getExchangeInfoBySymbol($this->ticker), true);

        if ($this->exchangeConnection->exchangeIsBinance()) {
            $baseAsset = $exchangeInfo['symbols'][0]['baseAsset'];
            $quoteAsset = $exchangeInfo['symbols'][0]['quoteAsset'];
        }

        if ($this->exchangeConnection->exchangeIsKucoin()) {
            $baseAsset = $exchangeInfo['baseCurrency'];
            $quoteAsset = $exchangeInfo['quoteCurrency'];
        }


        $baseBalance = $this->exchangeConnection->getDepositBalanceBySymbol($baseAsset);
        $quoteBalance = $this->exchangeConnection->getDepositBalanceBySymbol($quoteAsset);

        Log::write('debug', 'baseBalance', ['baseBalance' => $baseBalance]);
        Log::write('debug', 'quoteBalance', ['quoteBalance' => $quoteBalance]);


        return ['baseAssetBalance' => $baseBalance, 'quoteAssetBalance' => $quoteBalance];
    }

    /**
     * @param bool $isLazyLoading
     * @param bool $isAssetUsageWarned
     * @return string[]
     */
    public function putGridIntoOperation(bool $isLazyLoading = false, bool $isAssetUsageWarned = false)
    {
        try {
            $this->checkExchangeConnection();
        } catch (EmptyPropertyException $e) {
            Log::write('error', 'Ошибка при добавлении сетки, не найдено соединение', ['error' => $e]);

            return ['status' => 'fail', 'title' => 'Ошибка', 'msg' => 'Во время выставления сетки произошла ошибка, попробуйте снова'];
        }

        $tickerMinPriceInDecimalPlaces = $this->exchangeConnection->getMinPriceForSymbolInDecimalPlaces($this->ticker);

        $this->priceInterval = number_format($this->getPriceInterval(), $tickerMinPriceInDecimalPlaces);
        $this->halfOrderQty = $this->getHalfOrderQty();

        $this->minAmountInDecimalPlaces = $this->exchangeConnection->getMinAmountForSymbolInDecimalPlaces($this->ticker);
        $this->costPerOrder = $this->investments / 2 / $this->halfOrderQty;

        if (!$isLazyLoading) {
            $settingStartPriceDone = $this->setStartPrice();
        }

        $this->save();


        $this->orders = $this->getOrders();

        $ordersCollection = collect($this->orders);

        foreach (
            $ordersCollection as $key => $order
        ) {
            Log::write('debug', "order #{$order->id}", $order->toArray());
        }

        $recalculatedInvestments = $this->recalculateMinInvestments($this->orders);

        if ($this->investments < $recalculatedInvestments) {
            return $this->investmentsNotValid($recalculatedInvestments);
        }

        $assetAvailabilityResult = $this->checkAssetAvailability();

        if ($assetAvailabilityResult['status'] === 'success') {
            if (!empty($assetAvailabilityResult['baseAssetForSell'])) {
                $assetsNeedle = $assetAvailabilityResult['baseAssetForSell'];
            } else {
                $assetsNeedle = $assetAvailabilityResult['quoteAssetForBuy'];
            }
        } else {
            return $assetAvailabilityResult;
        }

        if (!$isAssetUsageWarned) {
            $assetsUsage = $this->calculateAssetUsage();

            return ['status' => 'success', 'title' => 'Использование средств', 'msg' => 'Вы подтверждаете использование
            сервисом следующих обьемов средств?', 'vol' => $assetsUsage, 'gridId' => $this->id];
        }

//        if(!$isLazyLoading)
//        {
//            if($this->alt_used)
//            {
//                $this->coinsProcessing('SELL', $isLazyLoading, $assetsNeedle);
//            }
//            else
//            {
//                $this->coinsProcessing('BUY', $isLazyLoading);
//            }
//        }
//        else
//        {
//            if($this->alt_used)
//            {
//                $this->coinsProcessing('SELL', $isLazyLoading, $assetsNeedle);
//            }
//            else
//            {
//                $this->coinsProcessing('BUY', $isLazyLoading);
//            }
//        }

    }

    protected function formNewStarterOrder($amount, $side)
    {
        $order = new Order();

        $order->amount = $amount;
        $order->ticker = $this->ticker;
        $order->side = $side;
        $order->type = 'MARKET';

        $order->generateAndSetOrderUniqId();

        return $order;
    }


    protected function coinOperateFunds($assetAvailability)
    {
        if (!empty($assetAvailability['baseAssetForSell'])) {
            $order = $this->formNewStarterOrder($assetAvailability['baseAssetForSell'], 'SELL');

        }
    }

    protected function calculateHowMuchAssetsMustBeUsedInsteadParallelAsset($assetType, $baseAssetBalance, $quoteAssetBalance, $balance)
    {
        $minAmountInDecimal = $this->exchange->getMinAmountForSymbolInDecimalPlaces($this->ticker);

        if ($assetType === self::ASSET_BASE_TYPE) {
            $baseAssetForSell = $quoteAssetBalance / $this->exchange->getTickerCurrentPrice($this->ticker);

            $baseAssetForSell = NumHelper::numberFormatWithRightCeil($baseAssetForSell, $minAmountInDecimal);

            $baseAssetDifference = $balance['baseAssetBalance'] - $baseAssetForSell;

            $baseAssetDifference = NumHelper::numberFormatWithRightCeil($baseAssetDifference, $minAmountInDecimal);

            if ($balance['baseAssetBalance'] < $baseAssetForSell) {
                return ['status' => 'fail', 'title' => 'Ошибка', 'msg' => 'У вас на счету не хватает ' . $baseAssetDifference . ' ' . $this->currency_used];
            }

            return ['status' => 'success', 'baseAssetForSell' => $baseAssetForSell];
        } else {
            $quoteAssetForBuy = $baseAssetBalance * $this->exchange->getTickerCurrentPrice($this->ticker);

            $quoteAssetForBuy = NumHelper::numberFormatWithRightCeil($quoteAssetForBuy, $minAmountInDecimal);

            $quoteAssetDifference = $balance['quoteAssetBalance'] - $quoteAssetForBuy;

            Log::write('debug', 'quoteAssetDif - ' . $quoteAssetDifference);

            $quoteAssetDifference = NumHelper::numberFormatWithRightCeil($quoteAssetDifference, $minAmountInDecimal);

            if ($balance['quoteAssetBalance'] < $quoteAssetDifference) {
                return ['status' => 'fail', 'msg' => 'У вас на счету не хватает ' . $quoteAssetDifference . ' ' . $this->currency_used];
            }

            return ['status' => 'success', 'quoteAssetForBuy' => $quoteAssetForBuy];
        }
    }

    protected function checkAssetAvailability()
    {
        $buyOrders = $this->getBuyOrders();
        $sellOrders = $this->getSellOrders();

        $baseAssetAmount = $this->countOrdersAmount($sellOrders);
        $quoteAssetAmount = $this->countOrdersAmount($buyOrders);

        $balance = $this->getUserBalanceForChosenAssets();

        if ($this->alt_used) {
            $assetsInfo = $this->calculateHowMuchAssetsMustBeUsedInsteadParallelAsset(self::ASSET_BASE_TYPE, $baseAssetAmount, $quoteAssetAmount, $balance);
        } else {
            $assetsInfo = $this->calculateHowMuchAssetsMustBeUsedInsteadParallelAsset(self::ASSET_QUOTE_TYPE, $baseAssetAmount, $quoteAssetAmount, $balance);
        }
        return $assetsInfo;
    }

    protected function saveLazyProcessingRecord($order)
    {
        try {
            $model = new LazyProcessingOrders();

            $model->uniqueOrderId = $order->orderUniqId;
            $model->grid_id = $this->id;
            $model->price = $order->price;
            $model->amount = $order->amount;
            $model->summ = $order->price * $order->amount;

            $model->save();

            return ['status' => 'success'];
        } catch (\Exception $e) {
            Log::write('debug', 'Не получилось сохранить ордер в ордера для отложенной активации', ['error' => $e]);

            return ['status' => 'fail'];
        }
    }

    protected function getPriceInterval()
    {
        return ($this->highest_price - $this->lowest_price) / $this->order_qty;
    }

    protected function calculateVolumeForFirstTrade($orders)
    {
        $minAmountInDecimalPlaces = $this->exchangeConnection->getMinAmountForSymbolInDecimalPlaces($this->ticker);

        $summ = 0;

        foreach ($orders as $order) {
            if ($order->side === 'SELL') {
                $summ += $order->amount;
            }
        }

        $summ = number_format($summ, $minAmountInDecimalPlaces);

        return $summ;
    }

    protected function calculateAssetUsage()
    {
        $quoteAssetInvestment = 0;
        $baseAssetInvestment = 0;

        foreach ($this->orders as $order) {
            if ($order->side === 'SELL') {
                if ($this->alt_used) {
                    $baseAssetInvestment += $order->amount;
                } else {
                    $quoteAssetInvestment += $order->amount * $this->start_price;
                }
            } else {
                if ($this->alt_used) {
                    $baseAssetInvestment += $order->amount;
                } else {
                    $quoteAssetInvestment += $order->amount * $this->start_price;
                }
            }
        }

        return ['quoteAssetInvestment' => $quoteAssetInvestment,
            'baseAssetInvestment' => number_format($baseAssetInvestment, $this->minAmountInDecimalPlaces)];
    }

    /**
     * @param Order[] $orders
     * @return double
     */
    protected function recalculateMinInvestments($orders)
    {
        $investments = 0;

        foreach ($orders as $order) {
            $investments += $order->amount * $order->price;
        }

        return $investments;
    }

    public function saveOrderToActiveOrders($order)
    {
        try {
            $redis = Redis::connection('default');

            $activeOrderArr = [
                'uniqueOrderId' => $order->orderUniqId,
                'grid_id' => $this->id,
                'amount' => $order->amount,
                'price' => $order->price,
                'sum' => $order->price * $order->amount,
                'side' => $order->side
            ];

            $redis->command('hset', ['active_orders', $activeOrderArr['uniqueOrderId'], json_encode($activeOrderArr)]);


            $activeOrder = new ActiveOrder();
            $activeOrder->uniqueOrderId = $activeOrderArr['uniqueOrderId'];
            $activeOrder->grid_id = $activeOrderArr['grid_id'];
            $activeOrder->amount = $activeOrderArr['amount'];
            $activeOrder->price = $activeOrderArr['price'];
            $activeOrder->sum = $activeOrderArr['sum'];
            $activeOrder->side = $activeOrderArr['side'];
            $activeOrder->is_active = true;

            $activeOrder->save();


            return true;
        } catch (\Exception $e) {
            Log::write('debug', 'Произошла ошибка во время сохранения активного ордера в бд', ['error' => $e]);
            return false;
        }
    }

    public function bookOrders($orders = null)
    {
        try {
            $this->checkExchangeConnection();
        } catch (EmptyPropertyException $e) {
            return Response(['status' => 'error', 'msg' => $e->getMessage(), 'errorSpecimen' => $e]);
        }

        if (empty($orders)) {
            $orders = $this->orders;
        }

        $this->refreshCoinGetting();

        foreach ($orders as $order) {
            $res = $this->exchangeConnection->bookOrder($order);

            if ($res['status'] === 'error') {
                Log::write('error', 'Ошибка при попытке добавить ордер', ['Ошибка' => $res['msg']]);

                continue;
            }

            $this->saveOrderToActiveOrders($order);
        }

        if (empty($this->start_at)) {
            $this->start_at = date('Y-m-d H:i:s');
        }

        $this->save();


        return true;
    }

    protected function listenKeyIsAvailable($listenKeys)
    {
        if (!empty($listenKeys)) {
            if (!empty($listenKeys[$this->exchangeConnection->id])) {
                if ($listenKeys[$this->exchangeConnection->id]['expires_at'] - time() < 1800) {
                    return false;
                } else {
                    return true;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    protected function checkListenKeyAvailability()
    {
        if ($this->exchangeConnection->exchangeIsBinance()) {
            if (Redis::connection('default')->exists("binance_listen_keys")) {
                $listenKeys = json_decode(Redis::connection('default')->get("binance_listen_keys"), true);

                return $this->listenKeyIsAvailable($listenKeys);

            }
        }

        if ($this->exchangeConnection->exchangeIsKucoin()) {
            if (Redis::connection('default')->exists("kucoin_listen_keys")) {
                $listenKeys = json_decode(Redis::connection('default')->get("kucoin_listen_keys"), true);

                return $this->listenKeyIsAvailable($listenKeys);
            }
        }

        return false;
    }

    public static function refreshTickerSubscriptionBinanceStatic($exchange)
    {
        Redis::connection('default')->set('handlerRedisFlagBinance', 1);

        $orderChangeHandler = new OrderChangeHandlerBinance($exchange);
        dispatch($orderChangeHandler);
    }

    protected function refreshTickerSubscription()
    {
        if ($this->exchangeConnection->exchangeIsBinance()) {
            Redis::connection('default')->set('handlerRedisFlagBinance', 1);

            $orderChangeHandler = new OrderChangeHandlerBinance($this->exchangeConnection);
            dispatch($orderChangeHandler);
        }

        //TODO:05.11.2021 убрать
        if ($this->exchangeConnection->exchangeIsKucoin()) {
            Redis::connection('default')->set('handlerRedisFlagKucoin', 1);

            $orderChangeHandler = new OrderChangeHandlerKucoin($this->exchangeConnection);
            dispatch($orderChangeHandler);
        }

        return true;
    }

    public function clearActiveOrders()
    {
        try {
            $activeOrders = $this->activeOrders;

            foreach ($activeOrders as $activeOrder) {
                $this->exchange->deleteOrder($this->ticker, $activeOrder->uniqueOrderId);
                $activeOrder->delete();
            }
            return true;
        } catch (\Exception $e) {
            Log::write('error', 'Ошибка при попытке очистить активные ордера сетки', ['error' => $e]);
            return false;
        }

    }

    public function fillOrdersByType($type,$firstOrderPrice,
                                     $middleAmount, $tickerMinPriceInDecimalPlaces,
                                     $tickerMinAmountInDecimalPlaces, $orderStopPrice)
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

        return $orders;
    }

    public function checkNotionalAndMakeRightNotional($tickerMinNotional, $amount, $price, $startIncrementFrom = 1)
    {
        $notional = $amount * $price;

        if ($notional < $tickerMinNotional) {
            $amount = (string)$amount;
            $lastAmountNumber = substr($amount, -1 * $startIncrementFrom, 1);
            $lastAmountNumberIndex = (int)$lastAmountNumber;

//            ddd($lastAmountNumberIndex);
            for ($i = 0; $i < (10 - $lastAmountNumberIndex); $i++) {
                $lastAmountNumber++;

                $amount = substr_replace($amount, (string)$lastAmountNumber, -1 * $startIncrementFrom, 1);

                $notional = $amount * $price;

                if ($notional >= $tickerMinNotional) {
                    break;
                }
            }

            $startIncrementFrom++;

            return $this->checkNotionalAndMakeRightNotional($tickerMinNotional, $amount, $price, $startIncrementFrom);
        } else {
            return ['amount' => $amount];
        }
    }

    protected function countOrdersAmount($orders)
    {
        $amount = 0;

        foreach ($orders as $order) {
            $amount += $order->amount;
        }

        return $amount;
    }

    public function getBuyOrders()
    {
        $orders = [];

        foreach ($this->orders as $order) {
            if ($order->side === 'BUY') {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    public function getSellOrders()
    {
        $orders = [];

        foreach ($this->orders as $order) {
            if ($order->side === 'SELL') {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    public function getOrdersByParams($amount, $price, $stopPrice, $type)
    {
        $tickerMinAmountInDecimalPlaces = $this->exchange->getMinAmountForSymbolInDecimalPlaces($this->ticker);
        $tickerMinPriceInDecimalPlaces = $this->exchange->getMinPriceForSymbolInDecimalPlaces($this->ticker);

        if ($type === -1) {
            $firstOrderPrice = $price + $this->priceInterval;
        } else {
            $firstOrderPrice = $price + $this->priceInterval * 2;
        }


        $orders = $this->fillOrdersByType($type, $firstOrderPrice,
            $amount, $tickerMinPriceInDecimalPlaces, $tickerMinAmountInDecimalPlaces,
            $stopPrice);

        return $orders;
    }

    public function normalizePrice()
    {
        $tickerMinPriceInDecimalPlaces = $this->exchangeConnection->getMinPriceForSymbolInDecimalPlaces($this->ticker);

        $this->start_price = number_format($this->start_price, $tickerMinPriceInDecimalPlaces);
        $this->lowest_price = number_format($this->lowest_price, $tickerMinPriceInDecimalPlaces);
        $this->highest_price = number_format($this->highest_price, $tickerMinPriceInDecimalPlaces);

        $this->save();

    }

    public function getOrders()
    {
        switch($this->progression_type)
        {
            case $this::ARITHMETIC_PROGRESSION: {
                return $this->getOrdersArithmetic();
            }
            case $this::GEOMETRIC_PROGRESSION: {
                return $this->getOrdersGeometric();
                break;
            }
            default: {
                return $this->getOrdersArithmetic();
            }
        }
    }

    protected function getOrdersArithmetic()
    {
        $tickerInfo = $this->getTickerInfoForCreatingOrders();

        $middleAmount = round($this->investments / $tickerInfo['totalOrdersAmount'], $tickerInfo['tickerMinAmountInDecimalPlaces'], PHP_ROUND_HALF_UP);

        if($this->exchange->exchangeIsBinance())
        {
            $tickerMinNotional  = $this->exchangeConnection->getMinNotionalForSymbol($this->ticker);

            if($middleAmount * $tickerInfo['buyFirstOrderPrice'] < $tickerMinNotional)
            {
                $middleAmount = $tickerMinNotional / $tickerInfo['buyFirstOrderPrice'];
            }

            $middleAmount = number_format($middleAmount, $tickerInfo['tickerMinAmountInDecimalPlaces']);
            $middleAmount = $this->checkNotionalAndMakeRightNotional($tickerMinNotional, $middleAmount, $tickerInfo['buyFirstOrderPrice'])['amount'];
        }

        if($this->exchange->exchangeIsKucoinByExchangeType())
        {
            $middleAmount = number_format($middleAmount, $tickerInfo['tickerMinAmountInDecimalPlaces']);

            Log::write('debug', 'middleAmount', ['midAmount' => $middleAmount]);
            Log::write('debug', 'tickerMinAmount', ['tickerMinAmount' => $tickerInfo['tickerMinAmount']]);

            if($middleAmount < $tickerInfo['tickerMinAmount'])
            {
                $middleAmount = (float)$tickerInfo['tickerMinAmount'];
            }
        }


        $sellOrders = $this->fillOrdersByType(1, $tickerInfo['sellFirstOrderPrice'],
            $middleAmount, $tickerInfo['tickerMinPriceInDecimalPlaces'], $tickerInfo['tickerMinAmountInDecimalPlaces'],
            $tickerInfo['sellOrderStopPrice']);

        $buyOrders = $this->fillOrdersByType(-1, $tickerInfo['buyFirstOrderPrice'],
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

    protected function getOrdersGeometric()
    {

    }


    /**
     * @return array['tickerMinAmountInDecimalPlaces' => int, 'tickerMinPriceInDecimalPlaces' => int,
     * 'tickerMinAmount' => double, 'sellFirstOrderPrice' => double, 'buyFirstOrderPrice' => double, 'sellOrderStopPrice' => int,
     * 'buyOrderStopPrice' => int, 'totalOrdersAmount' => int, 'halfInvestment' => double]
     */
    protected function getTickerInfoForCreatingOrders()
    {
        $tickerMinAmountInDecimalPlaces = $this->exchangeConnection->getMinAmountForSymbolInDecimalPlaces($this->ticker);
        $tickerMinPriceInDecimalPlaces  = $this->exchangeConnection->getMinPriceForSymbolInDecimalPlaces($this->ticker);
        $tickerMinAmount                = $this->exchangeConnection->getMinAmountForSymbol($this->ticker);

        $sellFirstOrderPrice    = $this->start_price + $this->priceInterval;
        $buyFirstOrderPrice     = $this->lowest_price;


        $sellOrderStopPrice = $this->highest_price;
        $buyOrderStopPrice  = $this->start_price - $this->priceInterval;

        $totalOrdersAmount  = $this->getTotalAmount($buyFirstOrderPrice, $buyOrderStopPrice);
        $totalOrdersAmount += $this->getTotalAmount($sellFirstOrderPrice, $sellOrderStopPrice);

        $halfInvestment = $this->investments / 2;

        Log::write('debug', 'halfInvestment', ['halfInvestment' => $halfInvestment]);
        Log::write('debug', 'totalOrdersAmount', ['totalOrdersAmount' => $totalOrdersAmount]);

        return compact('tickerMinAmountInDecimalPlaces','tickerMinPriceInDecimalPlaces','tickerMinAmount',
            'sellFirstOrderPrice','buyFirstOrderPrice','sellOrderStopPrice','buyOrderStopPrice','totalOrdersAmount',
            'halfInvestment'
        );
    }



    protected function deleteActiveOrdersFromRedis($activeOrders)
    {
        $redis = Redis::connection('default');

        $redisQuery = ['active_orders'];

        foreach ($activeOrders as $activeOrder)
        {
            $redisQuery[] = $activeOrder->uniqueOrderId;
        }

        $redis->command('hdel', $redisQuery);
    }

    public function stopGrid()
    {
        $activeOrders = $this->activeOrders;

        $this->deleteActiveOrdersFromRedis($activeOrders);


        foreach($activeOrders as $key => $activeOrder)
        {
            Log::write('debug', 'Активный ордер', ['order' => $key]);
            $this->exchangeConnection->deleteOrder($this->ticker, $activeOrder->uniqueOrderId);
            usleep(500);
        }

        $this->stop_at = date('Y-m-d H:i:s');

        $this->save();

    }

    public function archive()
    {
        $this->is_active = false;

        $this->save();
    }

    public function orderHistories()
    {
        return $this->hasMany(OrderHistory::class);
    }

    public function activeOrders()
    {
        return $this->hasMany(ActiveOrder::class);
    }

    public function activeSellOrdersCount()
    {
        return $this->hasMany(ActiveOrder::class)->where(['side' => 'SELL'])->count();
    }

    public function activeBuyOrdersCount()
    {
        return $this->hasMany(ActiveOrder::class)->where(['side' => 'BUY'])->count();
    }

    public function activeSellOrders()
    {
        return $this->hasMany(ActiveOrder::class)->where(['side' => 'SELL'])->get();
    }

    public function activeBuyOrders()
    {
        return $this->hasMany(ActiveOrder::class)->where(['side' => 'BUY'])->get();
    }

    public function lastSellOrder()
    {
        $minPriceForSellOrder = $this->hasMany(ActiveOrder::class)->where(['side' => 'SELL'])->min('sum');

        return $this->hasMany(ActiveOrder::class)->where('sum', 'like', '%'.$minPriceForSellOrder.'%')->first();
    }

    public function lastBuyOrder()
    {
        $maxPriceForBuyOrder = $this->hasMany(ActiveOrder::class)->where(['side' => 'BUY'])->max('sum');

        return $this->hasMany(ActiveOrder::class)->where('sum', 'like', '%'.$maxPriceForBuyOrder.'%')->first();
    }

    public function exchange()
    {
        return $this->belongsTo(Exchange::class);
    }

    protected function getAmountInterval()
    {
        return ($this->highest_price - $this->start_price) / $this->getHalfOrderQty();
    }

    protected function getHalfOrderQty()
    {
        return floor($this->order_qty / 2);
    }
}
