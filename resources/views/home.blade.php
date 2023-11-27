@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            @if($teachers)
                <div class="col-md-8">
                    <div class="card mb-3">
                        <div class="card-body">
                            <form action="?" method="get">
                                <div class="row">
                                    <div class="col-md-9 mb-3 mb-lg-0">
                                        <div class="form-floating">
                                            <select class="form-select" id="teacher_id" name="teacher_id">
                                                <option selected disabled></option>
                                                @foreach($teachers as $teacher)
                                                    <option @if($t && $teacher->hemis_id == $t) selected @endif
                                                    value="{{ $teacher->hemis_id }}">
                                                        {{ $teacher->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <label for="teacher_id">Professor-o‘qituvchini tanlang</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-success py-2 mt-lg-2 w-100">
                                            Filtrlash
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card mb-3">
                        <div class="card-body small">
                            <div>
                                Yaratilgan darslar: {{ $lessons }}
                            </div>
                            <div>
                                Jarayonda: {{ $lessons_o }}
                            </div>
                            <div>
                                Tugatilgan: {{ $lessons_c }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-bold">
                        <div class="d-flex">
                            <div class="flex-grow-1 py-1">Admin paneli</div>
                            <div class="mx-1">
                                <a href="{{ url('/home/meets') }}" class="btn btn-success btn-sm">
                                    Dars yaratish
                                </a>
                            </div>
                            @if(auth()->user()->role == 'admin')
                                <div class="mx-1">
                                    <a href="{{ url('/home/teacher_insert') }}" class="btn btn-success btn-sm">
                                        O‘qituvchi qo‘shish
                                    </a>
                                </div>
                            @endif
                            @if(auth()->user()->role == 'admin')
                                <div class="mx-1">
                                    <a href="{{ url('/home/groups') }}" class="btn btn-success btn-sm">
                                        Guruhlarni boshqarish
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        @foreach($meets as $meet)
                            <div class="card mb-2">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div class="fw-bold">
                                                Mavzu: {{ $meet->topic }}
                                            </div>
                                            <div class="small">
                                                @if(auth()->user()->role == 'admin')
                                                    <div style="text-transform: capitalize">
                                                        O‘qituvchi: {{ \App\Models\Application::user_name($meet->_from) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    Guruh: {{ \App\Models\Application::group_name($meet->_to) }}
                                                </div>
                                                <div>
                                                    Boshlash
                                                    vaqti: {{ date('d.m.Y H:i', strtotime($meet->created_at)) }}
                                                </div>
                                                <div>
                                                    Yakunlash vaqti: {{ date('d.m.Y H:i', strtotime($meet->ended_at)) }}
                                                </div>
                                                {{--@if(auth()->user()->role == 'admin')
                                                    <div class="mt-3">
                                                        Бошлаш URL:
                                                        <button class="small border rounded"
                                                                onclick='copyClipBoard("{{ $meet->start_url }}")'>
                                                            нусхалаш
                                                        </button>
                                                    </div>
                                                    <div>
                                                        Кириш URL: {{ $meet->join_url }}
                                                    </div>
                                                @endif--}}
                                            </div>
                                        </div>
                                        <div class="col-3 text-center h-100 align-middle">
                                            @if(strtotime($meet->ended_at) > time())
                                                @if($meet->status == 'w')
                                                    <div>
                                                        <a href="{{ $meet->start_url }}" target="_blank"
                                                           class="btn btn-outline-success btn-sm mb-1">
                                                            Darsni boshlash
                                                        </a>
                                                    </div>
                                                @endif
                                            @endif
                                            @if($meet->status == 'w' || $meet->status == 's')
                                                <div>
                                                    <a href="{{ url('/home/delete/' . $meet->meet_id) }}"
                                                       class="btn btn-outline-dark btn-sm mb-1">
                                                        Darsni yakunlash
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    {{ $meets->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script>
        $('.pagination').addClass('justify-content-center');
    </script>
@endsection
