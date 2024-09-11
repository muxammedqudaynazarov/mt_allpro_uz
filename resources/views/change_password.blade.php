@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-bold">
                        <div class="d-flex">
                            <div class="flex-grow-1 py-1">
                                Паролни ўзгартириш
                            </div>
                            <div>
                                <a href="{{ url('/home') }}" class="btn btn-success btn-sm">
                                    Асосийга қайтиш
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="?" method="post">
                            @csrf
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" name="newPassword" id="newPassword"
                                       required autofocus>
                                <label for="newPassword">Янги паролни киритиш</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" name="reEnterPassword"
                                       id="reEnterPassword" required>
                                <label for="reEnterPassword">Янги паролни такрорланг</label>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-success w-100 py-3">
                                    Ўзгартириш
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
