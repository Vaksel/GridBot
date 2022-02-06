<?php

namespace App\Models;

use App\Jobs\OrderChangeJobBinance;
use App\Jobs\OrderChangeJobKucoin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ListenTokens extends Model
{
    public static function refreshBinanceListenKeys()
    {
        $redis = Redis::connection('default');

        $listenKeys = $redis->get('binance_listen_keys');


        $isListenerDemonNeedReload = false;
        $exchange = null;

        if(!empty($listenKeys))
        {
            $listenKeys = json_decode($listenKeys, true);

            Log::write('debug', 'listenKeys', ['listenKeys' => $listenKeys]);


            foreach ($listenKeys as $exchangeId => &$key)
            {
                if($key['expires_at'] - time() < 900)
                {
                    $res = self::findExchangeAndReloadListenKey($exchangeId, $key);

                    Log::write('debug', 'res', ['res' => $res]);

                    if($res['status'] === 'postSuccess')
                    {
                        $key['key'] = $res['listenKey'];
                        $isListenerDemonNeedReload = true;

                        if(!empty($exchange))
                        {
                            $exchange = Exchange::where(['id' => $exchangeId])->first();
                        }
                    }

                    $key['expires_at'] = time() + 2700;
                }
            }

            $listenKeys = json_encode($listenKeys);

            Log::write('debug', 'listeNkeys', ['listeNkeys' => $listenKeys]);

            $redis->set('binance_listen_keys', $listenKeys);

            if($isListenerDemonNeedReload)
            {
                Grid::refreshTickerSubscriptionBinanceStatic($exchange);
            }
        }

        return true;
    }

    protected static function findExchangeAndReloadListenKey($exchangeId, $listenKey)
    {
        $ex = Exchange::where(['id' => $exchangeId])->first();

        $reloadRes = $ex->reloadEvent($listenKey['key']);
        //Может быть ситуация когда ключ уже недоступен, тогда получаем новый ключ
        //
        if(!empty($reloadRes['listenKey']))
        {
            return ['status' => 'postSuccess', 'key' => $reloadRes['listenKey']];
        }
        else
        {
            return ['status' => 'putSuccess'];
        }
    }
}