<?php

namespace App\Jobs;

use App\Models\Exchange;
use App\Models\ExchangeGlobalFunctionsKucoin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lin\Ku\Kucoin;

class OrderChangeHandlerKucoin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $exchangeConnection;

    /**
     * Create a new job instance.
     *
     * @param Exchange $exchange
     */
    public function __construct(Exchange $exchangeConnection)
    {
        $this->onQueue('kucoin_order_listener_pusher');
        $this->exchangeConnection = $exchangeConnection;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $exGlobalFunction = new ExchangeGlobalFunctionsKucoin($this->exchangeConnection);
        $exGlobalFunction->startKucoinSocketConnectionManually();
    }
}
