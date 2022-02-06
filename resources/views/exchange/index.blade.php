<?php

?>

@extends('layout')

@section('head')
	<script
			src="https://code.jquery.com/jquery-3.6.0.slim.min.js"
			integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI="
			crossorigin="anonymous"></script>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-F3w7mX95PdgyTmZZMECAngseQB83DfGTowi0iMjiWaeVhAn4FJkqJByhZMI3AhiU" crossorigin="anonymous">
	<link rel="stylesheet" href="/css/exchange/index.css">
	<link rel="stylesheet" href="/css/plugins/selectize/selectize.css">
	<link rel="stylesheet" href="/node_modules/@splidejs/splide/dist/css/splide.min.css">
	<script src="/js/plugins/selectize/selectize.js"></script>
	<script src="/node_modules/@splidejs/splide/dist/js/splide.min.js"></script>
	<script src="/js/plugins/dom-slide/index.js"></script>
@endsection

@section('content')

	@php

	@endphp

{{--	[--}}
{{--	'label' => 'Тип биржи',--}}
{{--	'value' => function ($row) {--}}
{{--	if($row)--}}
{{--	return '<img href="">'--}}
{{--	}--}}
{{--	]--}}

{{--	function ($row)--}}
{{--	{--}}
{{--	global $paperList;--}}

{{--	ddd($paperList);--}}

{{--	$apiKey = mb_strimwidth($row->api_key, 0, 15, '...');--}}
{{--	return "<div class='d-flex'><span>{$apiKey}</span>{$paperList}</div>";--}}

{{--	}--}}

	@php


	$apiKeyCallback = function ($row) {

    $paperList = '<svg class="copyItem" version="1.1" id="paper" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
		 viewBox="0 0 1024 1024" enable-background="new 0 0 1024 1024" xml:space="preserve">
	<g id="paper-paper">
		<polyline fill="#FFFFFF" points="322.0645,768 322.0645,256 586.3145,256 701.9434,371.6143 	"/>
		<polyline fill="#D0E8F9" points="701.9434,371.6143 701.9434,768 322.0645,768 	"/>
		<g>
			<path fill="#0096E6" d="M714.7432,780.7998H309.2646V243.2002h282.3497l123.1288,123.1143V780.7998L714.7432,780.7998z
				 M334.8643,755.2002h354.2783V376.9141l-108.128-108.1143H334.8643V755.2002L334.8643,755.2002z"/>
		</g>
		<g>
			<polyline fill="#D0E8F9" points="581.4434,268.1929 581.4434,383.8071 697.0566,383.8071 		"/>
			<polygon fill="#0096E6" points="697.0566,396.6074 568.6426,396.6074 568.6426,268.1929 594.2432,268.1929 594.2432,371.0068
				697.0566,371.0068 697.0566,396.6074 		"/>
		</g>
	</g>
	</svg>
	';

		$apiKey = mb_strimwidth($row->api_key, 0, 15, '...');
		return "<div class='d-flex'><input type='text' class='api_key_hidden hidden' value='{$row->api_key}'><span>{$apiKey}</span>{$paperList}</div>";
	};

	$secretKeyCallback = function ($row) {
	        $paperList = '<svg class="copyItem" version="1.1" id="paper" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
		 viewBox="0 0 1024 1024" enable-background="new 0 0 1024 1024" xml:space="preserve">
	<g id="paper-paper">
		<polyline fill="#FFFFFF" points="322.0645,768 322.0645,256 586.3145,256 701.9434,371.6143 	"/>
		<polyline fill="#D0E8F9" points="701.9434,371.6143 701.9434,768 322.0645,768 	"/>
		<g>
			<path fill="#0096E6" d="M714.7432,780.7998H309.2646V243.2002h282.3497l123.1288,123.1143V780.7998L714.7432,780.7998z
				 M334.8643,755.2002h354.2783V376.9141l-108.128-108.1143H334.8643V755.2002L334.8643,755.2002z"/>
		</g>
		<g>
			<polyline fill="#D0E8F9" points="581.4434,268.1929 581.4434,383.8071 697.0566,383.8071 		"/>
			<polygon fill="#0096E6" points="697.0566,396.6074 568.6426,396.6074 568.6426,268.1929 594.2432,268.1929 594.2432,371.0068
				697.0566,371.0068 697.0566,396.6074 		"/>
		</g>
	</g>
	</svg>
	';

		$apiKey = mb_strimwidth($row->api_secret, 0, 15, '...');
		return "<div class='d-flex'><input type='text' class='api_secret_hidden hidden' value='{$row->api_secret}'><span>{$apiKey}</span>{$paperList}</div>";
	};

	$passPhraseCallback = function ($row) {
	        $paperList = '<svg class="copyItem" version="1.1" id="paper" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
		 viewBox="0 0 1024 1024" enable-background="new 0 0 1024 1024" xml:space="preserve">
	<g id="paper-paper">
		<polyline fill="#FFFFFF" points="322.0645,768 322.0645,256 586.3145,256 701.9434,371.6143 	"/>
		<polyline fill="#D0E8F9" points="701.9434,371.6143 701.9434,768 322.0645,768 	"/>
		<g>
			<path fill="#0096E6" d="M714.7432,780.7998H309.2646V243.2002h282.3497l123.1288,123.1143V780.7998L714.7432,780.7998z
				 M334.8643,755.2002h354.2783V376.9141l-108.128-108.1143H334.8643V755.2002L334.8643,755.2002z"/>
		</g>
		<g>
			<polyline fill="#D0E8F9" points="581.4434,268.1929 581.4434,383.8071 697.0566,383.8071 		"/>
			<polygon fill="#0096E6" points="697.0566,396.6074 568.6426,396.6074 568.6426,268.1929 594.2432,268.1929 594.2432,371.0068
				697.0566,371.0068 697.0566,396.6074 		"/>
		</g>
	</g>
	</svg>
	';

		$apiKey = mb_strimwidth($row->api_passphrase, 0, 15, '...');

		if(!empty($apiKey))
		{
		    return "<div class='d-flex'><input type='text' class='api_passphrase_hidden hidden' value='{$row->api_passphrase}'><span>{$apiKey}</span>{$paperList}</div>";
		}

		return 'Пусто';
	};

		$gridData = [
			'dataProvider' => $dataProvider,
			'title' => 'Ваши биржи',
			'useFilters' => false,
			'columnFields' => [
				'id',
				array(
					'label' 	=> 'API-ключ',
					'format' 	=> 'html',
					'value' 	=> $apiKeyCallback
				),
				array(
					'label' 	=> 'API-secret',
					'format' 	=> 'html',
					'value' 	=> $secretKeyCallback
				),
				array(
					'label' 	=> 'API-passphrase',
					'format' 	=> 'html',
					'value' 	=> $passPhraseCallback
				),
				[
					'label' => 'Тип биржи',
					'value' => function ($row)
					{
						return $row->exchangeType->exchange_name;
					}
				],
				[
            'label' => 'Действия', // Optional
            'class' => Itstructure\GridView\Columns\ActionColumn::class, // Required
            'actionTypes' => [ // Required
                [
                    'class' => Itstructure\GridView\Actions\Delete::class, // Required
                    'url' => function ($data) { // Optional
                        return '/exchange-delete/' . $data->id;
                    },
                    'htmlAttributes' => [ // Optional
                        'style' => 'color: yellow; font-size: 8px;',
                        'onclick' => 'return window.confirm("Вы уверены что хотите удалить эту биржу?");'
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

			@if(session()->has('exchange_status_success'))
				<div class="alert alert-success">{{session()->get('exchange_status_success')}}</div>
			@endif

			@if(session()->has('exchange_status_fail'))
				<div class="alert alert-danger">{{session()->get('exchange_status_fail')}}</div>
			@endif
		<br>
		@gridView($gridData)

		<br>

		<a href="{{route('exchangeCreating')}}" class="btn btn-success">Добавить биржу</a>
	</div>

	<script src="/js/exchange/view.js"></script>

@endsection