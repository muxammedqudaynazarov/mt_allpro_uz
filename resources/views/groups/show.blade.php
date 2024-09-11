@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="justify-content-center">
            <div class="card">
                <div class="card-header fw-bold">
                    <div class="d-flex">
                        <div class="flex-grow-1 py-1">Гуруҳларни бошқариш</div>
                        @can('groups:create')
                            <div class="mx-1">
                                <a href="{{ route('groups.create') }}" class="btn btn-success btn-sm">
                                    Гуруҳ қўшиш
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
                            <th>Гуруҳ номи</th>
                            <th>Электрон почта</th>
                            <th>Курси</th>
                            <th></th>
                            <th style="width: 20%"></th>
                        </tr>
                        </thead>
                        <tbody class="small">
                        @foreach($groups as $group)
                            <tr>
                                <td class="text-center align-middle">{{ $group->hemis_id }}</td>
                                <td class="align-middle">
                                    {{ $group->name }}
                                </td>
                                <td class="text-center align-middle">
                                    {{ $group->email }}
                                </td>
                                <td class="text-center align-middle">
                                    {{ $group->course }}-курс
                                </td>
                                <td class="text-center align-middle">
                                    @can('groups:status')
                                        <div class="form-check form-switch">
                                            <input name="check_status"
                                                   onclick="change_status({{ $group->hemis_id }})"
                                                   type="checkbox"
                                                   class="form-check-input" role="switch"
                                                   id="change_status" @if($group->active == '1') checked @endif>
                                        </div>
                                    @endcan
                                </td>
                                <td class="text-center align-middle">
                                    @can('groups:show')
                                        <a href="{{ route('groups.show', $group->id) }}"
                                           class="btn btn-info m-1" style="font-size: 10px">
                                            кўриш
                                        </a>
                                    @endcan
                                    @can('groups:edit')
                                        <a href="{{ route('groups.edit', $group->id) }}"
                                           class="btn btn-success m-1" style="font-size: 10px">
                                            янгилаш
                                        </a>
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
@section('script')
    <script>
        function change_status(group_id) {
            $.get("{{ url('/home/groups/change_status') }}/" + group_id, function (data) {
                console.log(data);
            });
        }
    </script>
@endsection
