<?php

namespace App\Http\Controllers;

use Amp\Loop;
use App\Helpers\NumHelper;
use App\Jobs\OrderChangeHandlerKucoin;
use App\Models\ActiveOrder;
use App\Models\Exchange;
use App\Models\ExchangeGlobalFunctionsKucoin;
use App\Models\ExchangeType;
use App\Models\Grid;
use App\Models\LazyProcessingOrders;
use App\Models\ListenTokens;
use App\Models\Socket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\View;
use JavaScript;
use KuCoin\SDK\PrivateApi\WebSocketFeed;
use Lin\Binance\Binance;
use Lin\Binance\BinanceWebSocket;
use Lin\Ku\Kucoin;
use App\Models\Order;

use KuCoin\SDK\PrivateApi\Account;
use KuCoin\SDK\Exceptions\HttpException;
use KuCoin\SDK\Exceptions\BusinessException;
use KuCoin\SDK\Auth;
//use Ratchet\Client\WebSocket;
//use React\EventLoop\Factory;
////use React\EventLoop\Loop;
//use React\EventLoop\LoopInterface;

use Amp;
use Amp\Delayed;
use Amp\Websocket;
use Amp\Websocket\Client;
use function Amp\Websocket\Client\connect;
use function League\Uri\parse;

class IndexController extends Controller
{
    public function priceIncrementInit()
    {
        $url = "https://api.kucoin.com/api/v1/symbols";

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            "Content-Type: application/json",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $time = time();

        $resp = curl_exec($curl);
        curl_close($curl);

        $resp = json_decode($resp, true)['data'];

        foreach($resp as $val)
        {
            Redis::connection('default')->set("SYMBOL-INFO-{$val['symbol']}", json_encode($val));
        }

    }

