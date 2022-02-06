<?php

namespace App\Models;

use Illuminate\Support\Facades\Redis;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

use App\Classes\ActiveOrderUnited;

/**
 * Class ListenerExecutor
 * @package App\Models
 * @todo добавить логгирование в случае не нахождения каких либо
 * обьектов, в дальнейшем нужно добавить повторное нахождение либо
 * грамотную обработку таких случаев
 */
class ListenerExecutor extends Model
{
    use HasFactory;

    protected $activeOrder, $grid;

    protected $lazyProcessing;

    protected function deleteActiveOrder($uniqueOrderId)
    {
        $redis = Redis::connection('default');

        $redisDelResult = $redis->command('hdel', ['active_orders', $uniqueOrderId]);

        if(!$redisDelResult)
        {
            Log::write('debug', 'Активный ордер из redis для удаления не был найден');
        }

        $activeOrder = ActiveOrder::where(['uniqueOrderId' => $uniqueOrderId])->first();

        if(!empty($activeOrder))
        {
            $activeOrder->delete();
        }
        else
        {
            Log::write('debug', 'Активный ордер для удаления не был найден');
        }
    }

    protected function activeOrderProcessing($listenerInfo)
    {
            $this->grid->initConnection();

            if(count($this->grid->activeSellOrders()) === 1)
            {
                $this->grid->stopGrid();
            }

            if(count($this->grid->activeBuyOrders()) === 1)
            {
                $this->grid->stopGrid();
            }

            try
            {
                $orderHistoryRecord = $this->createAndGetOrderHistoryRecord($listenerInfo);

                $orderHistoryRecord->save();
            } catch (\Exception $e)
            {
                Log::write('error', 'Произошла ошибка при попытке сохранить историю ордера', ['error' => $e]);
            }

            $minPriceInDecimal = $this->grid->exchange->getMinPriceForSymbolInDecimalPlaces($this->grid->ticker);


            try {
                $newOrder = $this->formatNewOrder($minPriceInDecimal);
            } catch (\Exception $e)
            {
                Log::write('error','Произошла ошибка при попытке сформировать новый ордер', ['error' => $e]);
            }

            if(!empty($orderHistoryRecord->profit))
            {
                $this->grid->grid_profit += $orderHistoryRecord->profit;

                $this->grid->save();
            }


            try
            {
                if(!empty($newOrder) && $newOrder instanceof Order)
                {
                    if($this->grid->exchange->bookOrder($newOrder)['status'] === 'success')
                    {
                        if($this->grid->saveOrderToActiveOrders($newOrder))
                        {
                            $this->deleteActiveOrder($listenerInfo['uniqueClientId']);
                        }
                    }
                }
            }
            catch (\Exception $e)
            {
                Log::write('error','Произошла ошибка при попытке выставить новый ордер на биржу', ['error' => $e]);
            }
    }

    public function initProcessing($listenerInfo)
    {
        if($this->findAndSetActiveOrderAndGrid(true, $listenerInfo['uniqueClientId']))
        {
            $this->activeOrderProcessing($listenerInfo);
        }
        elseif($this->findAndSetActiveOrderAndGrid(false, $listenerInfo['uniqueClientId']))
        {
            $this->activeOrderProcessing($listenerInfo);
        }
        elseif($this->findAndSetLazyProcessingOrder($listenerInfo['uniqueClientId']))
        {
            if($this->setGridFromLazyProcessingOrder())
            {
                try {
                    $this->grid->initConnection();

                    $orders = $this->grid->getOrders();

                    $this->grid->bookOrders($orders);

                    $this->lazyProcessing->delete();
                }
                catch (\Exception $e)
                {
                    Log::write('debug', 'Ошибка с отложенным запуском', ['error' => $e]);
                }
            }
        }
    }

    protected function formatNewOrder($minPriceInDecimal)
    {
        $order = new Order();

        $order->ticker = $this->grid->ticker;
        $order->price = number_format($this->getNewOrderPrice(), $minPriceInDecimal);
        $order->amount = $this->activeOrder->amount;
        $order->side = $this->activeOrder->side == 'SELL' ? 'BUY' : 'SELL';

        $order->generateAndSetOrderUniqId();

        return $order;
    }

