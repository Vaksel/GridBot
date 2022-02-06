<?php

?>

@extends('layout')

@section('head')
	<script
			src="https://code.jquery.com/jquery-3.6.0.slim.min.js"
			integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI="
			crossorigin="anonymous"></script>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-F3w7mX95PdgyTmZZMECAngseQB83DfGTowi0iMjiWaeVhAn4FJkqJByhZMI3AhiU" crossorigin="anonymous">
	<link rel="stylesheet" href="/css/grid/index.css">
	<link rel="stylesheet" href="/css/plugins/selectize/selectize.css">
	<link rel="stylesheet" href="/node_modules/@splidejs/splide/dist/css/splide.min.css">
	<script src="/js/plugins/selectize/selectize.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
@endsection

@section('content')

	<div class="modal" tabindex="-1" id="removeChoser__modal" role="dialog">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Вы хотите удалить или заархивировать сетку?</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<p>
						При удалении сетки закрываются все открытые ордера сетки, а также удаляется вся история ордеров.
						<hr>
						При архивировании сетки закрываются все открытые ордера сетки, но история сетки
						(параметры сетки, статистика, история ордеров) остаются
					</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-danger" onclick="deleteGrid()">Удалить сетку</button>
					<button type="button" class="btn btn-primary" onclick="archiveGrid()">Заархивировать сетку</button>
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть</button>
				</div>
			</div>
		</div>
	</div>

	<br><br>
	<div class="container">
		<h2>Общая сеточная прибыль: {{$commonGridProfit}}</h2>
		<h2>Общая прибыль: </h2>
		<h2>К-во открытых ордеров: <span class="text-success">{{$activeOrdersBuy}}</span>|<span class="text-danger">{{$activeOrdersSell}}</span></h2>
	</div>

	@php

			$gridData = [
				'dataProvider' 			=> $dataProvider,
				'title' 				=> 'Ваши сетки',
				'useFilters' 			=> false,
				'defaultIdIsEnabled' 	=> false,
				'columnFields' => [
					'id',
										[
					    'attribute' => 'bot_name',
					    'label' 	=> 'Название сетки'
					],
					[
						'label' => 'Биржа сетки',
						'value' => function ($row)
						{
							return $row->exchange->name;
						}
					],
					[
					    'attribute' => 'ticker',
					    'label' 	=> 'Пара'
					],
										[
					    'attribute' => 'lowest_price',
					    'label' 	=> 'Нижний предел цены'
					],
										[
					    'attribute' => 'highest_price',
					    'label' 	=> 'Верхний предел цены'
					],
										[
					    'attribute' => 'investments',
					    'label' 	=> 'Сумма инвестиции'
					],
										[
					    'attribute' => 'order_qty',
					    'label' 	=> 'К-во ордеров'
					],
										[
					    'attribute' => 'currency_used',
					    'label' 	=> 'Используемая монета'
					],
										[
					    'attribute' => 'stop_price',
					    'label' 	=> 'Стоп-лимит'
					],
										[
					    'attribute' => 'start_price',
					    'label' 	=> 'Цена входа'
					],

					[
				'label' => 'Действия', // Optional
				'class' => Itstructure\GridView\Columns\ActionColumn::class, // Required
				'actionTypes' => [ // Required
					[
						'class' => Itstructure\GridView\Actions\Delete::class, // Required
						'url' => function ($data) { // Optional
							return '/grid-delete/' . $data->id;
						},
						'htmlAttributeCustomClasses' => 'gridDelete',
						'htmlAttributes' => [ // Optional
							'style' => 'color: yellow; font-size: 8px;',
							'onclick' => 'return false;'
						]
					],
					[
						'class' => Itstructure\GridView\Actions\View::class, // Required
						'url' => function ($data) { // Optional
							return '/grid-view/' . $data->id;
						},
						'htmlAttributes' => [ // Optional
							'style' => 'color: yellow; font-size: 8px;',
						]
					],
					[
						'class' => Itstructure\GridView\Actions\OrderHistory::class, // Required
						'url' => function ($data) { // Optional
							return '/grid-history/' . $data->id;
						},
						'htmlAttributes' => [ // Optional
							'style' => 'color: yellow; font-size: 8px; width:35px;',
						]
					],

				]
			]
				]
			];
	@endphp

	{{--	'view',--}}

	{{--	'edit' => function ($data) {--}}
	{{--	return '/admin/pages/' . $data->id . '/edit';--}}
	{{--	},--}}

	<div class="container">
		<br>
		<br>
		<br>
		<br>

		@if(session()->has('grid_status_success'))
			<div class="alert alert-success">{{session()->get('grid_status_success')}}</div>
		@endif

		@if(session()->has('grid_status_fail'))
			<div class="alert alert-danger">{{session()->get('grid_status_fail')}}</div>
		@endif

		<br>

		@gridView($gridData)

		<br>

		<a href="{{route('gridCreating')}}" class="btn btn-success">Добавить сетку</a>

	</div>

	<script src="/js/grid/table-grid.js"></script>

@endsection