    public function index()
    {
//        $exchange = Exchange::where(['id' => 2])->first();
//
//        $order = new Order();
//        $order->ticker = 'ICP-USDT';
//        $order->side = 'SELL';
//        $order->type = 'limit';
//        $order->amount = 0.0001;
//        $order->price = 55;
//
//        $order->generateAndSetOrderUniqId();
//
//        $exchange->bookOrder($order);

        ddd('complete');

//        $timeStart = microtime();
//
//        $payload = '{"type":"message","topic":"\/spotMarket\/tradeOrders","userId":"6149a1937013850006886f49","channelType":"private","subject":"orderChange","data":{"symbol":"ICP-USDT","orderType":"limit","side":"sell","orderId":"618c5ce6c735d4000190b55b","liquidity":"taker","type":"match","orderTime":1636588774736761387,"size":"0.001","filledSize":"0.001","price":"44.56","matchPrice":"48.3","matchSize":"0.001","tradeId":"618c5ce67857785082219b56","clientOid":"9918356100","remainSize":"0","status":"match","ts":1636588774736761387}}';
//
////        $payload = json_decode($payload, true);
////
////        if(!empty($payload['subject']) && !empty($payload['data']))
////        {
////            $timeEnd = microtime();
////        }
//
//        if(!empty(strpos($payload,'"subject"')) && !empty(strpos($payload,'"data"')))
//        {
//            $timeEnd = microtime();
//        }
//
//        ddd(['timeStart' => $timeStart, 'timeEnd' => $timeEnd]);
////        $redis = Redis::connection('default');
////
////        $redis->set('handlerRedisFlagKucoin', 1);
////
////        $orderChangeHandler = new OrderChangeHandlerKucoin($exchange);
////        dispatch($orderChangeHandler);
////
////        $test = $redis->command('hexists', ['kucoin_socket_connections_elder',
////            2]);
////
////        $test = $redis->command('hkeys', ['kucoin_socket_connections_elder']);
////
////        ddd($test);
////
////        ddd('успешно запущен');
//        return redirect(route('grids'));
//
//        $grid = Grid::where(['id' => 422])->first();
//        $grid->initConnection();
//        $grid->getOrders();
//        return redirect(route('grids'));

//        $lpOrder = LazyProcessingOrders::where(['id' => 6])->first();
//
//        $lpOrder->grid->initConnection();
//        ddd($lpOrder->grid->getOrders());

        $config = Amp\Redis\Config::fromUri('redis://null:SSvuLr22ji6HRSc@localhost:6379');

        $redis = new Amp\Redis\Redis(new Amp\Redis\RemoteExecutor($config));


        $urls = [
            'wss://stream.binance.com:9443/ws/btcusdt@kline_1m',
            'wss://stream.binance.com:9443/ws/ethudst@kline_1m'
        ];

        $list = array();
//        $blockDetector = new BlockDetector(function ($time) use($redis) {
//            $redis->set('block_detector_log_' . uniqid(), $time);
//        });

        Loop::run(function ($id) use ($urls, $redis, &$list){
//            $blockDetector->start();
            /** @var Client\Connection[] $connections */

            while(true)
            {
                $res = yield $redis->query('lpop', 'redisTestList');

                if($res == null)
                {
                    break;
                }

                $list[] = json_decode($res,true);
            }

//            yield $redis->set('loopId' . time(), $id);
//
//            yield $redis->set('loopIsStarted' . time(), 1);
//            Log::debug('loopIsStarted', ['loopStarted' => $urls]);
            $connectionMessage = [
                'type' => 'subscribe',
                'topic'=> '/spotMarket/tradeOrders',
                'privateChannel'=>true,
                'response'=>true
            ];

//            foreach ($urls as $key => $url)
//            {
////                yield $redis->set('url' . $key . time(), 1);
//
//                $connect = yield connect($url);
//
////                $connect->send(json_encode($connectionMessage));
//
////                Amp\Loop::repeat(10000, function () use($redis, $connect)
////                {
////                    $ping = json_encode(['type' => 'ping']);
////
////                    $connect->send($ping);
////                });
//
//                $connections[] = ['connection' => $connect, 'promiseIsReceived' => false];
//            }
//
//            $i = 0;
//            $a = 0;
//
//            $connectionsL = count($connections);
//
//            /** @var Websocket\Message $message */
//            while (true)
//            {
//                foreach ($connections as $key => $connection)
//                {
//
//                    Amp\asyncCall(function () use (&$connection, $redis, &$i){
//                        if(!$connection['promiseIsReceived'])
//                        {
//                            $message = yield $connection['connection']->receive();
//
//                            $connection['promiseIsReceived'] = true;
//
//                            if(!empty($message))
//                            {
//                                $payload = yield $message->buffer();
//
//                                if(!empty($payload))
//                                {
////                                    $connection['promiseIsReceived'] = false;
//
//                                    yield $redis->set('tes' . '_' . time(), $payload);
//                                }
//                            }
//
////                            $payload = yield $message->buffer();
////
////                            $connection['promiseIsReceived'] = true;
////
////                            yield $redis->set('payloadTimer' . '_' . time(), $payload);
//                        }
//
//
////                        $message->onResolve(function ($res, $tes) use($redis){
////                            $payload = yield $tes->buffer();
////
////                            yield $redis->set('tes' . '_' . time(), $payload);
////                        });
//
//
////                        yield $redis->set('unsortedPayload' . '_' . time(), $payload);
//
//
////                        $payload->onResolve(function ($res, $payload) use ($redis)
////                        {
////                            yield $redis->set('unsortedPayload' . '_' . time(), $payload);
////                        });
//                    });
//
//
////                        yield $redis->set('unsortedPayload' . $i . '_' . time(), $payload);
//                }
//
////                foreach ($connections as $key => $connection)
////                {
////                    $message = yield $connection->receive();
////
////                        $payload = yield $message->buffer();
////
//////                        $payload->onResolve(function ($res, $payload) use ($redis) {
//////                            if(empty($res))
//////                            {
////                                yield $redis->set('unsortedPayload' . time(), $payload);
////
//////                                Amp\asyncCall(function () use ($redis, $payload){
//////
//////                                    yield $redis->set('sortedPayload' . time(), $payload);
////
//////                            $payload = json_decode($payload,true);
//////
//////                            if(!empty($payload['type']))
//////                            {
//////                                if($payload['type'] !== 'welcome' && $payload['type'] !== 'pong')
//////                                {
//////                                    yield $redis->set('socketResOther'. time(), json_encode($payload));
//////                                }
//////                                elseif($payload['type'] === 'welcome')
//////                                {
//////                                    yield $redis->set('socketResWelcome'. time(), json_encode($payload));
//////                                }
//////                            }
//////
//////                            if(!empty($payload['subject']) && !empty($payload['data']))
//////                            {
//////                                $payloadData = $payload['data'];
//////
//////                                yield $redis->set('socketResData'. time(), json_encode($payload));
//////
//////                                $this->executeKucoinOrderListenerProcessing($payloadData);
//////                            }
//////                                });
////
//////                            }
//////                        });
////
//////                        Amp\asyncCall(function () use ($redis, $payload){
//////
//////                            yield $redis->set('sortedPayload' . time(), $payload);
//////
////////                            $payload = json_decode($payload,true);
////////
////////                            if(!empty($payload['type']))
////////                            {
////////                                if($payload['type'] !== 'welcome' && $payload['type'] !== 'pong')
////////                                {
////////                                    yield $redis->set('socketResOther'. time(), json_encode($payload));
////////                                }
////////                                elseif($payload['type'] === 'welcome')
////////                                {
////////                                    yield $redis->set('socketResWelcome'. time(), json_encode($payload));
////////                                }
////////                            }
////////
////////                            if(!empty($payload['subject']) && !empty($payload['data']))
////////                            {
////////                                $payloadData = $payload['data'];
////////
////////                                yield $redis->set('socketResData'. time(), json_encode($payload));
////////
////////                                $this->executeKucoinOrderListenerProcessing($payloadData);
////////                            }
//////                        });
////
////
////
////                }
//
//                if(yield $redis->has('laravel_database_handlerRedisFlagTest'))
//                {
//                    yield $redis->set('connectionIsCloseDelete'. time(), true);
//
//                    yield $redis->delete('laravel_database_handlerRedisFlagTest');
//                    Loop::stop();
//
//                    break;
//                }
//
//                yield new Delayed(2500);
//            }

//            $blockDetector->stop();

        });

        ddd($list);
        return redirect(route('grids'));
        ini_set('max_execution_time', 0);
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);

