@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-bold">
                        <div class="d-flex">
                            <div class="flex-grow-1 py-1">Токен олиш</div>
                            <div>
                                <a href="{{ url('/home/meets') }}" class="btn btn-success btn-sm">
                                    Бекор қилиш
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="?" method="post">
                            @csrf
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="accountId" name="accountId" required>
                                <label for="accountId">Account ID</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="clientId" name="clientId" required>
                                <label for="clientId">Client ID</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="clientSecret" name="clientSecret" required>
                                <label for="clientSecret">Client Secret</label>
                            </div>
                            <div class="text-center">
                                <button class="btn btn-success w-100">
                                    Олиш
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
