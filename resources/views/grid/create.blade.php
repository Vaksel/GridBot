<?php
?>

@php
	$coinName = '1INCH';
	$investmentCoin = 'USDT';
@endphp

@extends('layout')

@section('head')
	<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-F3w7mX95PdgyTmZZMECAngseQB83DfGTowi0iMjiWaeVhAn4FJkqJByhZMI3AhiU" crossorigin="anonymous">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
	<link rel="stylesheet" href="/css/grid/create.css">
	<link rel="stylesheet" href="/css/plugins/selectize/selectize.css">
	<link rel="stylesheet" href="/node_modules/@splidejs/splide/dist/css/splide.min.css">
	<script src="/js/plugins/selectize/selectize.js"></script>
	<script src="/node_modules/@splidejs/splide/dist/js/splide.min.js"></script>
	<script src="/js/plugins/dom-slide/index.js"></script>
@endsection

<script>
    var tickerOptions = <?php echo json_encode($tickers);?>; // <-- no quotes, no parsify
</script>

@section('content')

	<div class="d-flex flex-column align-items-center" style="padding-top: 100px;">

	<div class="splide" id="exchange_slider">
		<div class="splide__track">
			<ul class="splide__list">
				@foreach($exchanges as $exchange)
					<li class="splide__slide d-flex flex-column align-items-center exchange_item" data-exchange-id="{{$exchange->id}}">
						@if($exchange->exchange_type_id == 1 || $exchange->exchange_type_id == 3)
							<img class="exchange_icon" src="/img/binance_exchange_icon.svg" alt="">
						@elseif($exchange->exchange_type_id == 2 || $exchange->exchange_type_id == 4)
							<img class="exchange_icon" src="/img/kucoin_exchange_icon.svg" alt="">
						@endif

						<h3>{{$exchange->name}}</h3>
					</li>
				@endforeach
			</ul>
		</div>
	</div>

		<svg
				xmlns:dc="http://purl.org/dc/elements/1.1/"
				xmlns:cc="http://creativecommons.org/ns#"
				xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
				xmlns:svg="http://www.w3.org/2000/svg"
				xmlns="http://www.w3.org/2000/svg"
				xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd"
				xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape"
				width="22"
				height="22"
				fill="currentColor"
				class="arrow"
				viewBox="0 0 5.8208332 5.8208335"
				version="1.1"
				id="toggleExchange"
				inkscape:version="0.92.2 (5c3e80d, 2017-08-06)"
				sodipodi:docname="go-up.svg">
			<defs
					id="defs2" />
			<sodipodi:namedview
					id="base"
					pagecolor="#ffffff"
					bordercolor="#666666"
					borderopacity="1.0"
					inkscape:pageopacity="0.0"
					inkscape:pageshadow="2"
					inkscape:zoom="8"
					inkscape:cx="17.486803"
					inkscape:cy="2.7831783"
					inkscape:document-units="mm"
					inkscape:current-layer="layer1"
					showgrid="true"
					units="px"
					inkscape:window-width="1360"
					inkscape:window-height="718"
					inkscape:window-x="0"
					inkscape:window-y="24"
					inkscape:window-maximized="1">
				<inkscape:grid
						type="xygrid"
						id="grid10" />
			</sodipodi:namedview>
			<metadata
					id="metadata5">
				<rdf:RDF>
					<cc:Work
							rdf:about="">
						<dc:format>image/svg+xml</dc:format>
						<dc:type
								rdf:resource="http://purl.org/dc/dcmitype/StillImage" />
						<dc:title></dc:title>
					</cc:Work>
				</rdf:RDF>
			</metadata>
			<g
					inkscape:label="Capa 1"
					inkscape:groupmode="layer"
					id="layer1"
					transform="translate(0,-291.17915)">
				<g
						id="g856"
						transform="matrix(0,1,1,0,-291.32557,291.17915)">
					<path
							sodipodi:nodetypes="ccc"
							inkscape:connector-curvature="0"
							id="path12"
							d="m 2.9104167,292.23748 -2.11666671,1.85209 2.11666661,2.11666"
							style="fill:none;stroke:currentColor;stroke-width:1;stroke-linecap:butt;stroke-linejoin:round;stroke-miterlimit:4;stroke-dasharray:none;stroke-opacity:1" />
					<path
							style="fill:none;stroke:currentColor;stroke-width:1;stroke-linecap:butt;stroke-linejoin:round;stroke-miterlimit:4;stroke-dasharray:none;stroke-opacity:0.52147241"
							d="M 5.2916667,292.23748 3.175,294.08957 l 2.1166666,2.11666"
							id="path822"
							inkscape:connector-curvature="0"
							sodipodi:nodetypes="ccc" />
				</g>
			</g>
		</svg>

	</div>

	@error('currency_used')
	<div class="alert alert-danger">{{ $message }}</div>
	@enderror

	@if($errors->any())
		@foreach($errors->all() as $error)
			<div class="alert alert-danger">{{ $error }}</div>
		@endforeach
	@endif

	<form action="{{route('createGrid')}}" method="POST" class="createGridForm">
		@csrf

		<div class="modal" id="notificationModal" tabindex="-1" role="dialog">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="modalTitle"></h5>
						<button type="button" onclick="$('#notificationModal').modal('hide')" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<p id="modalText"></p>
					</div>
				</div>
			</div>
		</div>

		<div class="modal" id="priceLevelModal" tabindex="-1" role="dialog">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="modalTitle">Уровни инвестирования</h5>
						<button type="button" onclick="$('#priceLevelModal').modal('hide')" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="d-flex justify-content-between">
							<div class="d-flex flex-column investment-lvl">
								<div>Уровень инвестирования</div>
							</div>
							<div class="d-flex flex-column min-investment-sum">
								<div>Мин. сумма для уровня</div>
							</div>
							<div class="d-flex flex-column mid-investment-amount">
								<div>Средний обьем сетки</div>
							</div>
						</div>
						<div class="investment-container">
						</div>
					</div>
				</div>
			</div>
		</div>

		<input name="ticker" type="hidden">
		<input type="hidden" name="currency_used">
		<input type="hidden" name="alt_used" value="false">
		<input type="hidden" name="exchange_id">
		<input type="hidden" name="grid_id" value="">
		<input type="hidden" name="exchange_type">
		<input type="hidden" name="assetUsageWarned" value="0">


		<div class="container">
			<h1 style="text-align: center;">Добавление сеток</h1>
			<div class="col-md-6" style="margin: auto;">
				<div id="bot_name">
					<h4>Назовите сетку</h4>
					<div id="bot_name-inner">
						<input type="text" name="bot_name" class="form-control bot_name">
					</div>
					<div id="bot_name-error" class="field-error"></div>
				</div>
				<div class="d-flex flex-row justify-content-between">
					<h5>Выберите пару для торговли</h5>
					<select class="tickers">
						@foreach($tickers as $key => $ticker)
							<option value="{{$ticker}}">{{$ticker}}</option>
						@endforeach
					</select>
				</div>
				<div class="d-flex justify-content-between">
					<label for="lowest_price" class="form-label">Ценовой диапазон</label>
					<label class="form-label">Цена: <span id="current-price">ожидание</span></label>
				</div>
				<div class="d-flex flex-row" id="price-interval">
					<div id="lowest_price-inner" class="price-inner">
						<input name="lowest_price" type="text" class="form-control price-interval price-interval--low">
					</div>
					~
					<div id="highest_price-inner" class="price-inner">
						<input name="highest_price" type="text" class="form-control price-interval price-interval--high">
					</div>
				</div>
				<div id="order-qty">
					<h4>К-во ордеров для торговли</h4>
					<div id="order_qty-inner">
						<input type="text" name="order_qty" class="form-control order-qty">
					</div>
				</div>
				<div class="d-flex justify-content-around" id="price-interval__inner">
					<span class="price-interval_label">Интервал (USDT)</span>
					<span class="price-interval_value">0</span>
				</div>
				<div class="d-flex justify-content-between">
					<h4>Всего инвестиций</h4>
					<div class="d-none flex-row parallel-coin">
						<h4 class="btn btn-info" style="margin-right: 10px;">Использовать </h4>
					</div>
				</div>
				<div class="d-flex flex-row investment-sum__inner">
					<div id="investments-inner">
						<input type="text" name="investments" class="form-control" readonly>
					</div>
					<div data-bs-toggle="tooltip" data-bs-placement="bottom" title="Для просмотра уровней инвестирования введите инвестицию" style="margin-left: -30px;margin-top: 5px;">
						@include('grid.list-svg', ['class' => 'investment-levels', 'dataTitle' => 'Уровни инвестирования'])
					</div>
					<span class="used_coin"></span>
				</div>
{{--				<div class="available-balance">--}}
{{--					<span class="available-balance_label">Доступный баланс</span>--}}
{{--					<span class="available-balance_value">0</span>--}}
{{--				</div>--}}
				<div class="additional-settings d-flex">
{{--					<div>--}}
{{--						<span class="stop-price_label">Стоп цена</span>--}}
{{--						<input name="stop_price" class="form-control stop-price_value" value="0">--}}
{{--					</div>--}}
					<div class="enter-price__inner">
						<span class="enter-price_label">Цена входа (введите если хотите начинать не с текущей цены)</span>
						<input name="start_price" class="form-control enter-price_value" value="">
					</div>
				</div>
				<button type="submit" class="create-grid_btn" id="createGrid">
					Создать
				</button>
				<div class="d-none justify-content-center loaded_hiding" id="loader">
					<svg class="preloader__image" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
						<path fill="currentColor"
							  d="M304 48c0 26.51-21.49 48-48 48s-48-21.49-48-48 21.49-48 48-48 48 21.49 48 48zm-48 368c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48zm208-208c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48zM96 256c0-26.51-21.49-48-48-48S0 229.49 0 256s21.49 48 48 48 48-21.49 48-48zm12.922 99.078c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48c0-26.509-21.491-48-48-48zm294.156 0c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48c0-26.509-21.49-48-48-48zM108.922 60.922c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.491-48-48-48z">
						</path>
					</svg>
				</div>
			</div>


		</div>


	</form>

	{{--@include ('js')--}}
	<script src="/js/grid/calc_min_investment.js"></script>
	<script src="/js/grid/index.js"></script>
@endsection