@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-bold">
                        <div class="d-flex">
                            <div class="flex-grow-1 py-1">Ўқитувчи яратиш</div>
                            <div>
                                <a href="{{ url('/home') }}" class="btn btn-success btn-sm">
                                    Бекор қилиш
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('teachers.store') }}" method="post">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="hemis_id" name="hemis_id"
                                               required>
                                        <label for="hemis_id">HEMIS ID</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="telegram_id" name="telegram_id"
                                               required>
                                        <label for="telegram_id">Telegram ID</label>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="name" name="name" required>
                                        <label for="name">Ўқитувчининг тўлиқ Ф.И.Ш.</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="phone" name="phone"
                                               required>
                                        <label for="phone">Телефон рақами</label>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="username" name="username"
                                               required>
                                        <label for="username">Ўқитувчи логини</label>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center">
                                <button class="btn btn-success w-100 py-3">
                                    Яратиш
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
