<?php

namespace App\Models;

use App\Exceptions\EmptyPropertyException;
use App\Exceptions\RedisWriteException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Lin\Binance\Binance;
use Lin\Ku\Kucoin;

class Exchange extends Model
{
    use HasFactory;

    public const EXCHANGE_TYPE_BINANCE = 0;
    public const EXCHANGE_TYPE_KUCOIN = 1;


    protected $guarded = [];

    public $exchangeConnection;

    public static function formatBinanceListenerOutput($output)
    {
        $res = [];

        $res['uniqueClientId']  = $output['data']['c'];
        $res['side']            = $output['data']['S'];
        $res['executionType']   = $output['data']['x'];
        $res['orderStatus']     = $output['data']['X'];
        $res['eventTime']       = $output['data']['E'];
        $res['exchangeType']    = ExchangeType::BINANCE_EXCHANGE;

        return $res;
    }

    public static function formatKucoinListenerOutput($output)
    {
        $res = [];

        try {
            $res['uniqueClientId']  = $output['clientOid'];
            $res['side']            = strtoupper($output['side']);
            $res['executionType']   = $output['type'];
            $res['orderStatus']     = $output['status'];
            if(empty($output['matchPrice']))
            {
                $res['price']       = $output['price'];
            }
            else
            {
                $res['price']       = $output['matchPrice'];
            }
            $res['filledSize']      = $output['filledSize'];
            $res['eventTime']       = $output['ts'];
            $res['exchangeType']    = ExchangeType::KUCOIN_EXCHANGE;
        } catch (\Exception $e)
        {
            return null;
        }

        return $res;
    }

    public function initConnection()
    {
        if(!empty($this->exchangeConnection) &&
            ($this->exchangeConnection instanceof Kucoin || $this->exchangeConnection instanceof Binance))
        {
            return false;
        }

        $apiKey = $this->api_key;
        $apiSecret = $this->api_secret;


        $apiPassphrase = $this->api_passphrase;

        if(empty($apiKey))
        {
            $error = "API-key не обнаружен";

            throw new EmptyPropertyException($error,404);
        }

        if(empty($apiSecret))
        {
            $error = "Secret-key не обнаружен";

            throw new EmptyPropertyException($error,404);
        }

        switch ($this->exchange_type_id)
        {
            case ExchangeType::BINANCE_EXCHANGE_TEST :
            {
                $this->exchangeConnection = new Binance($apiKey, $apiSecret, 'https://testnet.binance.vision');

                return true;
            }
            case ExchangeType::KUCOIN_EXCHANGE_TEST :
            {
                if(empty($apiPassphrase))
                {
                    $this->exchangeConnection = new Kucoin($apiKey, $apiSecret);
                }
                else
                {
                    $this->exchangeConnection = new Kucoin($apiKey, $apiSecret, $apiPassphrase);
                }

                return true;
            }
            case ExchangeType::BINANCE_EXCHANGE :
            {
                $this->exchangeConnection = new Binance($apiKey, $apiSecret);

                return true;
            }
            case ExchangeType::KUCOIN_EXCHANGE :
            {
                if(empty($apiPassphrase))
                {
                    $this->exchangeConnection = new Kucoin($apiKey, $apiSecret);
                }
                else
                {
                    $this->exchangeConnection = new Kucoin($apiKey, $apiSecret, $apiPassphrase);
                }

                return true;
            }
        }

        return false;
    }

    public function getAccountInfo()
    {
        $this->initConnection();

        if($this->exchangeIsBinance())
        {
            return $this->exchangeConnection->user()->getAccount();
        }

        if($this->exchangeIsKucoin())
        {
//            $this->exchangeConnection->user()->getOpenOrders();
        }
    }

    public function checkConnection()
    {
        $this->initConnection();

        try {
            if($this->exchangeIsKucoin())
            {
                $this->getTradeFee('BTC-USDT');
            }

            if($this->exchangeIsBinance())
            {
                $this->getTradeFee('BTCUSDT');
            }

            return ['status' => 'success'];
        } catch (\Exception $e)
        {
            Log::write('debug', 'Биржа не валидна', ['error' => $e]);

            return ['status' => 'fail', 'error' => $e];
        }
    }

