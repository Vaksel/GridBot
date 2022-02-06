<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeType extends Model
{
    use HasFactory;

    public const BINANCE_EXCHANGE = 1;
    public const KUCOIN_EXCHANGE = 2;
    public const BINANCE_EXCHANGE_TEST = 3;
    public const KUCOIN_EXCHANGE_TEST = 4;
}
