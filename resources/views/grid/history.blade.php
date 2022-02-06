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
	<script src="/js/plugins/selectize/selectize.js"></script>
@endsection

@section('content')
	<div class="container" style="margin-top: 100px;">
		История ордеров сетки
		<div class="row">
			<div class="col-md-6">
				<div class="d-flex justify-content-around">
					<div>Цена</div>
					<div>Обьем</div>
					<div>Сумма</div>
					<div>Прибыль</div>
					<div>Дата ордера</div>
				</div>
				@foreach($orderHistories as $orderHistory)
						<div class="d-flex justify-content-between {{$orderHistory->side === 'SELL'?'sell-open-order':'buy-open-order'}}">
							<div class="open_orders-item"><span>{{$orderHistory->price}}</span></div>
							<div class="open_orders-item"><span>{{$orderHistory->amount}}</span></div>
							<div class="open_orders-item"><span>{{number_format($orderHistory->price * $orderHistory->amount, 5)}}</span></div>
							<div class="open_orders-item"><span>{{$orderHistory->profit}}</span></div>
							<div class="open_orders-item"><span>{{$orderHistory->created_at}}</span></div>
						</div>
				@endforeach
			</div>
{{--			<div class="col-md-6">--}}
{{--				<div class="d-flex justify-content-around">--}}
{{--					<div>Цена</div>--}}
{{--					<div>Обьем</div>--}}
{{--					<div>Сумма</div>--}}
{{--					<div>Дата ордера</div>--}}
{{--				</div>--}}
{{--				@foreach($openOrders as $openOrder)--}}
{{--					@if($openOrder->side === 'SELL')--}}
{{--						<div class="d-flex justify-content-between sell-open-order">--}}
{{--							<div class="open_orders-item"><span>{{$openOrder->price}}</span></div>--}}
{{--							<div class="open_orders-item"><span>{{$openOrder->amount}}</span></div>--}}
{{--							<div class="open_orders-item"><span>{{$openOrder->sum}}</span></div>--}}
{{--							<div class="open_orders-item"><span>{{$openOrder->created_at}}</span></div>--}}
{{--						</div>--}}
{{--					@endif--}}
{{--				@endforeach--}}
{{--			</div>--}}
		</div>

	</div>
@endsection