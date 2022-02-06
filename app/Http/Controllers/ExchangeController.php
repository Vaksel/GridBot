<?php


namespace App\Http\Controllers;


use App\Models\Exchange;
use App\Models\Grid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

use Itstructure\GridView\DataProviders\EloquentDataProvider;

class ExchangeController extends Controller
{
    public function get()
    {
        $dataProvider = new EloquentDataProvider(Exchange::where(['user_id' => auth()->user()->getAuthIdentifier()]));
        return view('exchange.index', [
            'dataProvider' => $dataProvider
        ]);
    }

    public function getCurrentPriceForSymbol(Request $req)
    {
        $validatorRules = [
            'ticker'        => 'required|string',
            'exchange_id'   => 'required|int',
        ];

        $values = $req->all();
        unset($values['_token']);

        $validator = Validator::make($values, $validatorRules);

        if($validator->fails())
        {
            return ['status' => 'error', 'errors' => $validator->errors()];
        }

        $model = Exchange::where(['id' => $req->exchange_id])->first();

        if(!empty($model))
        {
            $price = $model->getTickerCurrentPrice($req->ticker);

            if(!empty($price))
            {
                return ['status' => 'success', 'price' => (string)$price];
            }
            else
            {
                return ['status' => 'fail', 'msg' => 'Получить цену для тикера не удалось'];
            }
        }
        else
        {
            return ['status' => 'fail', 'msg' => 'Получить цену для тикера не удалось'];
        }
    }

    public function getCoinInfo(Request $req)
    {
        $redis = Redis::connection('default');

        if($req->exchange_type == 2 || $req->exchange_type === 4)
        {
            $tickerInfo = $redis->get("SYMBOL-INFO-{$req->ticker}");

            if(!empty($tickerInfo))
            {
                $tickerInfo = json_decode($tickerInfo, true);
            }

            return $tickerInfo;
        }

        return false;
    }

    public function create(Request $req)
    {
        $validatorRules = [
            'name'              => 'required|string',
            'api_key'           => 'required|string',
            'api_secret'        => 'required|string',
            'exchange_type_id'  => 'required|string',
        ];


        $values = $req->all();


//        $values['interval'] = ;
        $values['user_id'] = auth()->user()->getAuthIdentifier();
        $values['is_active'] = true;
        $values['created_at'] = date('Y-m-d H:i:s');
        $values['updated_at'] = date('Y-m-d H:i:s');


        unset($values['_token']);

        $validator = Validator::make($values, $validatorRules);

//        if(!empty($validator->errors()))
////        {
////            return ['status' => 'fail', 'msg' => json_encode($validator->getMessageBag())];
////        }
        try {
            $validator->validate();
        } catch (\Exception $e)
        {
            return ['status' => 'fail', 'msg' => (string)$e];
        }


        try
        {
            $exchange = Exchange::create($values);

            try {
                $exchangeInfoIsValid = $exchange->checkConnection();

                if($exchangeInfoIsValid['status'] === 'success')
                {
                    return ['status' => 'success', 'msg' => 'Биржа успешно добавлена!'];
                }
                else
                {
                    $exchange->delete();
                    return ['status' => 'fail', 'msg' => 'Биржа не была добавлена, проверьте правильность ввода данных!'];
                }
            } catch (\Exception $e)
            {
                $exchange->delete();

                Log::write('error', 'При добавлении биржи произошла ошибка!', ['error' => $e]);

                return ['status' => 'fail', 'msg' => 'Биржа не была добавлена, проверьте правильность ввода данных!'];
            }



        }
        catch (\Exception $e)
        {
            Log::write('debug', 'При добавлении биржи произошла ошибка!', ['error' => $e]);

            return ['status' => 'fail', 'msg' => 'При добавлении биржи произошла ошибка!'];
        }
    }

    public function update(Request $req)
    {

    }

    public function delete(Request $req)
    {
        if(empty($req->exchange_id))
        {
            session()->flash('exchange_status_fail', 'Id биржи обязателен');
            return redirect(route('exchanges'));
        }

        try {
            Exchange::where(['id' => $req->exchange_id])->delete();

            session()->flash('exchange_status_success', 'Биржа была успешно удалена');
            return redirect(route('exchanges'));
        }
        catch (\Exception $e)
        {
            session()->flash('exchange_status_fail', 'Во время удаления биржи произошла ошибка, попробуйте снова! ' . $e);
            return redirect(route('exchanges'));

        }
    }
}