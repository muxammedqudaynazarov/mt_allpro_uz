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
                        <form action="?" method="post">
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

                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="username" name="username" readonly>
                                        <label for="username">Ўқитувчи логини</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="password" value="123456789" class="form-control" id="password"
                                               name="password" readonly>
                                        <label for="password">Ўқитувчи пароли (123456789)</label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="role" name="role">
                                            <option disabled></option>
                                            <option value="admin">Администратор</option>
                                            <option value="user" selected>Ўқитувчи</option>
                                        </select>
                                        <label for="role">Фойдаланувчи рўли</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="active" name="active">
                                            <option disabled></option>
                                            <option value="1" selected>Актив</option>
                                            <option value="0">Блокланган</option>
                                        </select>
                                        <label for="active">Фойдаланувчи ҳолати</label>
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

@section('script')
    <script>
        $("#name").on('keyup', function () {
            let name = $(this).val();
            $.get("{{ url('/home/username') }}/" + name, function (data) {
                $('#username').empty().val(data);
            });
        });
    </script>
@endsection
