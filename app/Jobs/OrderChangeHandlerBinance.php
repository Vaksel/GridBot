<?php

namespace App\Jobs;

use App\Models\Exchange;
use App\Models\ExchangeGlobalFunctionsBinance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class OrderChangeHandlerBinance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $exchangeConnection;

    /**
     * Create a new job instance.
     *
     * @param Exchange $exchangeConnection - подключение к бирже
     * @return void
     */
    public function __construct(Exchange $exchangeConnection)
    {
        $this->onQueue('binance_order_listener_pusher');
        $this->exchangeConnection = $exchangeConnection;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $exGlobalFunction = new ExchangeGlobalFunctionsBinance($this->exchangeConnection);
        $exGlobalFunction->orderHandler();
    }
}
