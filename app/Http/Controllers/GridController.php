<?php

namespace App\Http\Controllers;

use App\Jobs\OrderChangeHandlerKucoin;
use App\Models\ActiveOrder;
use App\Models\Exchange;
use App\Models\Grid;
use App\Models\OrderHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use Itstructure\GridView\DataProviders\EloquentDataProvider;

class GridController extends Controller
{
    public function get()
    {
        $dataProvider = new EloquentDataProvider(Grid::where(['user_id' => auth()->user()->getAuthIdentifier(), 'is_active' => 1]));
        $archiveGridDataProvider = new EloquentDataProvider(Grid::where(['user_id' => auth()->user()->getAuthIdentifier(), 'is_active' => 0]));

        $userGrids = Grid::where(['user_id' => auth()->user()->getAuthIdentifier(), 'is_active' => 1])->get();
        $activeOrdersBuy = 0;
        $activeOrdersSell = 0;
        $commonGridProfit = 0;

        foreach($userGrids as $grid)
        {
            $commonGridProfit += $grid->grid_profit;
            $activeOrdersBuy += $grid->activeBuyOrdersCount();
            $activeOrdersSell += $grid->activeSellOrdersCount();
        }

        return view('grid.index', [
            'commonGridProfit' => $commonGridProfit,
            'dataProvider' => $dataProvider,
            'archiveGridDataProvider' => $archiveGridDataProvider,
            'activeOrdersBuy' => $activeOrdersBuy,
            'activeOrdersSell' => $activeOrdersSell
        ]);
    }

    public function getArchive()
    {
        $archiveGridDataProvider = new EloquentDataProvider(Grid::where(['user_id' => auth()->user()->getAuthIdentifier(), 'is_active' => 0]));

        $userGrids = Grid::where(['user_id' => auth()->user()->getAuthIdentifier(), 'is_active' => 0])->get();
        $commonGridProfit = 0;

        foreach($userGrids as $grid)
        {
            $commonGridProfit += $grid->grid_profit;
        }

        return view('grid.archive', [
            'commonGridProfit' => $commonGridProfit,
            'archiveGridDataProvider' => $archiveGridDataProvider,
        ]);
    }

    public function reload()
    {
        $exchange = Exchange::where(['id' => 2])->first();
        \Illuminate\Support\Facades\Redis::set('handlerRedisFlagKucoin', 1);

        $orderChangeHandler = new OrderChangeHandlerKucoin($exchange);
        dispatch($orderChangeHandler);

        ddd('Очередь запущена');
    }

    public function view(Request $req)
    {
        $grid           = Grid::where(['id' => $req->grid_id])->first();
        $gridStartTime  = strtotime($grid->start_at);

        $orderHistoriesQty24     = $grid->orderHistories->where('created_at', '>', date('d-m-y H:i:s', strtotime('-24 hours')))->count();
        $orderHistoriesQty       = $grid->orderHistories->count();
        $gridProfit              = $grid->grid_profit;
        $activeOrdersQty         = $grid->activeOrders->count();


        $openSellOrders = ActiveOrder::where(['grid_id' => $req->grid_id, 'side' => 'SELL'])->orderBy('price', 'ASC')->get();
        $openBuyOrders = ActiveOrder::where(['grid_id' => $req->grid_id, 'side' => 'BUY'])->orderBy('price', 'DESC')->get();

        return view('grid.view', compact('openBuyOrders', 'openSellOrders', 'gridStartTime', 'gridProfit', 'orderHistoriesQty24',
            'orderHistoriesQty', 'activeOrdersQty'));
    }

