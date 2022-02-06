<?php

use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\GridController;
use App\Http\Controllers\IndexController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [IndexController::class, 'index']);

Route::get('/manual-reload', [GridController::class, 'reload']);

Route::get('/price-inc', [IndexController::class, 'priceIncrementInit']);

Route::get('/get-orders', function(){
    ddd(123);
});


Route::get('/grids', [GridController::class, 'get'])->middleware(['auth'])->name('grids');

Route::get('/grids-archive', [GridController::class, 'getArchive'])->middleware(['auth'])->name('archiveGrids');

Route::get('/grid-creating', [IndexController::class, 'gridCreating'])->middleware(['auth'])->name('gridCreating');

Route::post('/grid-creating', [IndexController::class, 'gridCreating'])->middleware(['auth']);

Route::post('/grid-create', [GridController::class, 'create'])->middleware(['auth'])->name('createGrid');

Route::get('/grid-delete/{grid_id}', [GridController::class, 'delete'])->middleware(['auth'])->name('deleteGrid');

Route::get('/grid-archive/{grid_id}', [GridController::class, 'archive'])->middleware(['auth'])->name('archiveGrid');

Route::get('/grid-view/{grid_id}', [GridController::class, 'view'])->middleware(['auth'])->name('viewGrid');

Route::get('/grid-history/{grid_id}', [GridController::class, 'history'])->middleware(['auth'])->name('historyGrid');


Route::post('/choose-exchange-for-grid', [IndexController::class, 'gridCreating'])->middleware(['auth'])->name('chooseExchangeForGrid');


Route::get('/exchanges', [ExchangeController::class, 'get'])->middleware(['auth'])->name('exchanges');

Route::get('/exchange-creating', [IndexController::class, 'exchangeCreating'])->middleware(['auth'])->name('exchangeCreating');

Route::post('/exchange-create', [ExchangeController::class, 'create'])->middleware(['auth'])->name('createExchange');

Route::get('/exchange-delete/{exchange_id}', [ExchangeController::class, 'delete'])->middleware(['auth', 'web'])->name('deleteExchange');

Route::post('/coin-info', [ExchangeController::class, 'getCoinInfo'])->middleware(['auth', 'web'])->name('getCoinInfo');

Route::post('/current-price', [ExchangeController::class, 'getCurrentPriceForSymbol'])->middleware(['auth', 'web'])->name('getCurrentPriceForCoin');


Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

require __DIR__.'/auth.php';