    public function getTradeFee($symbol = null)
    {
        $this->initConnection();

        if($this->exchangeIsBinance())
        {
            if($symbol)
            {
                $tradeFee = $this->exchangeConnection->user()->getTradeFee(['symbol' => $symbol]);

                if(!empty($tradeFee))
                {
                    return $tradeFee[0]['makerCommision'];
                }
                else
                {
                    Log::write('debug', 'Коммисия не получена', ['exchange_id' => $this->id]);
                    return null;
                }
            }
            else
            {
                $tradeFee = $this->exchangeConnection->user()->getTradeFee();

                if($tradeFee)
                {
                    return $tradeFee;
                }
                else
                {
                    return null;
                }
            }
        }

        if($this->exchangeIsKucoin())
        {
            if($symbol)
            {
                $tradeFee = $this->exchangeConnection->market()->getTradeFee(['symbols' => $symbol]);

                if(!empty($tradeFee))
                {
                    if($tradeFee['code'] == 200000)
                    {
                        Log::write('debug', 'tradeFee', ['feeObj' => $tradeFee]);
                        return $tradeFee['data'][0]['makerFeeRate'];
                    }
                }
                else
                {
                    Log::write('debug', 'Коммисия не получена', ['exchange_id' => $this->id]);
                    return null;
                }
            }
            else
            {
                $tradeFee = $this->exchangeConnection->market()->getTradeFee();

                if($tradeFee)
                {
                    return $tradeFee;
                }
                else
                {
                    return null;
                }
            }
        }
    }

    public function deleteOpenOrdersBySymbol($symbol)
    {
        $this->initConnection();

        if($this->exchangeIsBinance())
        {
            return $this->exchangeConnection->user()->deleteOpenOrders(['symbol' => $symbol]);
        }

        if($this->exchangeIsKucoin())
        {
//            $this->exchangeConnection->user()->getOpenOrders();
        }
    }

    public function getOpenOrders($symbol = null)
    {
        $this->initConnection();

        if($this->exchangeIsBinance())
        {
            if($symbol)
            {
                return $this->exchangeConnection->user()->getOpenOrders(['symbol' => $symbol]);
            }
            else
            {
                return $this->exchangeConnection->user()->getOpenOrders();
            }
        }

        if($this->exchangeIsKucoin())
        {
//            $this->exchangeConnection->user()->getOpenOrders();
        }
    }

    /**
     * @param array|int[] $config
     * [
    'symbol'=>'ETHBTC',
    'limit'=>'20',
    'orderId'=>'',
    'startTime'=>'',
    'endTime'=>'',
    ]
     */
    public function getAllOrders(array $config = ['limit' => 20])
    {
        $this->initConnection();
        try
        {
            return ['status' => 'success', 'res' => $this->exchangeConnection->user()->getAllOrders($config)];
        }
        catch (\Exception $e)
        {
            return ['status' => 'error', 'res' => json_decode($e->getMessage(),true)];
        }
    }

