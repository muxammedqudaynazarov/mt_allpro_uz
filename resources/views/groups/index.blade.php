@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-bold">
                        <div class="d-flex">
                            <div class="flex-grow-1 py-1">Гуруҳларни бошқариш</div>
                            <div>
                                <a href="{{ url('/home') }}" class="btn btn-success btn-sm">
                                    Бекор қилиш
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                            <tr class="text-center">
                                <th>#</th>
                                <th>Гурух</th>
                                <th>E-mail</th>
                                <th>Курси</th>
                                <th>Холати</th>
                            </tr>
                            </thead>
                            <tbody class="small">
                            @foreach($groups as $group)
                                <tr>
                                    <td class="text-center align-middle">{{ $group->hemis_id }}</td>
                                    <td>{{ $group->name }}</td>
                                    <td class="text-center align-middle">{{ $group->email }}</td>
                                    <td class="text-center align-middle">{{ $group->course }}-курс</td>
                                    <td class="text-center align-middle">
                                        <div class="form-check form-switch">
                                            <input name="check_status" onclick="change_status({{ $group->hemis_id }})"
                                                   type="checkbox"
                                                   class="form-check-input" role="switch"
                                                   id="change_status" @if($group->active == '1') checked @endif>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
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
