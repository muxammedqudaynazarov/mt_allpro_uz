@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-bold">
                        <div class="d-flex">
                            <div class="flex-grow-1 py-1">Parolni o‘zgartirish</div>
                            <div>
                                <a href="{{ url('/home') }}" class="btn btn-success btn-sm">
                                    Bekor qilish
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="?" method="post">
                            @csrf
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" name="newPassword" id="newPassword"
                                       placeholder="Yangi parolni kiriting" autofocus required>
                                <label for="newPassword">Yangi parolni kiriting</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" name="reEnterPassword"
                                       id="reEnterPassword" placeholder="Yangi parolni takrorlang" required>
                                <label for="reEnterPassword">Yangi parolni takrorlang</label>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-success w-100 py-3">
                                    O‘zgartirish
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