    /**
     * @param int $grid_id
     * @param Order $order
     */
    protected function saveOrderToRedis(int $grid_id, Order $order)
    {
        try
        {
            $redis = Redis::connection('default');

            $redis->set("order_{$order->orderUniqId}", json_encode([
                    'symbol'    => $order->ticker,
                    'price'     => $order->price,
                    'quantity'  => $order->amount,
                    'grid_id'   => $grid_id
                ])
            );

        }
        catch (\Exception $e)
        {
            Log::write('error', 'Ошибка при попытке записать данные об ордере в редис', ['error' => $e]);
//            throw new RedisWriteException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }


    protected function checkOrderFieldFillableForBookOrder(Order $order)
    {
        if(empty($order->ticker) || !is_string($order->ticker))
        {
            throw new EmptyPropertyException('Ордер не содержит имя тикера, или имя не корректно!', 404);
        }

        if(empty($order->side) || !is_string($order->side) || ($order->side !== 'SELL' && $order->side !== 'BUY'))
        {
            throw new EmptyPropertyException('Ордер не содержит сторону (BUY или SELL), или имя стороны не корректно!', 404);
        }
    }

    public function bookMarketOrder(Order $order)
    {
        $this->initConnection();

        try {
            $this->checkOrderFieldFillableForBookOrder($order);

            if($this->exchangeIsBinance())
            {
                $result = $this->exchangeConnection->trade()->postOrder([
                    'symbol'            => $order->ticker,
                    'side'              => $order->side,
                    'type'              => $order->type,
                    'newClientOrderId'  => $order->orderUniqId,
                    'quantity'          => number_format($order->amount, 2),
                ]);

            }
            elseif($this->exchangeIsKucoin())
            {
                $result = $this->exchangeConnection->order()->post([
                    'symbol'            => $order->ticker,
                    'side'              => $order->side,
                    'type'              => strtolower($order->type),
                    'clientOid'         => $order->orderUniqId,
                    'size'              => $order->amount,
                ]);

            }

            if(!empty($result))
            {
                Log::write('debug', 'marketOrderResult', ['marketOrderResult' => $result]);
            }

            return ['status' => 'success', 'msg' => 'Ордер успешно выставлен'];

        }
        catch (\Exception $e)
        {
            Log::write('debug', 'Маркет ордер не был куплен, произошла ошибка',['error' => $e]);
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    public function bookOrder(Order $order)
    {
        $this->initConnection();

        try {
            $this->checkOrderFieldFillableForBookOrder($order);

            if($this->exchangeIsBinance())
            {
                $result = $this->exchangeConnection->trade()->postOrder([
                    'symbol'            => $order->ticker,
                    'side'              => $order->side,
                    'type'              => $order->type,
                    'newClientOrderId'  => $order->orderUniqId,
                    'quantity'          => $order->amount,
                    'price'             => $order->price,
                    'timeInForce'       => $order->actionTime,
                ]);

            }
            elseif($this->exchangeIsKucoin())
            {
                $symbolInfo = Redis::connection('default')->get("SYMBOL-INFO-{$order->ticker}");

                $symbolInfo = json_decode($symbolInfo, true);

                $numbersAfterComma = strlen(
                                        substr(
                                            $symbolInfo['priceIncrement'],
                                            strpos($symbolInfo['priceIncrement'], '.') + 1
                                        ));

                $result = $this->exchangeConnection->order()->post([
                    'symbol'            => $order->ticker,
                    'side'              => $order->side,
                    'type'              => strtolower($order->type),
                    'clientOid'         => $order->orderUniqId,
                    'size'              => $order->amount,
                    'price'             => number_format($order->price, $numbersAfterComma),
                    'timeInForce'       => $order->actionTime,
                ]);

            }

            return ['status' => 'success', 'msg' => 'Ордер успешно выставлен'];

        }
        catch (\Exception $e)
        {
            Log::write('critical', 'Произошла ошибка при попытке выставить ордер на биржу', ['error' => $e, 'message' => $e->getMessage(), 'code' => $e->getCode()]);
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    public function deleteOrder($symbol, $orderId)
    {
        $this->initConnection();

        try {

            if($this->exchangeIsBinance())
            {
                $result = $this->exchangeConnection->trade()->deleteOrder([
                    'symbol'                => $symbol,
                    'origClientOrderId'     => $orderId,
                ]);

            }
            elseif($this->exchangeIsKucoin())
            {
                $result = $this->exchangeConnection->order()->deleteClient([
                    'clientOid'         => $orderId,
                ]);

                Log::write('debug', 'Результат удаления ордера', ['res' => $result]);

            }

            return ['status' => 'success', 'msg' => 'Ордер успешно удален'];

        }
        catch (\Exception $e)
        {
            Log::write('critical', 'Произошла ошибка при попытке удалить ордер из биржи', ['error' => $e]);
            return ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    public function testListenKeys()
    {
        $this->initConnection();

        if($this->exchangeIsBinance())
        {
            return $this->exchangeConnection->user()->postUserDataStream();
        }

        if($this->exchangeIsKucoin())
        {
            return $this->exchangeConnection->account()->getListenSubscriptionByPrivateChannel();
        }
    }

    public static function identifyExpiredListenKeysAndRefreshThem($redisKeyForListenKeysStore, $exchangeType)
    {
        $redis = Redis::connection('default');

        $expiredListenKeys = array();

        $listenKeys = json_decode($redis->get($redisKeyForListenKeysStore), true);

        foreach ($listenKeys as $listenKey)
        {
            if($listenKey['expires_at'] - time() < 120)
            {
                $expiredListenKeys[] = $listenKey;
            }
        }

        self::refreshExpiredListenKeys($expiredListenKeys, $exchangeType);
    }

    protected static function refreshExpiredListenKeys($expiredListenKeys)
    {
        $listenKeysStr = '';

        foreach ($expiredListenKeys as $key)
        {
            $listenKeysStr += $key['key'] + ',';
        }

        Exchange::reloadEvent($listenKeysStr);
    }

    public function createEvent()
    {
        $this->initConnection();

        if($this->exchangeIsBinance())
        {
            return $this->exchangeConnection->user()->postUserDataStream();
        }

        if($this->exchangeIsKucoin())
        {
            return $this->exchangeConnection->account()->getListenSubscriptionByPrivateChannel();
        }
    }

//    public function reloadEvent($listenKey)
//    {
//        $this->initConnection();
//
//        if($this->exchangeIsBinanceOrKucoin())
//        {
//            return $this->exchangeConnection->user()->putUserDataStream(['listenKey' => $listenKey]);
//        }
//    }

    public function getMinNotionalForSymbol($symbol)
    {
        $exchangeInfo = $this->getExchangeInfoBySymbol($symbol);

        if($this->exchangeIsBinance())
        {
            if(!empty($exchangeInfo['symbols'][0]['filters']))
            {
                $filters = $exchangeInfo['symbols'][0]['filters'];

                foreach ($filters as $filter)
                {
                    if($filter['filterType'] === 'MIN_NOTIONAL')
                    {
                        return $filter['minNotional'];
                    }
                }
            }
        }

        if($this->exchangeIsKucoin())
        {
            return Redis::connection('default')->get("SYMBOL-INFO-{$symbol}");
        }
    }


    public function getMinPriceForSymbolInDecimalPlaces(string $symbol)
    {
        $minPrice = $this->getMinPriceForSymbol($symbol);

        return strlen(substr($minPrice, strrpos($minPrice, '.') + 1, strrpos($minPrice, '1') - 1));
    }

    public function getMinPriceForSymbol(string $symbol)
    {
        $exchangeInfo = $this->getExchangeInfoBySymbol($symbol);

        if($this->exchangeIsBinance())
        {
            if(!empty($exchangeInfo['symbols'][0]['filters']))
            {
                $filters = $exchangeInfo['symbols'][0]['filters'];

                foreach ($filters as $filter)
                {
                    if($filter['filterType'] === 'PRICE_FILTER')
                    {
                        return $filter['minPrice'];
                    }
                }
            }
        }

        if($this->exchangeIsKucoin())
        {
            return Redis::connection('default')->get("SYMBOL-INFO-{$symbol}");
        }
    }

    public function getMinAmountForSymbolInDecimalPlaces($symbol)
    {
        $minAmount = $this->getMinAmountForSymbol($symbol);

        return strlen(substr($minAmount, strrpos($minAmount, '.') + 1, strrpos($minAmount, '1') - 1));
    }

    public function getMinAmountForSymbol(string $symbol)
    {
        $exchangeInfo = $this->getExchangeInfoBySymbol($symbol);

        if($this->exchangeIsBinance())
        {
            if(!empty($exchangeInfo['symbols'][0]['filters']))
            {
                $filters = $exchangeInfo['symbols'][0]['filters'];

                foreach ($filters as $filter)
                {
                    if($filter['filterType'] === 'LOT_SIZE')
                    {
                        return $filter['minQty'];
                    }
                }
            }
        }

        if($this->exchangeIsKucoin())
        {
            $minAmount = Redis::connection('default')->get("SYMBOL-INFO-{$symbol}");

            if($minAmount)
            {
                $minAmount = json_decode($minAmount, true)['baseMinSize'];
            }

            return $minAmount;
        }
    }

    public function getExchangeInfoBySymbol($symbol)
    {
        $this->initConnection();

        if($this->exchangeIsBinance())
        {
            return $this->exchangeConnection->system()->getExchangeInfoBySymbol(['symbol' => $symbol]);
        }

        if($this->exchangeIsKucoin())
        {
            return Redis::connection('default')->get("SYMBOL-INFO-{$symbol}");
        }
    }

    public function getDepositBalanceBySymbol($symbol)
    {
        $this->initConnection();

        $depositBalance = $this->getDepositBalance();

        $balance = 0;

        if($this->exchangeIsBinance())
        {
            foreach ($depositBalance['balances'] as $assetBalance)
            {
                if($assetBalance['asset'] === $symbol)
                {
                    $balance += $assetBalance['free'];
                }
            }
        }

        if($this->exchangeIsKucoin())
        {
            foreach ($depositBalance['data'] as $assetBalance)
            {
                if($assetBalance['currency'] === $symbol)
                {
                    $balance += $assetBalance['available'];
                }
            }
        }

        return $balance;
    }

    public function getDepositBalance()
    {
        $this->initConnection();

        if($this->exchangeIsBinance())
        {
            return $this->exchangeConnection->user()->getAccount();
        }

        if($this->exchangeIsKucoin())
        {
            return $this->exchangeConnection->account()->getAll();
        }
    }

    public function exchangeType()
    {
        return $this->belongsTo(ExchangeType::class);
    }

    public function reloadEvent($listenKey)
    {
        $this->initConnection();

        if($this->exchangeIsBinance())
        {
            try {
                return $this->exchangeConnection->user()->putUserDataStream(['listenKey' => $listenKey]);
            }
            catch (\Exception $e)
            {
                if($e->getMessage() === 'This listenKey does not exist.')
                {
                    return $this->createEvent();
                }
            }
        }

        return null;
    }

//    public function getMarketInfo($symbols = array())
//    {
//        $this->initConnection();
//
//        if(!empty($symbols))
//        {
//            if($this->exchangeIsBinance())
//            {
//                foreach ($symbols as $symbol)
//                {
//                    $this->exchangeConnection->
//                }
//            }
//
//            if($this->exchangeIsKucoin())
//            {
//                foreach ($symbols as $symbol)
//                {
//                    $this->exchangeConnection->market()->getOrderBookLevel1()
//                }
//            }
//
//        }
//        else
//        {
//            if($this->exchangeIsBinance())
//            {
//                foreach ($symbols as $symbol)
//                {
//                    $this->exchangeConnection
//                }
//            }
//        }
//    }

    public function getTickerCurrentPrice($ticker)
    {
        $this->initConnection();

        if(empty($ticker))
        {
            throw new EmptyPropertyException('Введите название тикера', 404);
        }

        if($this->exchangeIsBinance())
        {
            return $this->exchangeConnection->system()->getTickerPrice(['symbol' => $ticker])['price'];
        }

        if($this->exchangeIsKucoin())
        {
            $price = $this->exchangeConnection->market()->getOrderBookLevel1(['symbol' => $ticker])['data']['price'];
            Log::write('debug', 'Цена токена', ['tokenPrice' => $price]);
            return $price;
        }
    }

    public function getAllTickers()
    {
        $tickers = [];

        $this->initConnection();

        if($this->exchangeIsBinanceOrKucoin())
        {
            return $this->exchangeConnection->system()->getAllTickers();
        }

        return $tickers;
    }

    /**
     * @return array
     */
    public function getAlreadyListenedExchanges()
    {
        $redis = Redis::connection('default');

        if($redis->exists('kucoin_listened_exchanges'))
        {
            $alreadyListenedExchanges = $redis->get('kucoin_listened_exchanges');

            if(!empty($alreadyListenedExchanges))
            {
                return json_decode($alreadyListenedExchanges, true);
            }
        }
        else
        {
            return array();
        }
    }

    public function exchangeIsBinanceOrKucoin()
    {
        if($this->exchangeIsBinance() || $this->exchangeIsKucoin())
        {
            return true;
        }

        return false;
    }

    public function exchangeIsBinance()
    {
        if($this->exchangeConnection instanceof Binance)
        {
            return true;
        }

        return false;
    }

    public function exchangeIsKucoin()
    {
        if($this->exchangeConnection instanceof Kucoin)
        {
            return true;
        }

        return false;
    }

    public function exchangeIsKucoinByExchangeType()
    {
        if($this->exchange_type_id === 2)
        {
            return true;
        }

        return false;
    }
}