    public function create(Request $req)
    {
        ini_set('max_execution_time', 300);

        $validatorRules = [
            'ticker'        => 'required|string',
            'exchange_id'   => 'required|int',
            'bot_name'      => 'required|string',
            'lowest_price'  => 'required',
            'highest_price' => 'required',
            'investments'   => 'required|numeric',
            'order_qty'     => 'required|integer|min:10',
            'currency_used' => 'required|string',
            'alt_used' => 'required',
//            'stop_price'    => 'numeric',
//            'start_price'   => 'numeric'
        ];


        $validatorMessages = [
            'min' => 'Можно добавить не менее :min ордеров'
        ];

        $values = $req->all();

//        $values['interval'] = ;
//        $values['lowest_price'] = $values['lowest_price'];
//        $values['highest_price'] = $values['highest_price'];
//        $values['stop_price'] = (double)$values['start_price'];
        $values['alt_used'] = $values['alt_used'] == 'true' ? $values['alt_used'] = true : false;
        $values['user_id'] = auth()->user()->getAuthIdentifier();
        $values['created_at'] = date('Y-m-d H:i:s');
        $values['updated_at'] = date('Y-m-d H:i:s');

        $assetUsageWarnedValue = (bool)$values['assetUsageWarned'];
        $gridId = $values['grid_id'];

        unset($values['assetUsageWarned']);
        unset($values['grid_id']);
        unset($values['_token']);
        unset($values['exchange_type']);

        if(empty($values['start_price']))
        {
            $values['start_price'] = 0;
        }
        else
        {
            $values['is_deferred'] = true;
        }


        $validator = Validator::make($values, $validatorRules,
            $validatorMessages
        );

        if($validator->fails())
        {
            return ['status' => 'error', 'errors' => $validator->errors()];
        }

        try
        {
            if(empty($assetUsageWarnedValue))
            {

                $grid = Grid::create($values);
            }
            else
            {
                $grid = Grid::where(['id' => $gridId])->first();
            }


            $grid->exchangeConnection = Exchange::find($values['exchange_id']);

            $grid->normalizePrice();

            if($grid->is_deferred)
            {
                $isLazyLoading = true;
            }
            else
            {
                $isLazyLoading = false;
            }
            if(empty($assetUsageWarnedValue))
            {
                $gridOperationRes = $grid->putGridIntoOperation($isLazyLoading);
            }
            else
            {
                $gridOperationRes = $grid->putGridIntoOperation($isLazyLoading, true);
            }


        }
        catch (\Exception $e)
        {
            Log::write('critical', 'Ошибка при добавлении сетки', ['error' => $e]);

            if(!empty($grid))
            {
                $grid->delete();
            }

            return ['status' => 'fail', 'title' => 'Ошибка', 'msg' => 'При добавлении сетки произошла ошибка'];
        }

        if(!empty($gridOperationRes))
        {
            return Response($gridOperationRes);
        }

        return ['status' => 'success', 'title' => 'Успех', 'msg' => 'Сетка успешно добавлена и запущена!'];
    }

    public function update(Request $req)
    {

    }

    public function archive(Request $req)
    {
        $this->checkGridId($req);

        $grid = Grid::where(['id' => $req->grid_id])->with('activeOrders')->first();

        if(!empty($grid))
        {
            $grid->initConnection();
            $grid->stopGrid();
        }
        else
        {
            session()->flash('grid_status_fail', 'Сетка не найдена!');
        }

        try {
            $grid->archive();

            session()->flash('grid_status_success', 'Сетка была успешно заархивирована');
            return redirect(route('grids'));
        }
        catch (\Exception $e)
        {
            session()->flash('grid_status_fail', 'Во время архивации сетки произошла ошибка, попробуйте снова!');

            Log::write('error', 'Произошла ошибка', ['error' => $e]);
            return redirect(route('grids'));
        }
    }

    public function delete(Request $req)
    {
        $this->checkGridId($req);

        $grid = Grid::where(['id' => $req->grid_id])->with('activeOrders')->first();

        if(!empty($grid))
        {
            $grid->initConnection();
            $grid->stopGrid();
        }
        else
        {
            session()->flash('grid_status_fail', 'Сетка не найдена!');
        }

        try {
            $grid->delete();

            session()->flash('grid_status_success', 'Сетка была успешно удалена');
            return redirect(route('grids'));
        }
        catch (\Exception $e)
        {
            session()->flash('grid_status_fail', 'Во время удаления сетки произошла ошибка, попробуйте снова!');

            Log::write('error', 'Произошла ошибка', ['error' => $e]);
            return redirect(route('grids'));
        }
    }

    public function history(Request $req)
    {
        $this->checkGridId($req);

        try {
            $orderHistories = OrderHistory::where(['grid_id' => $req->grid_id])->get();

            return view('grid.history', compact('orderHistories'));
        }
        catch (\Exception $e)
        {
            session()->flash('grid_status_fail', 'Во время получения ордеров произошла ошибка, попробуйте снова!');

            Log::write('error', 'Произошла ошибка', ['error' => $e]);
            return redirect(route('grids'));
        }
    }

    protected function checkGridId($req)
    {
        if(empty($req->grid_id))
        {
            session()->flash('grid_status_fail', 'Id сетки обязателен');
            return redirect(route('grids'));
        }
    }

}
