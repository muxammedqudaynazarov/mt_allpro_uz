@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="justify-content-center">
            <div class="card">
                <div class="card-header fw-bold">
                    <div class="d-flex">
                        <div class="flex-grow-1 py-1">Ўқитувчи яратиш</div>
                        <div>
                            <a href="{{ route('teachers.index') }}" class="btn btn-success btn-sm">
                                Рўйхатга қайтиш
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if(auth()->user()->current_role == 'admin')
                        <div class="text-danger border p-4 rounded mb-3">
                            <div class="fw-bold">
                                Ҳурматли админлар, фойдаланувчи яратишдан олдиг профессор-ўқитувчиларнинг
                                https://t.me/dist_karsu_uz_bot Telegram ботига /start буйриғи берилганлигини текширинг.
                                Қуйидагиларга этибор қаратинг!
                            </div>
                            <ol>
                                <li>
                                    Аввал Telegram орқали @dist_karsu_uz_bot ботига «Старт» беринг.
                                </li>
                                <li>
                                    Кейин профессор-ўқитувчининг Telegram ID рақамини @getmyid_bot Telegram боти орқали
                                    олинг.
                                </li>
                                <li>
                                    Керакли майдонларни тўлдиринг.
                                </li>
                            </ol>
                        </div>
                    @endif
                    <form action="{{ route('teachers.store') }}" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="hemis_id" name="hemis_id">
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
                                    <input type="text" class="form-control" id="name" name="name"
                                           style="text-transform: uppercase" required>
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
                                           style="text-transform: lowercase" required>
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
@endsection
