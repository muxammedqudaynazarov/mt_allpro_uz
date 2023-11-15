@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-bold">
                        <div class="d-flex">
                            <div class="flex-grow-1 py-1">Админ панель</div>
                            <div>
                                <a href="{{ url('/home/meets') }}" class="btn btn-success btn-sm">
                                    Дарс яратиш
                                </a>
                            </div>
                        </div>
                        </div>
                    <div class="card-body">
                        {{ __('You are logged in!') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
