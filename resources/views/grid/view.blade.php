<?php

?>

@extends('layout')

@section('head')
	<script
			src="https://code.jquery.com/jquery-3.6.0.slim.min.js"
			integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI="
			crossorigin="anonymous"></script>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-F3w7mX95PdgyTmZZMECAngseQB83DfGTowi0iMjiWaeVhAn4FJkqJByhZMI3AhiU" crossorigin="anonymous">
	<link rel="stylesheet" href="/css/grid/view.css">
	<link rel="stylesheet" href="/css/plugins/selectize/selectize.css">
	<link rel="stylesheet" href="/node_modules/@splidejs/splide/dist/css/splide.min.css">
	<script src="https://momentjs.com/downloads/moment-with-locales.js"></script>
	<script src="/js/plugins/selectize/selectize.js"></script>
@endsection

<script>
    var gridCreatingTime = <?php echo json_encode($gridStartTime);?>; // <-- no quotes, no parsify
</script>



@section('content')
	<div class="container" style="margin-top: 100px;">
		<h4>
			Прибыль сетки: {{$gridProfit}}
		</h4>
		<h4>
			К-во сделок за 24 часа: {{$orderHistoriesQty24}}
		</h4>
		<h4>
			К-во сделок: {{$orderHistoriesQty}}
		</h4>
		<h4>
			<div class="d-flex">
				Сетка запущена: &nbsp<div id="clock"></div>
			</div>
		</h4>
		<h3>Открытые ордера в сетке ({{$activeOrdersQty}} штук)</h3>
		<div class="row">
			<div class="col-md-6">
				<div class="d-flex justify-content-around">
					<div>Цена</div>
					<div>Обьем</div>
					<div>Сумма</div>
					<div>Дата ордера</div>
				</div>
				@foreach($openBuyOrders as $openOrder)
					<div class="d-flex justify-content-between buy-open-order">
						<div class="open_orders-item"><span>{{$openOrder->price}}</span></div>
						<div class="open_orders-item"><span>{{$openOrder->amount}}</span></div>
						<div class="open_orders-item"><span>{{$openOrder->sum}}</span></div>
						<div class="open_orders-item"><span>{{$openOrder->created_at}}</span></div>
					</div>
				@endforeach
			</div>
			<div class="col-md-6">
				<div class="d-flex justify-content-around">
					<div>Цена</div>
					<div>Обьем</div>
					<div>Сумма</div>
					<div>Дата ордера</div>
				</div>
				@foreach($openSellOrders as $openOrder)
					<div class="d-flex justify-content-between sell-open-order">
						<div class="open_orders-item"><span>{{$openOrder->price}}</span></div>
						<div class="open_orders-item"><span>{{$openOrder->amount}}</span></div>
						<div class="open_orders-item"><span>{{$openOrder->sum}}</span></div>
						<div class="open_orders-item"><span>{{$openOrder->created_at}}</span></div>
					</div>
				@endforeach
			</div>
		</div>

	</div>

	<script src="/js/grid/stats.js"></script>
@endsection