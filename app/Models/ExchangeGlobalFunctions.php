<?php

namespace App\Models;

use Amp\Delayed;
use App\Jobs\OrderChangeJobBinance;
use App\Jobs\OrderChangeJobKucoin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use KuCoin\SDK\Auth;
use KuCoin\SDK\PrivateApi\WebSocketFeed;

class ExchangeGlobalFunctions extends Model
{
    use HasFactory;

    public $exchangeConnection;

    public function orderHandler()
    {
        ini_set('max_execution_time', 0);
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);

        $grids = $this->getAllKucoinGrids();
        $gridsAlreadyInProcessing = $this->exchangeConnection->getAlreadyListenedExchanges();

        $processGridRes = $this->processGrids($grids, $gridsAlreadyInProcessing);
        $symbols = $processGridRes['gridsInfo'];

        if($processGridRes['needRefresh'])
        {
            $exchanges = $this->getExchangesByIds($exchangeIds = array_keys($processGridRes['gridsInfo']));
            $socketConnections = $this->initializeSocketAuthConnections($exchanges);


            $websocketInfo = $this->getWebsocketInfo($socketConnections);
            $socketResult = $this->startSocketLoopForEventOrder($websocketInfo['socketUrls'], $websocketInfo['pingInterval'], $symbols);
        }


    }


    protected function executeKucoinOrderListenerProcessing($listenerData)
    {
        $job = new OrderChangeJobKucoin();

        dispatch($job);
    }

    protected function startSocketLoopForEventOrder($urls, $pingInterval, $symbols)
    {
        $startTime = time();

        Amp\Loop::run(function () use ($urls, $pingInterval, $startTime){
            /** @var Client\Connection[] $connections */

            $connectionMessage = [
                'type' => 'subscribe',
                'topic'=> '/spotMarket/tradeOrders',
                'privateChannel'=>false,
                'response'=>true
            ];

            foreach ($urls as $url)
            {
                $connect = yield Client\connect($url);

                $connect->send(json_encode($connectionMessage));
                $connections[] = ['connect' => $connect,
                    'pingtime' => 0];
            }

            $conL = count($connections);

            /** @var Websocket\Message $message */
            while (true)
            {
                for ($i = 0; $i < $conL; $i++)
                {
                    $message = yield $connections[$i]['connect']->receive();
                    if(!empty($message))
                    {
                        $payload = yield $message->buffer();

                        if(!empty($payload))
                        {
                            $payload = json_decode($payload,true);
                        }

                        if(!empty($payload['subject']) && !empty($payload['data']))
                        {

                        }

                        Log::write('debug', 'socketResponse', ['res' => $payload]);
                    }

                    if($connections[$i]['pingtime'] === 0 ||
                        (time() - $connections[$i]['pingtime'] - 5) > $pingInterval)
                    {
                        $ping = json_encode(['type' => 'ping']);
                        $connections[$i]['connect']->send($ping);
                        $connections[$i]['pingtime'] = time();
                    }

                    if(time() - $startTime > 150)
                    {
                        $connections[$i]['connect']->close();
                    }
                }

                if(time() - $startTime > 150)
                {
                    break;
                }

                yield new Delayed(1);
            }
        });
    }

    /**
     * @param array $exchanges
     * @return array
     */
    protected function initializeSocketAuthConnections(array $exchanges)
    {
        $exchangesLength = count($exchanges);
        $connections = [];

        for ($i = 0; $i < $exchangesLength; $i++)
        {
            $auth = new Auth($exchanges[$i]->api_key, $exchanges[$i]->api_secret, $exchanges[$i]->api_passphrase);
            $connections[] = new WebSocketFeed($auth);
        }

        return $connections;
    }

    /**
     * Получаем информацию по сокетам для кукоина - такую как url для подключения к WebSocket
     * и pingInterval
     * @param array $socketConnections
     * @return array
     */
    protected function getWebsocketInfo(array $socketConnections)
    {
        $socketConnectionsLength = count($socketConnections);
        $socketUrls = array();
        $pingInterval = 0;

        for($i = 0; $i < $socketConnectionsLength; $i++)
        {
            $query = ['connectId' => uniqid('', true)];
            $res = $socketConnections[$i]->getPrivateServer($query);

            $socketUrls[] = $res['connectUrl'];
            $pingInterval = $pingInterval > $res['pingInterval'] ? $res['pingInterval'] : $pingInterval;
        }

        return ['socketUrls' => $socketUrls, 'pingInterval' => $pingInterval];

    }

    /**
     * Определяю записана ли эта биржа в списке слушателя и трансформирую исходную коллекцию, которая содержит
        в себе сетки кукоина таким образом что новый массив содержит в себе id биржи в качестве ключа массива
        и заодно происходит проверка добавилась ли хотябы одна новая биржа и возвращается флаг needRefresh
        в значении true
     * @param array $grids
     * @param array $exchangesAlreadyInProcessing
     * @return array
     */

    protected function processGrids(array $grids, array $exchangesAlreadyInProcessing)
    {
        $finalArr = [];
        $needRefresh = false;

        foreach ($grids as $grid)
        {
            if(empty($exchangesAlreadyInProcessing[$grid->exchange_id]))
            {
                $needRefresh = true;
            }

            if(empty($finalArr[$grid->exchange_id]))
            {
                $finalArr[$grid->exchange_id] = $grid->ticker;
            }
        }

        return ['gridsInfo' => $finalArr, 'needRefresh' => $needRefresh];
    }

    protected function getAllKucoinGrids()
    {
        $exchangeTypeId = ExchangeType::KUCOIN_EXCHANGE;

        return Grid::whereHas('exchange', function($query) use ($exchangeTypeId)
        {
            $query->where('exchange_type_id', $exchangeTypeId);
        })->get();
    }

    public function __construct($exchangeConnection)
    {
        $this->exchangeConnection = $exchangeConnection;
    }

    protected function executeKucoinOrderListenerProcessing($listenerData)
    {
        $job = new OrderChangeJobKucoin();

        dispatch($job);
    }

    protected function getClientApiConnections()
    {

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

    public function formKucoinStreamDataListenKeysString($listenKeys)
    {
        $streamDataListenKeyString = '';

        if(!empty($listenKeys))
        {
            foreach ($listenKeys as $key => $value)
            {
                $streamDataListenKeyString = $key . '&stream=';
            }

            $streamDataListenKeyString = substr($streamDataListenKeyString, 0, strlen($streamDataListenKeyString) - 8);
        }

        return $streamDataListenKeyString;
    }


}
