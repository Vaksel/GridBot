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

class OrderChangeJobBinance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $listenerData;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($listenerData)
    {
        $this->onQueue('binance_order_listener_job');

        $this->listenerData = $listenerData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $formattedListenerOutput = Exchange::formatBinanceListenerOutput($this->listenerData);

        $listenerExecutor = new ListenerExecutor();

        $listenerExecutor->initProcessing($formattedListenerOutput);
    }
}
