@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="justify-content-center">
            <div class="card">
                <div class="card-header fw-bold">
                    <div class="d-flex">
                        <div class="flex-grow-1 py-1">Админ панель</div>
                        @can('teachers:create')
                            <div class="mx-1">
                                <a href="{{ route('teachers.create') }}" class="btn btn-success btn-sm">
                                    Ўқитувчи қўшиш
                                </a>
                            </div>
                        @endcan
                        <div class="mx-1">
                            <a href="{{ url('/home') }}" class="btn btn-success btn-sm">
                                Асосийга қайтиш
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                        <tr class="text-center">
                            <th>#</th>
                            <th>Ўқитувчи Ф.И.Ш.</th>
                            <th>Телефон</th>
                            <th>Маъсул</th>
                            <th>Қўшилган вақти</th>
                            @can('teachers:password')
                                <th>Пароль</th>
                            @endcan
                            @can('teachers:status')
                                <th>Блок</th>
                            @endcan
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($teachers as $teacher)
                            <tr>
                                <td class="align-middle text-center">{{ $teacher->id }}</td>
                                <td class="align-middle">
                                    <div class="fw-bold">
                                        {{ $teacher->name }}
                                    </div>
                                    <div class="small">
                                        {{ $teacher->username }}
                                    </div>
                                </td>
                                <td class="align-middle text-center">
                                    {{ $teacher->phone }}
                                </td>
                                <td class="align-middle text-center">
                                    @if($teacher->admin_id)
                                        {{ $teacher->admin->name }}
                                    @endif
                                </td>
                                <td class="align-middle text-center">
                                    {{ $teacher->created_at }}
                                </td>
                                @can('teachers:password')
                                    <td class="align-middle text-center">
                                        @if($teacher->id != auth()->id())
                                            <a href="{{ url('/home/teachers/'.$teacher->id.'/password') }}"
                                               class="btn btn-info" style="font-size: 10px">
                                                Паролни тиклаш
                                            </a>
                                        @endif
                                    </td>
                                @endcan
                                @can('teachers:status')
                                    <td class="align-middle text-center">
                                        @if($teacher->id != auth()->id())
                                            <a href="{{ url('/home/teachers/'.$teacher->id.'/status') }}"
                                               class="btn btn-warning" style="font-size: 10px">
                                                @if ($teacher->status == 'active')
                                                    Блоклаш
                                                @else
                                                    Блокдан очиш
                                                @endif
                                            </a>
                                        @endif
                                    </td>
                                @endcan
                                <td class="align-middle text-center">
                                    @can('teachers:edit2')
                                        <a href="{{ route('teachers.edit', $teacher->id) }}"
                                           class="btn btn-success" style="font-size: 10px">
                                            янгилаш
                                        </a>
                                    @endcan
                                    @can('teachers:delete')
                                        <form action="{{route('teachers.destroy', $teacher->id)}}}"
                                              method="post" class="d-inline-block">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-primary m-1" type="submit" style="font-size: 10px"
                                                    onclick="return confirm('Ушбу фойдаланувчини ўчирмоқчимисиз?')">
                                                ўчириш
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
