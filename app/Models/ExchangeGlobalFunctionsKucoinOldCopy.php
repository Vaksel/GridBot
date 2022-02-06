<?php

namespace App\Models;

use Amp;
use Amp\Delayed;
use Amp\Loop;
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

class ExchangeGlobalFunctionsKucoin extends Model
{
    use HasFactory;

    public $exchangeConnection;

    public function __construct($exchangeConnection)
    {
        $this->exchangeConnection = $exchangeConnection;
    }

    public function orderHandler()
    {
        ini_set('max_execution_time', 0);
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);

        sleep(15);

        $redis = Redis::connection('default');

        $grids = $this->getAllKucoinGrids();
        $gridsAlreadyInProcessing = $this->exchangeConnection->getAlreadyListenedExchanges();

        $processGridRes = $this->processGrids($grids, $gridsAlreadyInProcessing);
        $symbols = $processGridRes['gridsInfo'];

        Log::write('debug', 'processGridRes', ['res' => $processGridRes]);
        if($processGridRes['needRefresh'])
        {
            $redis->set('kucoin_listened_exchanges', json_encode($symbols));
            $exchanges = $this->getExchangesByIds($exchangeIds = array_keys($symbols));
            $socketConnections = $this->initializeSocketAuthConnections($exchanges);


            $websocketInfo = $this->getWebsocketInfo($socketConnections);

            Log::write('debug', 'socketUrls', ['res' => $websocketInfo['socketUrls']]);

            if($redis->exists('handlerRedisFlagKucoin'))
            {
                if($redis->del('handlerRedisFlagKucoin'))
                {
                    $socketResult = $this->startSocketLoopForEventOrder($websocketInfo['socketUrls'], $websocketInfo['pingInterval'], $symbols);
                }
            }
            else
            {
                $socketResult = $this->startSocketLoopForEventOrder($websocketInfo['socketUrls'], $websocketInfo['pingInterval'], $symbols);
            }
        }
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


    protected function startSocketLoopForEventOrder($urls, $pingInterval, $symbols)
    {
        $startTime = time();

        Log::write('debug', 'startTime', ['startTime' => $startTime]);

        $config = Amp\Redis\Config::fromUri('redis://null:SSvuLr22ji6HRSc@localhost:6379');

        $redis = new Amp\Redis\Redis(new Amp\Redis\RemoteExecutor($config));

//        $blockDetector = new BlockDetector(function ($time) use($redis) {
//            $redis->set('block_detector_log_' . uniqid(), $time);
//        });

        Loop::run(function () use ($urls, $pingInterval, $startTime, $symbols, $redis){
//            $blockDetector->start();
            /** @var Client\Connection[] $connections */


            yield $redis->set('loopIsStarted' . time(), 1);
//            Log::debug('loopIsStarted', ['loopStarted' => $urls]);
            $connectionMessage = [
                'type' => 'subscribe',
                'topic'=> '/spotMarket/tradeOrders',
                'privateChannel'=>true,
                'response'=>true
            ];

            $ping = json_encode(['type' => 'ping']);

            foreach ($urls as $key => $url)
            {
                yield $redis->set('url' . $key . time(), 1);

                $connect = yield connect($url);

                yield $connect->send(json_encode($connectionMessage));

                Amp\Loop::repeat(10000, function () use($redis, $connect, $ping)
                {
                    yield $connect->send($ping);
                });

                $connections[] = $connect;

                Amp\Loop::repeat(30000, function () use($redis, $key, &$connect, $url, $ping, $connectionMessage, &$connections)
                {
                    if(!$connect->isConnected())
                    {
                        yield $redis->set('connectionRestarted' . $key . time(), 1);

                        $connect = yield connect($url);

                        yield $connect->send(json_encode($connectionMessage));

                        Amp\Loop::repeat(10000, function () use($redis, $connect, $ping)
                        {
                            yield $connect->send($ping);
                        });
                        $connections[$key] = $connect;
                    }
                });

            }

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

                            if(!empty($payload['type']))
                            {
                                if($payload['type'] !== 'welcome' && $payload['type'] !== 'pong')
                                {
                                    yield $redis->set('socketResOther'. $key. time(), json_encode($payload));
                                }
                                elseif($payload['type'] === 'welcome')
                                {
                                    yield $redis->set('socketResWelcome'. $key. time(), json_encode($payload));
                                }
                            }

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

                yield new Delayed(1000);
            }

            $redis->delete('laravel_database_kucoin_listened_exchanges');

//            $blockDetector->stop();

        });


    }

    protected function executeKucoinOrderListenerProcessing($listenerData)
    {
        $job = new OrderChangeJobKucoin($listenerData);

        dispatch($job);
    }

}
