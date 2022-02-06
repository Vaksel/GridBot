<?php
?>

@php
	$coinName = '1INCH';
	$investmentCoin = 'USDT';
@endphp

@extends('layout')

@section('head')
	<script
			src="https://code.jquery.com/jquery-3.6.0.slim.min.js"
			integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI="
			crossorigin="anonymous"></script>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-F3w7mX95PdgyTmZZMECAngseQB83DfGTowi0iMjiWaeVhAn4FJkqJByhZMI3AhiU" crossorigin="anonymous">
	<link rel="stylesheet" href="/css/grid/create.css">
	<link rel="stylesheet" href="/css/plugins/selectize/selectize.css">
	<link rel="stylesheet" href="/node_modules/@splidejs/splide/dist/css/splide.min.css">
	<script src="/js/plugins/selectize/selectize.js"></script>
	<script src="/node_modules/@splidejs/splide/dist/js/splide.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
	<script src="/js/plugins/dom-slide/index.js"></script>
@endsection

@section('content')
	<style>
		.selectize-control {
			width: 150px;
		}
	</style>

	<div class="modal" tabindex="-1" id="notificationModal" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Сообщение после создания сетки</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть</button>
				</div>
			</div>
		</div>
	</div>
	@error('currency_used')
	<div class="alert alert-danger">{{ $message }}</div>
	@enderror

	@if($errors->any())
		@foreach($errors->all() as $error)
			<div class="alert alert-danger">{{ $error }}</div>
		@endforeach
	@endif

	<form action="{{route('createExchange')}}" method="POST" class="createExchangeForm">
		@csrf

		<input type="hidden" name="exchange_type_id">

		<div class="container">
			<h1>Добавление бирж</h1>
			<div class="col-md-6">
				<div>
					<label for="name">Имя</label>
					<input class="form-control" name="name" type="text" placeholder="Назовите биржу" value="">
				</div>
				<div>
					<label for="api_key">API-key</label>
					<input class="form-control" name="api_key" type="text" placeholder="Введите ваш api-key" value="">
				</div>
				<div>
					<label for="api_secret">Secret-key</label>
					<input class="form-control" name="api_secret" type="text" placeholder="Введите ваш api-secret" value="">
				</div>
				<div>
					<label for="api_passphrase">Passphrase (если такой есть)</label>
					<input class="form-control" name="api_passphrase" type="text" placeholder="Введите ваш passphrase" value="">
				</div>
				<div class="d-flex flex-row justify-content-between">
					<h5>Выберите биржу для торговли</h5>
					<select class="exchanges">
						<option value="1">Binance</option>
						<option value="2">Kucoin</option>
						<option value="3">BinanceTest</option>
						<option value="4">KucoinTest</option>
						<option value="5">None</option>
					</select>
				</div>

			<button type="submit" class="btn btn-success create-grid_btn" id="createExchange">
				Создать
			</button>
		</div>

		</div>


	</form>

	{{--@include ('js')--}}

	<script src="/js/exchange/index.js"></script>
@endsection