<?php

namespace App\Models;

use Amp;
use Amp\Delayed;
use Amp\Loop;
use Amp\Websocket\Code;
use App\Jobs\OrderChangeHandlerKucoin;
use App\Jobs\OrderChangeJobBinance;
use App\Jobs\OrderChangeJobKucoin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use KuCoin\SDK\Auth;
use KuCoin\SDK\PrivateApi\WebSocketFeed;
use function Amp\Websocket\Client\connect;
use function League\Uri\parse;

use Kelunik\LoopBlock\BlockDetector;

/**
 * TODO:07.11.2021 переделать соединение с Redis на глобальное для класса
 **/
class ExchangeGlobalFunctionsKucoin extends Model
{
    use HasFactory;

    public $exchangeConnection;

    public function __construct($exchangeConnection)
    {
        $this->exchangeConnection = $exchangeConnection;
    }

    public function getKucoinSocketConnection()
    {
        $exchanges = new Collection([$this->exchangeConnection]);

        $socketConnections = $this->initializeSocketAuthConnections($exchanges);
        $websocketInfo = $this->getWebsocketInfo($socketConnections);

        if(!empty($socketUrl = $websocketInfo['socketUrls'][0]))
        {
            return $socketUrl;
        }

        return false;
    }

    public function startKucoinSocketConnectionManually()
    {
        ini_set('max_execution_time', 0);
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);

        sleep(15);

        $redis = Redis::connection('default');

        $elderConnections = $redis->command('hgetall', ['kucoin_socket_connections_elder']);

        Log::write('debug', 'elderConnections', ['con' => $elderConnections]);
        if($redis->exists('handlerRedisFlagKucoin'))
        {
            if($redis->del('handlerRedisFlagKucoin'))
            {
                $socketResult = $this->startSocketLoopForEventOrder($elderConnections);
            }
        }
        else
        {
            $socketResult = $this->startSocketLoopForEventOrder($elderConnections);
        }

//        $grids = $this->getAllKucoinGrids();
//        $gridsAlreadyInProcessing = $this->exchangeConnection->getAlreadyListenedExchanges();
//
//        $processGridRes = $this->processGrids($grids, $gridsAlreadyInProcessing);
//        $symbols = $processGridRes['gridsInfo'];

