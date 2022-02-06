<?php

namespace App\Jobs;

use App\Models\Exchange;
use App\Models\ListenerExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class OrderChangeJobKucoin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $listenerData;
    protected $key;

    public function __construct($listenerData, $key)
    {
        $this->onQueue('kucoin_order_listener_job');

        $this->listenerData = $listenerData;
        $this->key = $key;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $redis = Redis::connection('default');

        $payload = $this->listenerData;
        $payloadDecoded = json_decode($payload, true);
        $payloadData = $payloadDecoded['data'];

        if(!empty($payloadData['symbol']) && !empty($payloadData['price']) && !empty($payloadData['status']))
        {
            $redis->set('socketResData'. $this->key. $payloadData['symbol'] . time() . $payloadData['price'] . $payloadData['status'], $payload, 'EX', 36000);
        }
        else
        {
            $redis->set('socketResData'. $this->key . time(), $payload, 'EX', 36000);
        }

        $formattedListenerOutput = Exchange::formatKucoinListenerOutput($payloadData);

        if(!empty($formattedListenerOutput))
        {
            if(
                ($formattedListenerOutput['executionType'] === 'filled'
                    || $formattedListenerOutput['executionType'] === 'match') &&
                ($formattedListenerOutput['orderStatus'] === 'done'
                    || $formattedListenerOutput['orderStatus'] === 'match'))
            {
                $listenerExecutor = new ListenerExecutor();

                $listenerExecutor->initProcessing($formattedListenerOutput);
            }
        }
    }
}