        $grid = Grid::where(['id' => 371])->first();

        $stopPrice = $grid->lastSellOrder()->price;

        $orders = $grid->getOrdersByParams(0.1,
            $grid->lastBuyOrder()->price,
            $stopPrice,
            1
        );

//        $stopPrice = $grid->exchange->getTickerCurrentPrice($grid->ticker);
//
//        $orders = $grid->getOrdersByParams(0.01,
//            $grid->lastBuyOrder()->price,
//            $stopPrice,
//            -1
//        );

        $grid->exchangeConnection = $grid->exchange;


        ddd($grid->bookOrders($orders));



//        $exchange = auth()->user()->getExchangeById(15);

//        ddd($exchange->getOpenOrders());
        if(auth()->user())
        {


//            ddd($exchange->getTradeFee('BNBUSDT'));
        }


//        $activeOrders = ActiveOrder::where(['grid_id' => 238])->get();
//
//        $sellOrdersSum = 0;
//        $buyOrdersSum = 0;
//
//        foreach ($activeOrders as $activeOrder)
//        {
//            if($activeOrder->side === 'SELL')
//            {
//                $sellOrdersSum += $activeOrder->sum;
//            }
//            else
//            {
//                $buyOrdersSum += $activeOrder->sum;
//            }
//        }
//
//        ddd(['sellOrdersSum' => $sellOrdersSum, 'buyOrdersSum' => $buyOrdersSum]);

//        ListenTokens::refreshBinanceListenKeys();
//        ddd($exchange->getOpenOrders('BNBUSDT'));
//        ddd($exchange->getMinPriceForSymbolInDecimalPlaces('BNBUSDT'));
//        ddd($exchange->getAccountInfo());
//
        ddd($exchange->deleteOpenOrdersBySymbol());
//
//        $listenKey = $exchange->createEvent()['listenKey'];
//
//        $connectionLink = "wss://stream.binance.com:9443/stream?streams={$listenKey}";
//        $socket = new Socket();
//        $socket->initSocketConnection($connectionLink);
//
//        $now = time();
//
//        while (true)
//        {
//            $responseObjFromBinance = $socket->getSocketResponse($connectionLink);
//
//            Log::write('debug', json_encode($responseObjFromBinance));
////
////            ddd(json_encode($responseObjFromBinance));
//
////            if(time() - $now > 123)
////            {
////
//////                Log::write('debug', json_encode($responseObjFromBinance));
////            }
//        }
//
//        ddd('success');

//        $socket = new WebSocket();
//
//        $socket->on('receive', function($client, $data){
//            Log::write('debug', json_encode($data));
//        });
//        ddd($listenKey);
//
//        $binance = new BinanceWebSocket();
//
//        $binance->config([
//            'log' => true,
//            'data_time' => 1,
//            'global'=>'127.0.0.1:2208',
//            'baseurl' => "ws://stream.binance.com:9443/stream?streams={$listenKey}"
//        ]);
//
//        $binance->keysecret([
//            'key'=>'OxPRsnec733uiCO1zw7Apwrv45KkxY6py0AexLGGYA5pwouBD6hLFgJvk8aTIf6B',
//            'secret'=>'F6TgygytQ03oytynk5JDoTsuscZ34cs3gH720DCTT6yEmUCLrg8tsD3TV6pHKLdT',
//        ]);
//
//        $binance->subscribe();
//
//        $binance->getSubscribes(function($data){
//            Log::write('debug', json_encode($data));
//        }, true);


//        ddd($binance->system()->getAllTickers());
//        return view('index');
    }
    
    public function grids()
    {
        
    }

    public function gridCreating(Request $req)
    {
        $tickers = [];

        $exchanges = auth()->user()->exchanges;

//        ddd($exchanges);


        if(empty($req->exchange_id))
        {
            return view('grid.create', compact('tickers', 'exchanges'));
        }

        $exchange = auth()->user()->getExchangeById($req->exchange_id);

        switch ($exchange->exchange_type_id)
        {
            case 1: {
                $binance = new Binance();

                $tickers = $binance->system()->getAllTickers();

                break;
            }
            case 2: {
                $kucoin = new Kucoin();

                $tickers = $kucoin->market()->getAllTickersFiltered();

                break;
            }
            case 3: {
                $binance = new Binance();

                $tickers = $binance->system()->getAllTickers();

                break;
            }
            case 4: {
                $kucoin = new Kucoin();

                $tickers = $kucoin->market()->getAllTickersFiltered();

                break;
            }
        }

        return Response(['status' => 'success', 'exchange_type' => $exchange->exchange_type_id,'exchange_id' => $req->exchange_id, 'tickers' => $tickers]);
    }

    public function exchangeCreating()
    {

        return view('exchange.create');

//        if(empty($req->exchange_id))
//        {
//            return view('grid.create', compact('tickers', 'exchanges'));
//        }
//
//        switch ($req->exchange_id)
//        {
//            case 1: {
//                $binance = new Binance();
//
//                $tickers = $binance->system()->getAllTickers();
//
//                break;
//            }
//            case 2: {
//                $kucoin = new Kucoin();
//
//                $tickers = $kucoin->market()->getAllTickersFiltered();
//
//                break;
//            }
//        }
//
//        return Response(['status' => 'success', 'exchange_id' => $req->exchange_id, 'tickers' => $tickers]);
    }
}
