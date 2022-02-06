<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport"
		  content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>Title</title>
	<link rel="stylesheet" href="/resources/css/bootstrap/bootstrap.css">
	@yield('head')
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark">
	<div class="container-fluid">
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="navbarCollapse">
			<div class="d-flex justify-content-between" style="width:100%;">
				<ul class="navbar-nav me-auto mb-2 mb-md-0">
					<li class="nav-item">
						<a class="nav-link" href="{{route('exchanges')}}">Биржи</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="{{route('grids')}}">Сетки</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="{{route('archiveGrids')}}">Архивные сетки</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" aria-current="page" href="{{route('gridCreating')}}">Создание сетки</a>
					</li>
				</ul>
			</div>
		</div>
	</div>
</nav>
	@yield('content')
</body>
</html>