        Log::write('debug', 'processGridRes', ['res' => 'processGridRes']);
//        if($processGridRes['needRefresh'])
//        {
//            $redis->set('kucoin_listened_exchanges', json_encode($symbols));
//            $exchanges = $this->getExchangesByIds($exchangeIds = array_keys($symbols));
//            $socketConnections = $this->initializeSocketAuthConnections($exchanges);
//
//
//            $websocketInfo = $this->getWebsocketInfo($socketConnections);
//
//            Log::write('debug', 'socketUrls', ['res' => $websocketInfo['socketUrls']]);
//
//            if($redis->exists('handlerRedisFlagKucoin'))
//            {
//                if($redis->del('handlerRedisFlagKucoin'))
//                {
//                    $socketResult = $this->startSocketLoopForEventOrder($websocketInfo['socketUrls'], $websocketInfo['pingInterval'], $symbols);
//                }
//            }
//            else
//            {
//                $socketResult = $this->startSocketLoopForEventOrder($websocketInfo['socketUrls'], $websocketInfo['pingInterval'], $symbols);
//            }
//        }
    }

    protected function getAllKucoinGrids()
    {
        $exchangeTypeId = ExchangeType::KUCOIN_EXCHANGE;

        return Grid::whereHas('exchange', function($query) use ($exchangeTypeId)
        {
            $query->where('exchange_type_id', $exchangeTypeId);
        })->get();
    }

    /**
     * Определяю записана ли эта биржа в списке слушателя и трансформирую исходную коллекцию, которая содержит
    в себе сетки кукоина таким образом что новый массив содержит в себе id биржи в качестве ключа массива
    и заодно происходит проверка добавилась ли хотябы одна новая биржа и возвращается флаг needRefresh
    в значении true
     * @param Collection $grids
     * @param array $exchangesAlreadyInProcessing
     * @return array
     */

    protected function processGrids(Collection $grids, array $exchangesAlreadyInProcessing)
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

    /**
     * @param array $exchangeIds
     * @return mixed
     */
    protected function getExchangesByIds(array $exchangeIds)
    {
        if(!empty($exchangeIds))
        {
            return Exchange::whereIn('id', $exchangeIds)->get();
        }
        else
        {
            return null;
        }
    }

    /**
     * @param Collection $exchanges
     * @return array
     */
    protected function initializeSocketAuthConnections(Collection $exchanges)
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


    protected function startSocketLoopForEventOrder($urls)
    {
        $startTime = time();

        Log::write('debug', 'startTime', ['startTime' => $startTime]);

        $config = Amp\Redis\Config::fromUri('redis://null:SSvuLr22ji6HRSc@localhost:6379');

        $redis = new Amp\Redis\Redis(new Amp\Redis\RemoteExecutor($config));

//        $blockDetector = new BlockDetector(function ($time) use($redis) {
//            $redis->set('block_detector_log_' . uniqid(), $time);
//        });

        Loop::run(function () use ($urls, $startTime, $redis){
//            $blockDetector->start();

            try {
            /** @var Client\Connection[] $connections */
            $connections = array();

            yield $redis->set('loopIsStarted' . time(), 1);
//            Log::debug('loopIsStarted', ['loopStarted' => $urls]);
            $connectionMessage =
                '{"type":"subscribe","topic":"\/spotMarket\/tradeOrders","privateChannel":true,"response":true}';

            $ping = '{"type":"ping"}';


            //Инициализируем подключения в случае если демон перезапустился
            foreach ($urls as $key => $url)
            {
                $connect = yield connect($url);

                yield $connect->send($connectionMessage);

                Amp\Loop::repeat(1000, function () use($redis, $connect, $ping)
                {
                    yield $connect->send($ping);
                });

                yield $redis->set('elderSocketIsAdded' . $key . time(), $url);


                $connections[$key] = $connect;

                Amp\Loop::repeat(30000, function () use($redis, $key, $url, $ping, $connectionMessage, &$connections)
                {
                    $connect = $connections[$key];

                    $connectionIsExist = yield $redis->query('hexists','laravel_database_kucoin_socket_connections_elder', $key);

                    if(!$connect->isConnected() && $connectionIsExist)
                    {
                        yield $redis->set('connectionRestarted' . $key . time(), 1);

                        $connect = yield connect($url);

                        yield $connect->send($connectionMessage);

                        Amp\Loop::repeat(1000, function () use($redis, $connect, $ping)
                        {
                            yield $connect->send($ping);
                        });

                        $connections[$key] = $connect;
                    }

                    $flagOnDelete = yield $redis->query('hexists', 'laravel_database_kucoin_exchange_disconnect_queue', $key);


//                    while(true)
//                    {
//                        $res = yield $redis->query('hexists', 'laravel_database_kucoin_exchange_disconnect_queue', $key);
//
//                        if($res == null)
//                        {
//                            break;
//                        }
//
//                        $closeReason = yield $connect->close(Code::NORMAL_CLOSE, 'User delete all grids on exchange');
//
//
//                    }
//
//                    $connectionThatMustBeDeleted = yield $redis->query('hget', 'laravel_database_kucoin_disconnect_queue');
                });

            }

            //Подхватываем подключения на лету
            //Достаем список ссылок на сокеты из redis и инициализируем новые подключения,
            //Добавляя их в массив connections
            Amp\Loop::repeat(60000, function () use ($redis, $ping, $connectionMessage, &$connections)
            {
                $key = 0;

                while(true)
                {
                    $res = yield $redis->query('lpop', 'laravel_database_kucoin_socket_connections');

                    if($res == null)
                    {
                        break;
                    }
                    else
                    {
                        $key++;
                    }

                    $delimitedRes = explode('++', $res);
                    $socketUrl = $delimitedRes[0];
                    $exchangeId = $delimitedRes[1];

                    $connect = yield connect($socketUrl);

                    yield $connect->send($connectionMessage);

                    Amp\Loop::repeat(1000, function () use($redis, $connect, $ping)
                    {
                        yield $connect->send($ping);
                    });

                    yield $redis->query('hset', 'laravel_database_kucoin_socket_connections_elder',$exchangeId,$socketUrl);

                    yield $redis->set('socketIsAdded', $socketUrl);
                    $connections[$exchangeId] = $connect;
                }
            });
            //

            $i = 0;
            $a = 0;

            /** @var Websocket\Message $message */
            while (true)
            {
                foreach ($connections as $key => $connection)
                {
                    $message = yield $connection->receive();

                    if(!empty($message))
                    {
                        $payload = yield $message->buffer();

                        yield $redis->set('asyncCallTest'. $key . time(), $payload);

//                        $this->executeKucoinOrderListenerProcessing($payload, $key);

                        Amp\asyncCall(function () use ($redis, $payload, $key){

                            yield $redis->set('asyncCallPayload' . $key . time(), $payload);

                            $payload = json_decode($payload,true);

                            if(!empty($payload['subject']) && !empty($payload['data']))
                            {
                                $payloadData = $payload['data'];

                                yield $redis->set('socketResData'. $key. time(), json_encode($payload));

                                $this->executeKucoinOrderListenerProcessing($payloadData);
                            }
                        });
                    }
                }

                if(yield $redis->has('laravel_database_handlerRedisFlagKucoin'))
                {
                    yield $redis->set('connectionIsCloseDelete'. time(), true);

                    yield $redis->delete('laravel_database_handlerRedisFlagKucoin');
                    Loop::stop();

                    break;
                }

                yield new Delayed(200);
            }

            $redis->delete('laravel_database_kucoin_listened_exchanges');

//            $blockDetector->stop();

            }
            catch (\Exception $e)
            {
                yield $redis->set('kucoinLoopError' . time(), $e->getMessage());

                $tokenIsExpired = strpos($e->getMessage(), 'token is expired (401)');

                if($tokenIsExpired)
                {
                    $connection = $this->getKucoinSocketConnection();

                    yield $redis->set('kucoinSocketConnection', $connection);

                    if(!empty($connection))
                    {
                        yield $redis->query('hset',
                            'laravel_database_kucoin_socket_connections_elder',
                                $this->exchangeConnection->id, $connection);

                        $orderChangeHandler = new OrderChangeHandlerKucoin($this->exchangeConnection);
                        dispatch($orderChangeHandler);
                    }

                    yield $redis->set('tokenIsExpired' . time(), $e->getMessage());

                    Loop::stop();
                }
            }

        });

    }

    protected function refreshExpiredKucoinFeedTokens($redis)
    {
        yield $redis->set('testrefresh', '123');

        $connection = $this->getKucoinSocketConnection();

        yield $redis->set('kucoinSocketConnection', $connection);
        if(!empty($connection))
        {
            yield $redis->command('hset',
                ['laravel_database_kucoin_socket_connections_elder',
                $this->exchangeConnection->id, $connection]);
        }
    }

    protected function executeKucoinOrderListenerProcessing($listenerData)
    {
        $job = new OrderChangeJobKucoin($listenerData);

        dispatch($job);
    }

}
