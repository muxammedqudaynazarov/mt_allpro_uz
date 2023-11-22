<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Бердақ номидаги Қорақалпоқ давлат унивеситети Масофавий таълимни бошқариш тизими</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.png') }}">
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    @yield('style')
</head>
<body style="font-family: 'Rubik', sans-serif;">
<div id="app">
    <nav class="navbar bg-body-tertiary">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">
                MT.allpro.uz
                <div style="font-size: 11px">
                    {{ auth()->user()->name }}
                </div>
            </a>
            <ul class="navbar-nav ms-auto">
                @guest
                @else
                    <li class="nav-item">
                        <a class="btn btn-outline-success btn-sm" href="{{ url('home/change') }}">
                            Паролни ўзгартириш
                        </a>

                        <a class="btn btn-outline-success btn-sm" href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            Тизимдан чиқиш
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </li>
                @endguest
            </ul>

        </div>
    </nav>

    <main class="py-4">
        @yield('content')
    </main>

    @if(Session::has('success'))
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-success text-light">
                    <strong class="me-auto">Тизим янгиланди</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"
                            aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    {!! Session::get('success') !!}
                </div>
            </div>
        </div>
    @endif
    @if(Session::has('error'))
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-dark text-light">
                    <strong class="me-auto">Тизимда хатолик</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"
                            aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    {!! Session::get('error') !!}
                </div>
            </div>
        </div>
    @endif
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
@if (Session::has('error') || Session::has('success'))
    <script>
        $(document).ready(function () {
            $('.toast').toast('show');
        });
    </script>
@endif
@yield('script')
</body>
</html>
