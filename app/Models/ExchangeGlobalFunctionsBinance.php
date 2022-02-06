<?php

namespace App\Models;

use App\Jobs\OrderChangeJobBinance;
use App\Jobs\OrderChangeJobKucoin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ExchangeGlobalFunctionsBinance extends Model
{
    use HasFactory;

    public $exchangeConnection;

    public function __construct($exchangeConnection)
    {
        $this->exchangeConnection = $exchangeConnection;
    }

    protected function getOldListenKeys()
    {
        return json_decode(Redis::connection('default')->get('binance_listen_keys'), true);
    }

    protected function getOverdueListenKeys()
    {
        $oldListenKeys = $this->getOldListenKeys();

        $overDueKeys = array();

        foreach ($oldListenKeys as $oldListenKey)
        {
            if($oldListenKey['expires_at'] - time() < 1800)
            {
                $overDueKeys[] = $oldListenKey;
            }
        }

        return $overDueKeys;
    }

    /**
     * @param Exchange $exchangeConnection
     * @return array - вмещает в себя обновленный массив с ключами прослушки канала ордеров
     */
    protected function refreshOverdueListenKeysAndGetAllKeys(Exchange $exchangeConnection)
    {
        $oldListenKeys = $this->getOldListenKeys();
        $curConKeyIsWritten = false;

        if(!empty($oldListenKeys))
        {
            foreach ($oldListenKeys as $key => $value)
            {
                if($value['expires_at'] - time() < 1800)
                {
                    $eventRes = $exchangeConnection->reloadEvent($value['key']);

                    if(!empty($eventRes['listenKey']))
                    {
                        $value['key'] = $eventRes['listenKey'];
                    }

                    $value['time'] = time() + 3600;
                }

                if($key == $exchangeConnection->id)
                {
                    $curConKeyIsWritten = true;
                }
            }
        }

        return ['oldListenKeys' => $oldListenKeys, 'currentConnectionKeyIsWritten' => $curConKeyIsWritten];
    }

    protected function formStreamDataListenKeysString($listenKeys)
    {
        $streamDataListenKeyString = '';

        foreach ($listenKeys as $value)
        {
            $streamDataListenKeyString = $value['key'] . '/';
        }

        $streamDataListenKeyString = substr($streamDataListenKeyString, 0, strlen($streamDataListenKeyString) - 1);

        return $streamDataListenKeyString;
    }

    protected function executeBinanceOrderListenerProcessing($listenerData)
    {
        $job = new OrderChangeJobBinance($listenerData);

        dispatch($job);
    }

    public function orderHandler()
    {
        sleep(30);

        $redis = Redis::connection('default');

        $listenKeys = $this->refreshOverdueListenKeysAndGetAllKeys($this->exchangeConnection);

        Log::debug('listenKeys', ['listenKeys' => $listenKeys]);

        $exchangeIsBinance = $this->exchangeConnection->exchangeIsBinance();

        if(empty($listenKeys['currentConnectionKeyIsWritten']))
        {
            unset($listenKeys['currentConnectionKeyIsWritten']);
            $listenKeys = !isset($listenKeys['oldListenKeys']) ? $listenKeys : $listenKeys['oldListenKeys'];
            unset($listenKeys['oldListenKeys']);

            $listenKey = array();
            $listenKey['expires_at'] = time() + 45 * 60;

            if($exchangeIsBinance)
            {
                $listenKey['key'] = $this->exchangeConnection->createEvent()['listenKey'];
            }

            $listenKeys[$this->exchangeConnection->id] = $listenKey;

        }

        unset($listenKeys['currentConnectionKeyIsWritten']);
        $listenKeys = !isset($listenKeys['oldListenKeys']) ? $listenKeys : $listenKeys['oldListenKeys'];
        unset($listenKeys['oldListenKeys']);

        Log::debug('listenKeys', ['listenKeys' => $listenKeys]);


        $listenKeysJSON = json_encode($listenKeys);
        $socket = new Socket();

        $redisHandlerFlag = '';

        if($exchangeIsBinance)
        {
            $redis->set("binance_listen_keys", $listenKeysJSON);

            Log::debug('listenKeysJSON', ['listenKeysJSON' => $listenKeysJSON]);

            $streamPostData = $this->formStreamDataListenKeysString($listenKeys);

            Log::debug('streamPostData', ['streamPostData' => $streamPostData]);

            $socket->initSocketConnection("wss://testnet.binance.vision/stream?streams={$streamPostData}");

            $redisHandlerFlag = 'handlerRedisFlagBinance';
        }

        $redis->set($redisHandlerFlag, 1);

        sleep(5);

        if($redis->del($redisHandlerFlag))
        {
            while(true)
            {
                if(!empty(Redis::connection('default')->exists($redisHandlerFlag)))
                {
                    Redis::connection('default')->del($redisHandlerFlag);
                    break;
                }

                $res = $socket->getSocketResponse();


                if(!empty($res))
                {
                    Log::write('debug', 'orderRes', ['res' => $res]);

                    if(!empty($res['data']['X']))
                    {
                        if($res['data']['X'] === 'FILLED')
                        {
                            if($exchangeIsBinance)
                            {
                                $this->executeBinanceOrderListenerProcessing($res);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function findOrderByParams($params)
    {
        if(!empty($params['data']['c']))
        {
            $redis = Redis::connection('default');
            $order = $redis->get("order_{$params['data']['c']}");

            if(!empty($order))
            {
                $order = json_decode($order, true);

                $orderHistory = new OrderHistories();
                $orderHistory->uniqueOrderId = $params['data']['c'];
                $orderHistory->grid_id = $order['grid_id'];
                $orderHistory->symbol = $order['symbol'];
                $orderHistory->price = $params['price'];
                $orderHistory->amount = $order['quantity'];
                $orderHistory->side = $params['data']['S'];

                $orderHistory->save();
            }
        }
    }
}