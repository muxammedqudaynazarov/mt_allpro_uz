<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Berdaq nomidagi Qoraqalpoq davlat univesiteti Masofaviy taʼlimni boshqarish tizimi</title>
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

    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">
                MT.allpro.uz
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                </ul>
                <div class="d-flex" role="search">
                    @auth
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0 small">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                   aria-expanded="false">
                                    @php
                                        $exp = explode(' ', auth()->user()->name);
                                        echo $exp[0] . ' ' . $exp[1];
                                    @endphp
                                    <br>
                                    @if(auth()->user()->role == 'admin')
                                        Administrator
                                    @else
                                        O‘qituvchi
                                    @endif
                                </a>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="{{ url('home/change') }}">
                                            Parolni o‘zgartirish
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ url('logout') }}">
                                            Tizimdan chiqish
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    @else
                        <a class="dropdown-item" href="{{ url('login') }}">
                            Saytga kirish
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <main class="py-4">
        @yield('content')
    </main>

    @if(Session::has('success'))
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-success text-light">
                    <strong class="me-auto">Tizim yangilandi</strong>
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
                    <strong class="me-auto">Tizimda xatolik</strong>
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
