<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public $amount = 0, $orderUniqId = null, $ticker, $price;

    /**
     * @var $side - может быть SELL или BUY
     */
    public $side;

    /**
     * @var $type - может быть LIMIT, MARKET, STOP_LOSS, STOP_LOSS_LIMIT, TAKE_PROFIT, TAKE_PROFIT_LIMIT, LIMIT_MAKER
     */
    public $type = 'LIMIT';

    /**
     * @var $actionTime - может быть GTC («действующий до отмены»), IOC («исполнить или отменить»), FOK («исполнить или аннулировать»)
     */
    public $actionTime = 'GTC';

    public const IS_BUY_ORDER = -1;
    public const IS_SELL_ORDER = 1;

    public const IS_LIMIT_TYPE = 'LIMIT';
    public const IS_MARKET_TYPE = 'MARKET';
    public const IS_STOP_LOSS_TYPE = 'STOP_LOSS';
    public const IS_LIMIT_MAKER_TYPE = 'STOP_LOSS_LIMIT';
    public const IS_TAKE_PROFIT_TYPE = 'TAKE_PROFIT';
    public const IS_TAKE_PROFIT_LIMIT_TYPE = 'TAKE_PROFIT_LIMIT';

    public const IS_GTC_ACTION_TIME = 'GTC';
    public const IS_IOC_ACTION_TIME = 'IOC';
    public const IS_FOK_ACTION_TIME = 'FOK';

    public function generateAndSetOrderUniqId()
    {
        $this->orderUniqId = rand(10000,99999).rand(10000,99999);
    }

}
