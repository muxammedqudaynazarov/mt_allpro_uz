@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-bold">
                        <div class="d-flex">
                            <div class="flex-grow-1 py-1">Админ панель</div>
                            @can('lessons:create')
                                <div class="mx-1">
                                    <a href="{{ route('meets.create') }}" class="btn btn-success btn-sm">
                                        Дарс яратиш
                                    </a>
                                </div>
                            @endcan
                            @can('teachers:view')
                                <div class="mx-1">
                                    <a href="{{ route('teachers.index') }}" class="btn btn-success btn-sm">
                                        Ўқитувчилар рўйхати
                                    </a>
                                </div>
                            @endcan
                            @can('groups:view')
                                <div class="mx-1">
                                    <a href="{{ url('/home/groups') }}" class="btn btn-success btn-sm">
                                        Гуруҳлар рўйхати
                                    </a>
                                </div>
                            @endcan
                        </div>
                    </div>
                    <div class="card-body">
                        @foreach($meets as $meet)
                            <div class="card mb-2">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div class="fw-bold">
                                                Мавзу: {{ $meet->topic }}
                                            </div>
                                            <div class="small">
                                                <div>
                                                    Гуруҳ: {{ $meet->group->name }}
                                                </div>
                                                <div>
                                                    Бошлаш вақти: {{ $meet->started_at }}
                                                </div>
                                                <div>
                                                    Якунлаш вақти: {{ $meet->ended_at }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-3 text-center h-100 align-middle">
                                            @if($meet->type == 'n')
                                                <div>
                                                    <a href="{{ $meet->start_url }}" target="_blank"
                                                       class="btn btn-outline-success btn-sm mb-1">
                                                        Дарсни бошлаш
                                                    </a>
                                                </div>
                                            @endif
                                            @if($meet->type == 'n' || $meet->type == 'c')
                                                <form action="{{route('meets.destroy', $meet->id)}}"
                                                      method="post" class="d-inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-outline-dark btn-sm m-1" type="submit"
                                                            onclick="return confirm('Дарсни вақтидан олдин якунлашни хоҳлайсизми?')">
                                                        Дарсни якунлаш
                                                    </button>
                                                </form>
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