    protected function getNewOrderPrice()
    {
        $newPrice = 0;

        Log::write('debug', 'oldPrice', [$this->activeOrder->price]);


        if($this->activeOrder->side == 'SELL')
        {
            $newPrice = $this->activeOrder->price - $this->grid->priceInterval * 2;
        }
        else
        {
            $newPrice = $this->activeOrder->price + $this->grid->priceInterval * 2;
        }

        Log::write('debug', 'newPrice', [$newPrice]);

        return $newPrice;
    }

    protected function findAndSetActiveOrderAndGrid($isRedis, $uniqueOrderId)
    {
        if($isRedis)
        {
            $redis = Redis::connection('default');

            $activeOrder = $redis->command('hget', ['active_orders', $uniqueOrderId]);

            if(!empty($activeOrder))
            {
                $activeOrder = json_decode($activeOrder, true);

                $this->grid = Grid::where(['id' => $activeOrder['grid_id']])->first();

                $this->activeOrder = new ActiveOrderUnited(
                    $activeOrder['uniqueOrderId'],
                    $activeOrder['grid_id'],
                    $this->grid->ticker,
                    $activeOrder['price'],
                    $activeOrder['amount'],
                    $activeOrder['side'],
                    $activeOrder['sum'],
                );

                return true;
            }
            else
            {
                return false;
            }
        }
        else
        {
            $activeOrder = ActiveOrder::where(['uniqueOrderId' => $uniqueOrderId])->with('grid')->first();

            if(!empty($activeOrder))
            {
                $this->grid = $activeOrder->grid;

                $this->activeOrder = new ActiveOrderUnited(
                    $activeOrder->uniqueOrderId,
                    $activeOrder->grid_id,
                    $activeOrder->symbol,
                    $this->grid->ticker,
                    $activeOrder->amount,
                    $activeOrder->side,
                    $activeOrder->sum,
                );

                return true;
            }
            else
            {
                return false;
            }
        }
    }

    protected function setGridFromActiveOrder()
    {
        $this->grid = Grid::where(['id' => $this->activeOrder->grid_id])->first();

        return !(empty($this->grid));
    }

    protected function findAndSetLazyProcessingOrder($uniqueOrderId)
    {
        $this->lazyProcessing = LazyProcessingOrders::where(['uniqueOrderId' => $uniqueOrderId])->first();

        return !(empty($this->lazyProcessing));
    }

    protected function setGridFromLazyProcessingOrder()
    {
        $this->grid = $this->lazyProcessing->grid;

        return !(empty($this->grid));
    }

    protected function createAndGetOrderHistoryRecord($orderEventData = null)
    {
        $orderHistoryRecord = new OrderHistory();

        if($this->activeOrder->side == 'SELL')
        {
            try {
                if ($this->grid->exchange->exchange_type_id == 3) {
                    $fee = 0.001;
                } else {
                    $fee = $this->grid->exchange->getTradeFee($this->grid->ticker);
                }

                if (!empty($fee)) {
                    $feeSumm = $this->activeOrder->sum * $fee;

                    $lastBuyOrderSum = ($this->activeOrder->price - $this->grid->priceInterval * 2) * $this->activeOrder->amount;

                    $feeSumm += $lastBuyOrderSum * $fee;
                    $orderHistoryRecord->profit = $this->activeOrder->sum - $lastBuyOrderSum - $feeSumm;
                } else {
                    $lastBuyOrderSum = ($this->activeOrder->price - $this->grid->priceInterval * 2) * $this->activeOrder->amount;

                    $orderHistoryRecord->profit = $this->activeOrder->sum - $lastBuyOrderSum;
                }

            } catch (\Exception $e) {
                Log::write('error', 'Произошла ошибка при попытке записать профит в ордер истории', ['error' => $e, 'codeLine' => 198]);
            }
        }
        else
        {
            $orderHistoryRecord->profit         = 0;
        }

        $orderHistoryRecord->uniqueOrderId  = $this->activeOrder->uniqueOrderId;
        $orderHistoryRecord->grid_id        = $this->activeOrder->grid_id;
        $orderHistoryRecord->symbol         = $this->grid->ticker;

        if(!empty($orderEventData))
        {
            $orderHistoryRecord->price          = $orderEventData['price'];
            $orderHistoryRecord->amount         = $orderEventData['filledSize'];
        }
        else
        {
            $orderHistoryRecord->price          = $this->activeOrder->price;
            $orderHistoryRecord->amount         = $this->activeOrder->amount;
        }

        $orderHistoryRecord->side           = $this->activeOrder->side;

        return $orderHistoryRecord;
    }
}
